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
use App\Security\Voter\StayVoter;
use App\Service\StayNotifier;
use App\Service\StayRealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

        $canManage    = $this->isGranted(StayVoter::MANAGE, $stay);
        $canAddPosition = $this->isGranted(StayVoter::ADD_POSITION, $stay);
        $canViewUnassigned = $this->isGranted(StayVoter::VIEW_UNASSIGNED, $stay);
        $allPositions = $this->positions->findByStayOrdered($stay);

        $manageablePositionIds = [];
        foreach ($allPositions as $tp) {
            if ($canManage || $this->isGranted(StayVoter::MANAGE_POSITION, $tp)) {
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
        foreach ($this->groups->findByProgrammeWithStudents($stay->getProgramme()) as $group) {
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

        $compatiblePositionsForStudent = [];
        foreach ($byGroup as $entry) {
            $pyId = $entry['group']->getProgrammeYear()->getId()->toRfc4122();
            foreach ($entry['students'] as $student) {
                $sid = $student->getId()->toRfc4122();
                if (!isset($studentPositionMap[$sid])) {
                    $compatible = array_values(array_filter(
                        $unassignedPositions,
                        static function (TrainingPosition $pos) use ($pyId): bool {
                            foreach ($pos->getProgrammeYears() as $py) {
                                if ($py->getId()->toRfc4122() === $pyId) {
                                    return true;
                                }
                            }
                            return false;
                        }
                    ));
                    $compatiblePositionsForStudent[$sid] = $compatible;
                }
            }
        }
        foreach ($ungroupedStudents as $student) {
            $sid = $student->getId()->toRfc4122();
            if (!isset($studentPositionMap[$sid])) {
                $compatiblePositionsForStudent[$sid] = $unassignedPositions;
            }
        }

        $programmeTeachers = $this->teachers->findByProgrammeOrderedByName($stay->getProgramme());

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
            return null;
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null || $position->getStudent() !== null) {
            return null;
        }

        if (!$this->isGranted(StayVoter::MANAGE_POSITION, $position)) {
            throw new AccessDeniedException();
        }

        $position->setStudent($student);
        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

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
        }

        $this->realtime->publishStayChanged($stay);

        return null;
    }

    /** @param array<string, string> $params */
    private function toast(string $key, array $params): void
    {
        $this->addFlash('live_toast', $this->translator->trans($key, $params, 'stays'));
    }
}
