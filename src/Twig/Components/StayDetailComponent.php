<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Repository\GroupRepository;
use App\Repository\StayRepository;
use App\Repository\TeacherRepository;
use App\Repository\TrainingPositionRepository;
use App\Repository\WorkerRepository;
use App\Security\StayScope;
use App\Security\Voter\PositionAssignment;
use App\Security\Voter\StayVoter;
use App\Service\StayNotifier;
use App\Service\StayRealtimeNotifier;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class StayDetailComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $stayId = '';

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly StayRepository $stays,
        private readonly TrainingPositionRepository $positions,
        private readonly GroupRepository $groups,
        private readonly TeacherRepository $teachers,
        private readonly WorkerRepository $workers,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
        private readonly StayNotifier $notifier,
        private readonly StayRealtimeNotifier $realtime,
        private readonly StayScope $scope,
        private readonly ClockInterface $clock,
    ) {}

    /** @return array<string, mixed> */
    public function getData(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw $this->createNotFoundException('Stay not found: ' . $this->stayId);
        }

        /** @var Teacher $actor */
        $actor        = $this->getUser();
        $canManage    = $this->isGranted(StayVoter::MANAGE, $stay);
        $canDelete    = $this->isGranted(StayVoter::DELETE, $stay);
        $canAddPosition = $this->isGranted(StayVoter::ADD_POSITION, $stay);
        $canViewUnassigned = $this->isGranted(StayVoter::VIEW_UNASSIGNED, $stay);
        // Los grupos se cargan antes que los puestos: así el alumnado llega con sus grupos ya
        // inicializados y deducir sus enseñanzas no lanza una consulta por alumno.
        $stayGroups   = $this->groups->findByStayWithStudents($stay);
        $allPositions = $this->positions->findByStayOrdered($stay);

        $manageablePositionIds = [];
        foreach ($allPositions as $tp) {
            if ($this->isGranted(StayVoter::MANAGE_POSITION, $tp)) {
                $manageablePositionIds[$tp->getId()->toRfc4122()] = true;
            }
        }

        $studentPositionMap  = [];
        $unassignedPositions = [];
        foreach ($allPositions as $tp) {
            if ($tp->getStudent() !== null) {
                $studentPositionMap[$tp->getStudent()->getId()->toRfc4122()] = $tp;
            } else {
                $unassignedPositions[] = $tp;
            }
        }

        $enrolledStudents = [];
        foreach ($stay->getStudents() as $s) {
            $enrolledStudents[$s->getId()->toRfc4122()] = $s;
        }

        $byGroup          = [];
        $placedStudentIds = [];
        foreach ($stayGroups as $group) {
            $groupStudents = [];
            foreach ($group->getStudents() as $student) {
                $sid = $student->getId()->toRfc4122();
                if (isset($enrolledStudents[$sid])) {
                    $groupStudents[]        = $student;
                    $placedStudentIds[$sid] = true;
                }
            }
            if ($groupStudents !== []) {
                $byGroup[] = ['group' => $group, 'students' => $groupStudents];
            }
        }

        $ungroupedStudents = [];
        foreach ($enrolledStudents as $sid => $student) {
            if (!isset($placedStudentIds[$sid])) {
                $ungroupedStudents[] = $student;
            }
        }
        usort($ungroupedStudents, fn ($a, $b) =>
            $a->getName()->getLastName() <=> $b->getName()->getLastName()
            ?: $a->getName()->getFirstName() <=> $b->getName()->getFirstName()
        );

        $countWithoutPosition = 0;
        $countUnsigned        = 0;
        foreach ($enrolledStudents as $sid => $student) {
            $tp = $studentPositionMap[$sid] ?? null;
            if ($tp === null) {
                $countWithoutPosition++;
            } elseif (!$tp->isSigned()) {
                $countUnsigned++;
            }
        }

        // Estudiantes cuyo puesto puede gestionar quien consulta (los de sus enseñanzas), y para
        // ellos los puestos libres compatibles con su nivel. Un docente de enlace solo puede
        // asignar los puestos libres de sus empresas.
        $today                         = $this->clock->now();
        $manageableStudentIds          = [];
        $compatiblePositionsForStudent = [];
        // Puestos libres de empresas de las que el docente es enlace (una consulta por puesto, no por alumno).
        $liaisonPositionIds = [];
        $liaisonOfCompany   = [];
        foreach ($unassignedPositions as $pos) {
            $company = $pos->getWorkcenter()?->getCompany();
            if ($company === null) {
                continue;
            }
            $companyId = $company->getId()->toRfc4122();
            $liaisonOfCompany[$companyId] ??= $company->getLiaisons()->contains($actor);
            if ($liaisonOfCompany[$companyId]) {
                $liaisonPositionIds[$pos->getId()->toRfc4122()] = true;
            }
        }
        $studentsToPlace = $ungroupedStudents;
        foreach ($byGroup as $entry) {
            foreach ($entry['students'] as $student) {
                $studentsToPlace[] = $student;
            }
        }
        foreach ($studentsToPlace as $student) {
            $sid = $student->getId()->toRfc4122();
            if ($this->scope->canManageStudent($actor, $stay, $student)) {
                $manageableStudentIds[$sid] = true;
            }
            if (isset($studentPositionMap[$sid])) {
                continue;
            }
            $isMine = isset($manageableStudentIds[$sid]);
            $compatiblePositionsForStudent[$sid] = array_values(array_filter(
                $unassignedPositions,
                fn (TrainingPosition $pos): bool => $pos->acceptsStudent($student)
                    && !$pos->isReservedAgainst($student, $today)
                    && ($isMine || isset($liaisonPositionIds[$pos->getId()->toRfc4122()]))
            ));
        }

        $programmeSummary = $stay->getProgrammes()->count() > 1
            ? $this->buildProgrammeSummary($stay, $actor, $enrolledStudents, $studentPositionMap, $unassignedPositions, $today)
            : [];

        $programmeTeachers = $this->teachers->findByStayProgrammesOrderedByName($stay);

        $companiesNeedingWorkers = [];
        foreach ($studentPositionMap as $tp) {
            if ($tp->getWorkplaceMentor() === null && $tp->getWorkcenter() !== null) {
                $company = $tp->getWorkcenter()->getCompany();
                $companiesNeedingWorkers[$company->getId()->toRfc4122()] = $company;
            }
        }
        $workersByCompanyId = $this->workers->findGroupedByCompanies(array_values($companiesNeedingWorkers));

        $statsMap = $this->stays->findStatsForStays([$stay]);
        $stats    = $statsMap[$stay->getId()->toRfc4122()] ?? [];

        $this->cache = [
            'stay'                             => $stay,
            'can_manage'                       => $canManage,
            'can_delete'                       => $canDelete,
            'programme_summary'                => $programmeSummary,
            'manageable_student_ids'           => $manageableStudentIds,
            'can_add_position'                 => $canAddPosition,
            'can_view_unassigned'              => $canViewUnassigned,
            'manageable_position_ids'          => $manageablePositionIds,
            'by_group'                         => $byGroup,
            'ungrouped_students'               => $ungroupedStudents,
            'student_position_map'             => $studentPositionMap,
            'unassigned_positions'             => $unassignedPositions,
            'compatible_positions_for_student' => $compatiblePositionsForStudent,
            'count_without_position'           => $countWithoutPosition,
            'count_unsigned'                   => $countUnsigned,
            'programme_teachers'               => $programmeTeachers,
            'workers_by_company_id'            => $workersByCompanyId,
            'stats'                            => $stats,
        ];

        return $this->cache;
    }



    /**
     * Resumen del reparto por enseñanza: alumnado, alumnado sin puesto y puestos libres a los que
     * puede optar cada una (separando los reservados por una preferencia vigente a otra enseñanza).
     *
     * @param array<string, \App\Entity\Student>    $enrolledStudents
     * @param array<string, TrainingPosition>        $studentPositionMap
     * @param list<TrainingPosition>                 $unassignedPositions
     * @return list<array{programme: \App\Entity\Programme, mine: bool, students: int, without_position: int, free: int, reserved_elsewhere: int}>
     */
    private function buildProgrammeSummary(
        Stay $stay,
        Teacher $actor,
        array $enrolledStudents,
        array $studentPositionMap,
        array $unassignedPositions,
        \DateTimeImmutable $today,
    ): array {
        $studentProgrammeIds = [];
        foreach ($enrolledStudents as $sid => $student) {
            foreach ($stay->getProgrammesOfStudent($student) as $programme) {
                $studentProgrammeIds[$sid][$programme->getId()->toRfc4122()] = true;
            }
        }

        $offeredIds = [];
        foreach ($unassignedPositions as $index => $position) {
            foreach ($position->getProgrammeYears() as $programmeYear) {
                $offeredIds[$index][$programmeYear->getProgramme()->getId()->toRfc4122()] = true;
            }
        }

        $summary = [];
        foreach ($stay->getProgrammesSorted() as $programme) {
            $pid  = $programme->getId()->toRfc4122();
            $row  = [
                'programme'          => $programme,
                'mine'               => $this->scope->canManageProgramme($actor, $stay, $programme),
                'students'           => 0,
                'without_position'   => 0,
                'free'               => 0,
                'reserved_elsewhere' => 0,
            ];

            foreach ($enrolledStudents as $sid => $student) {
                if (!isset($studentProgrammeIds[$sid][$pid])) {
                    continue;
                }
                ++$row['students'];
                if (!isset($studentPositionMap[$sid])) {
                    ++$row['without_position'];
                }
            }

            foreach ($unassignedPositions as $index => $position) {
                // Un puesto sin niveles se considera ofertado a todas las enseñanzas.
                if (isset($offeredIds[$index]) && !isset($offeredIds[$index][$pid])) {
                    continue;
                }
                if ($position->hasActivePriority($today) && !$position->getPriorityProgramme()?->getId()->equals($programme->getId())) {
                    ++$row['reserved_elsewhere'];
                    continue;
                }
                ++$row['free'];
            }

            $summary[] = $row;
        }

        return $summary;
    }

    #[LiveAction]
    public function assignPosition(#[LiveArg] string $studentId, #[LiveArg] string $positionId): ?Response
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new AccessDeniedException();
        }

        $student = null;
        foreach ($stay->getStudents() as $s) {
            if ($s->getId()->toRfc4122() === $studentId) {
                $student = $s;
                break;
            }
        }
        if ($student === null) {
            $this->toast('stays.toast.student_not_in_stay', []);

            return null;
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null || $position->getStudent() !== null) {
            $this->toast('stays.toast.position_unavailable', []);

            return null;
        }

        if (!$this->isGranted(StayVoter::ASSIGN, new PositionAssignment($position, $student))) {
            throw new AccessDeniedException();
        }

        if (!$position->acceptsStudent($student)) {
            $this->toast('stays.toast.position_incompatible', [
                '%student%' => $student->getName()->getFirstName() . ' ' . $student->getName()->getLastName(),
            ]);

            return null;
        }

        if ($position->isReservedAgainst($student, $this->clock->now())) {
            $this->toast('stays.toast.position_reserved', [
                '%student%'   => $student->getName()->getFirstName() . ' ' . $student->getName()->getLastName(),
                '%programme%' => $position->getPriorityProgramme()?->getName() ?? '',
                '%date%'      => $position->getPriorityUntil()?->format('d/m/Y') ?? '',
            ]);

            return null;
        }

        // Un alumno solo puede tener un puesto por estancia. Sin esta comprobación, dos asignaciones
        // seguidas (asignación rápida agrupada en un lote, o dos docentes a la vez) violarían la
        // restricción única y devolverían un error 500 dejando la pantalla desincronizada.
        if ($this->positions->findByStayAndStudent($stay, $student) !== null) {
            $this->toast('stays.toast.student_already_assigned', [
                '%student%' => $student->getName()->getFirstName() . ' ' . $student->getName()->getLastName(),
            ]);

            return null;
        }

        // Se bloquea la fila del puesto y se relee su estado dentro de una transacción: si otra
        // coordinación se ha adelantado en ese mismo instante se informa de quién se lo ha llevado,
        // en lugar de fallar con un conflicto de versiones y recargar la página.
        $this->em->beginTransaction();
        try {
            $this->em->refresh($position, LockMode::PESSIMISTIC_WRITE);

            if ($position->getStudent() !== null) {
                $this->em->rollback();
                $this->toast('stays.toast.position_taken', [
                    '%student%' => $position->getStudent()->getName()->getFirstName() . ' ' . $position->getStudent()->getName()->getLastName(),
                ]);

                return null;
            }

            $position->setStudent($student);
            $this->em->flush();
            $this->em->commit();
        } catch (EntityNotFoundException) {
            $this->rollbackQuietly();
            $this->toast('stays.toast.position_unavailable', []);

            return null;
        } catch (OptimisticLockException) {
            $this->rollbackQuietly();
            $this->addFlash('error', $this->translator->trans('stays.flash.position_conflict', [], 'stays'));

            return $this->redirectToRoute('app_stays_show', ['id' => $this->stayId]);
        } catch (UniqueConstraintViolationException) {
            $this->rollbackQuietly();
            $this->addFlash('error', $this->translator->trans('stays.flash.assign_conflict', [], 'stays'));

            return $this->redirectToRoute('app_stays_show', ['id' => $this->stayId]);
        }

        $this->realtime->publishStayChanged($stay);

        /** @var Teacher $actor */
        $actor = $this->getUser();
        $this->notifier->notifySharedPositionTaken($position, $student, $actor);

        $this->toast('stays.toast.position_assigned', [
            '%student%' => $student->getName()->getFirstName() . ' ' . $student->getName()->getLastName(),
        ]);

        return null;
    }

    #[LiveAction]
    public function unassignPosition(#[LiveArg] string $positionId): ?Response
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new AccessDeniedException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null
            || $position->getStudent() === null
            || $position->getState() !== TrainingPositionState::DRAFT
        ) {
            return null;
        }

        if (!$this->isGranted(StayVoter::MANAGE_POSITION, $position)) {
            throw new AccessDeniedException();
        }

        $student = $position->getStudent();
        $position->setStudent(null);
        $position->setAcademicTutor(null);
        $position->setWorkplaceMentor(null);
        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        $this->toast('stays.toast.position_unassigned', [
            '%student%' => $student->getName()->getFirstName() . ' ' . $student->getName()->getLastName(),
        ]);

        return null;
    }

    #[LiveAction]
    public function setAcademicTutor(#[LiveArg] string $positionId, #[LiveArg] string $teacherId): ?Response
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new AccessDeniedException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null || $position->getStudent() === null) {
            return null;
        }

        if (!$this->isGranted(StayVoter::MANAGE_POSITION, $position)) {
            throw new AccessDeniedException();
        }

        $teacher = $this->teachers->findById($teacherId);
        if ($teacher === null) {
            return null;
        }

        $previousTutorId = $position->getAcademicTutor()?->getId()->toRfc4122();
        $position->setAcademicTutor($teacher);
        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        if ($teacher->getId()->toRfc4122() !== $previousTutorId) {
            $this->notifier->notifyTutorAssigned($position);
        }

        $this->toast('stays.toast.academic_tutor_set', [
            '%teacher%' => $teacher->getName()->getFirstName() . ' ' . $teacher->getName()->getLastName(),
        ]);

        return null;
    }

    #[LiveAction]
    public function setWorkplaceMentor(#[LiveArg] string $positionId, #[LiveArg] string $workerId): ?Response
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new AccessDeniedException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null || $position->getStudent() === null || $position->getWorkcenter() === null) {
            return null;
        }

        if (!$this->isGranted(StayVoter::MANAGE_POSITION, $position)) {
            throw new AccessDeniedException();
        }

        $mentor = null;
        foreach ($position->getWorkcenter()->getCompany()->getWorkers() as $w) {
            if ($w->getId()->toRfc4122() === $workerId) {
                $mentor = $w;
                break;
            }
        }
        if ($mentor === null) {
            return null;
        }

        $position->setWorkplaceMentor($mentor);
        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        $this->toast('stays.toast.workplace_mentor_set', [
            '%mentor%' => $mentor->getName()->getFirstName() . ' ' . $mentor->getName()->getLastName(),
        ]);

        return null;
    }

    /**
     * Persiste los cambios y publica el aviso de tiempo real. Si otra persona
     * modificó el puesto en paralelo (bloqueo optimista), el flush cierra el EM:
     * redirigimos a la estancia para recargar con datos frescos en lugar de pisar.
     */
    private function flushWithRealtime(Stay $stay): ?Response
    {
        try {
            $this->em->flush();
        } catch (OptimisticLockException) {
            $this->addFlash('error', $this->translator->trans('stays.flash.position_conflict', [], 'stays'));

            return $this->redirectToRoute('app_stays_show', ['id' => $this->stayId]);
        } catch (UniqueConstraintViolationException) {
            // Otra persona asignó a ese alumno a otro puesto en paralelo.
            $this->addFlash('error', $this->translator->trans('stays.flash.assign_conflict', [], 'stays'));

            return $this->redirectToRoute('app_stays_show', ['id' => $this->stayId]);
        }

        $this->realtime->publishStayChanged($stay);

        return null;
    }

    private function rollbackQuietly(): void
    {
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    /** @param array<string, string> $params */
    private function toast(string $key, array $params): void
    {
        $this->addFlash('live_toast', $this->translator->trans($key, $params, 'stays'));
    }
}
