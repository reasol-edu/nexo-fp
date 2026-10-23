<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dashboard\StayProgress;
use App\Entity\Group;
use App\Entity\Stay;
use App\Entity\StayTeacherHours;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Entity\Worker;
use App\Repository\GroupRepository;
use App\Repository\StayRepository;
use App\Repository\StayTeacherHoursRepository;
use App\Repository\TeacherRepository;
use App\Repository\TrainingPositionRepository;
use App\Repository\WorkerRepository;
use App\Security\StayScope;
use App\Security\Voter\PositionAssignment;
use App\Security\Voter\StayVoter;
use App\Service\PositionFit;
use App\Service\PositionStateChanger;
use App\Service\StayNotifier;
use App\Service\StayRealtimeNotifier;
use App\Service\TeacherAssignmentCalculator;
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
    /** Máximo de puestos de una acción masiva que se pueden deshacer (el estado previo viaja en el botón). */
    private const MAX_UNDO_BULK = 60;

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
        private readonly PositionStateChanger $stateChanger,
        private readonly StayTeacherHoursRepository $teacherHours,
        private readonly TeacherAssignmentCalculator $calculator,
        private readonly \App\Repository\StudentYearProfileRepository $profiles,
        private readonly \App\Repository\StudentRepository $students,
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

        // Quien no realiza la FFEOE sigue en la lista mientras no se le quite de la estancia, pero no
        // cuenta como alumnado de la estancia (sin puesto, avance, reparto por docente), salvo que ya tenga puesto.
        $profileMap       = $this->profiles->findByStay($stay);
        $countedStudents  = array_filter(
            $enrolledStudents,
            static fn (\App\Entity\Student $s, string $sid): bool => ($profileMap[$sid] ?? null)?->takesFfeoe() !== false || isset($studentPositionMap[$sid]),
            ARRAY_FILTER_USE_BOTH,
        );
        $countedByGroup = [];
        foreach ($byGroup as $entry) {
            $entry['students'] = array_values(array_filter(
                $entry['students'],
                static fn (\App\Entity\Student $s): bool => isset($countedStudents[$s->getId()->toRfc4122()]),
            ));
            if ($entry['students'] !== []) {
                $countedByGroup[] = $entry;
            }
        }

        // «7/13 con puesto» en la cabecera de cada grupo (con el mismo criterio que los recuentos de la estancia).
        $groupStats = [];
        foreach ($countedByGroup as $entry) {
            $assigned = 0;
            foreach ($entry['students'] as $student) {
                if (isset($studentPositionMap[$student->getId()->toRfc4122()])) {
                    $assigned++;
                }
            }
            $groupStats[$entry['group']->getId()->toRfc4122()] = ['assigned' => $assigned, 'total' => \count($entry['students'])];
        }

        $countWithoutPosition = 0;
        $countUnsigned        = 0;
        foreach ($countedStudents as $sid => $student) {
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
        $positionFits                  = [];
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
            // Los puestos que mejor encajan con su ficha (empresa que aporta, localidades) van primero.
            [$compatiblePositionsForStudent[$sid], $positionFits[$sid]] = PositionFit::rank(array_values(array_filter(
                $unassignedPositions,
                fn (TrainingPosition $pos): bool => $pos->acceptsStudent($student)
                    && !$pos->isReservedAgainst($student, $today)
                    && ($isMine || isset($liaisonPositionIds[$pos->getId()->toRfc4122()]))
            )), $profileMap[$sid] ?? null);
        }

        $programmeSummary = $stay->getProgrammes()->count() > 1
            ? $this->buildProgrammeSummary($stay, $actor, $countedStudents, $studentPositionMap, $unassignedPositions, $today)
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
        $tutorSuggestions   = $this->buildTutorSuggestions($studentPositionMap, $manageablePositionIds, $programmeTeachers, $workersByCompanyId);

        $statsMap = $this->stays->findStatsForStays([$stay]);
        $stats    = $statsMap[$stay->getId()->toRfc4122()] ?? [];

        // Ficha del curso de cada estudiante (una consulta) y a quién puede editar quien consulta.
        $year             = $stay->getAcademicYear();
        $centre           = $year->getEducationalCentre();
        $activeYear       = $centre->getActiveAcademicYear();
        $yearIsActive     = $activeYear === null || $activeYear->getId()->toRfc4122() === $year->getId()->toRfc4122();
        $editsAllProfiles = $actor->isAdmin() || $centre->getAdmins()->contains($actor);
        $responsibleIds   = $editsAllProfiles ? [] : array_fill_keys($this->students->findResponsibleStudentIds($actor, $year), true);
        $profileEditable  = $yearIsActive ? $responsibleIds : [];
        // La actitud la ven quienes editan la ficha (también en un curso anterior) y quienes pueden asignar puesto al estudiante.
        $attitudeVisible  = $responsibleIds + $manageableStudentIds;

        // Quien no realiza la FFEOE y no tiene puesto se puede quitar de la estancia de una vez
        // (solo los de las enseñanzas que gestiona quien consulta).
        $removableNonFfeoe = [];
        $blockedNonFfeoe   = 0;
        foreach ($enrolledStudents as $sid => $student) {
            if (($profileMap[$sid] ?? null)?->takesFfeoe() !== false) {
                continue;
            }
            if (isset($studentPositionMap[$sid])) {
                $blockedNonFfeoe++;
            } elseif (isset($manageableStudentIds[$sid])) {
                $removableNonFfeoe[] = $student;
            }
        }

        $progress = $this->buildProgress(\count($countedStudents), \count($allPositions), array_values($studentPositionMap), $canManage, $canViewUnassigned);

        $this->cache = [
            'stay'                             => $stay,
            'can_manage'                       => $canManage,
            'can_delete'                       => $canDelete,
            'programme_summary'                => $programmeSummary,
            'manageable_student_ids'           => $manageableStudentIds,
            // Gestiona algún nivel de la estancia pero no todos (p. ej. solo 2.º de una enseñanza): hay estudiantes ajenos
            // aunque la estancia tenga una sola enseñanza, así que se muestran los candados y el filtro «Mis estudiantes».
            'partial_scope'                    => $this->scope->canManageAny($actor, $stay) && !$this->scope->canManageEveryLevel($actor, $stay),
            'can_add_position'                 => $canAddPosition,
            'can_view_unassigned'              => $canViewUnassigned,
            'manageable_position_ids'          => $manageablePositionIds,
            'by_group'                         => $byGroup,
            'ungrouped_students'               => $ungroupedStudents,
            'student_position_map'             => $studentPositionMap,
            'unassigned_positions'             => $unassignedPositions,
            'compatible_positions_for_student' => $compatiblePositionsForStudent,
            'position_fits'                    => $positionFits,
            'group_stats'                      => $groupStats,
            'count_without_position'           => $countWithoutPosition,
            'count_unsigned'                   => $countUnsigned,
            'programme_teachers'               => $programmeTeachers,
            'workers_by_company_id'            => $workersByCompanyId,
            'tutor_suggestions'                => $tutorSuggestions,
            'stats'                            => $stats,
            'progress'                         => $progress,
            'profiles'                         => $profileMap,
            'profile_edit_all'                 => $yearIsActive && $editsAllProfiles,
            'profile_editable_ids'             => $profileEditable,
            'attitude_all'                     => $editsAllProfiles,
            'attitude_visible_ids'             => $attitudeVisible,
            'removable_non_ffeoe'              => $removableNonFfeoe,
            'blocked_non_ffeoe'                => $blockedNonFfeoe,
            'teacher_assignments'              => $stay->isCalculateByTeacher()
                ? $this->buildTeacherAssignments($stay, $countedByGroup, $studentPositionMap)
                : null,
        ];

        return $this->cache;
    }

    /**
     * Reparto de tutorías duales docentes por docente (ajuste «Calcular asignaciones por docente»).
     *
     * Para cada grupo con estudiantes en la estancia, cada docente del grupo tiene indicadas unas horas
     * dualizadas y de ellas sale cuántos estudiantes debería tutorizar (la suma por grupo es siempre el
     * número de estudiantes del grupo). Se compara con los que ya tiene asignados como tutor dual docente.
     *
     * @param list<array{group: Group, students: list<\App\Entity\Student>}> $byGroup
     * @param array<string, TrainingPosition>                                $studentPositionMap
     *
     * @return array{
     *     groups: list<array{group: Group, students: int, total_hours: int, rows: list<array{teacher: Teacher, hours: int, expected: int, assigned: int, in_group: bool}>}>,
     *     teachers: list<array{teacher: Teacher, expected: int, assigned: int, by_group: list<array{group: Group, expected: int, assigned: int}>}>
     * }
     */
    private function buildTeacherAssignments(Stay $stay, array $byGroup, array $studentPositionMap): array
    {
        $savedHours = $this->teacherHours->findHoursByStay($stay);
        $byName     = static fn (Teacher $a, Teacher $b): int =>
            $a->getName()->getLastName() <=> $b->getName()->getLastName()
            ?: $a->getName()->getFirstName() <=> $b->getName()->getFirstName();

        $groups   = [];
        $teachers = [];

        foreach ($byGroup as $entry) {
            /** @var Group $group */
            $group   = $entry['group'];
            $groupId = $group->getId()->toRfc4122();

            // Docentes del grupo y, aunque no figuren en él, quienes ya tutorizan a estudiantes suyos.
            /** @var array<string, Teacher> $people */
            $people   = [];
            foreach ($group->getTeachers() as $teacher) {
                $people[$teacher->getId()->toRfc4122()] = $teacher;
            }
            $assigned = [];
            foreach ($entry['students'] as $student) {
                $tutor = ($studentPositionMap[$student->getId()->toRfc4122()] ?? null)?->getAcademicTutor();
                if ($tutor !== null) {
                    $tutorId           = $tutor->getId()->toRfc4122();
                    $people[$tutorId] ??= $tutor;
                    $assigned[$tutorId] = ($assigned[$tutorId] ?? 0) + 1;
                }
            }

            $hours = [];
            foreach ($people as $teacherId => $teacher) {
                $hours[$teacherId] = $savedHours[$groupId][$teacherId] ?? 0;
            }
            $expected = $this->calculator->distribute(\count($entry['students']), $hours);

            $sorted = array_values($people);
            usort($sorted, $byName);

            $rows = [];
            foreach ($sorted as $teacher) {
                $teacherId = $teacher->getId()->toRfc4122();
                $row = [
                    'teacher'  => $teacher,
                    'hours'    => $hours[$teacherId],
                    'expected' => $expected[$teacherId],
                    'assigned' => $assigned[$teacherId] ?? 0,
                    'in_group' => $group->getTeachers()->contains($teacher),
                ];
                $rows[] = $row;

                if ($row['hours'] > 0 || $row['assigned'] > 0) {
                    $teachers[$teacherId] ??= ['teacher' => $teacher, 'expected' => 0, 'assigned' => 0, 'by_group' => []];
                    $teachers[$teacherId]['expected'] += $row['expected'];
                    $teachers[$teacherId]['assigned'] += $row['assigned'];
                    $teachers[$teacherId]['by_group'][] = ['group' => $group, 'expected' => $row['expected'], 'assigned' => $row['assigned']];
                }
            }

            $groups[] = [
                'group'       => $group,
                'students'    => \count($entry['students']),
                'total_hours' => array_sum($hours),
                'rows'        => $rows,
            ];
        }

        $summary = array_values($teachers);
        usort($summary, static fn (array $a, array $b): int => $byName($a['teacher'], $b['teacher']));

        return ['groups' => $groups, 'teachers' => $summary];
    }

    /**
     * Sugerencia de tutores para los puestos a los que les falta alguno: el tutor docente y el tutor de
     * empresa que más repiten los demás puestos de la misma empresa en esta estancia. Solo se sugiere a
     * quien se puede asignar (docentes de las enseñanzas, trabajadores de esa empresa) y solo en puestos
     * que quien consulta gestiona. No hace consultas: usa lo ya cargado.
     *
     * @param array<string, TrainingPosition>  $studentPositionMap  puestos con estudiante
     * @param array<string, true>              $manageablePositionIds
     * @param list<Teacher>                    $programmeTeachers
     * @param array<string, list<Worker>>      $workersByCompanyId
     *
     * @return array<string, array{academic: ?Teacher, mentor: ?Worker}> por id de puesto
     */
    private function buildTutorSuggestions(array $studentPositionMap, array $manageablePositionIds, array $programmeTeachers, array $workersByCompanyId): array
    {
        $assignableTeachers = [];
        foreach ($programmeTeachers as $teacher) {
            $assignableTeachers[$teacher->getId()->toRfc4122()] = $teacher;
        }

        // Frecuencia de cada tutor en los puestos de cada empresa
        $academicByCompany = [];
        $mentorByCompany   = [];
        foreach ($studentPositionMap as $position) {
            $companyId = $position->getWorkcenter()?->getCompany()->getId()->toRfc4122();
            if ($companyId === null) {
                continue;
            }
            if (($tutor = $position->getAcademicTutor()) !== null && isset($assignableTeachers[$tutor->getId()->toRfc4122()])) {
                $academicByCompany[$companyId][$tutor->getId()->toRfc4122()] = ($academicByCompany[$companyId][$tutor->getId()->toRfc4122()] ?? 0) + 1;
            }
            if (($mentor = $position->getWorkplaceMentor()) !== null) {
                $mentorByCompany[$companyId][$mentor->getId()->toRfc4122()] = ($mentorByCompany[$companyId][$mentor->getId()->toRfc4122()] ?? 0) + 1;
            }
        }

        $suggestions = [];
        foreach ($studentPositionMap as $position) {
            $positionId = $position->getId()->toRfc4122();
            $companyId  = $position->getWorkcenter()?->getCompany()->getId()->toRfc4122();
            if ($companyId === null || !isset($manageablePositionIds[$positionId])) {
                continue;
            }

            $academic = null;
            if ($position->getAcademicTutor() === null && isset($academicByCompany[$companyId])) {
                arsort($academicByCompany[$companyId]);
                $academic = $assignableTeachers[array_key_first($academicByCompany[$companyId])] ?? null;
            }
            $mentor = null;
            if ($position->getWorkplaceMentor() === null && isset($mentorByCompany[$companyId])) {
                arsort($mentorByCompany[$companyId]);
                $mentorId = array_key_first($mentorByCompany[$companyId]);
                foreach ($workersByCompanyId[$companyId] ?? [] as $worker) {
                    if ($worker->getId()->toRfc4122() === $mentorId) {
                        $mentor = $worker;
                        break;
                    }
                }
            }
            if ($academic !== null || $mentor !== null) {
                $suggestions[$positionId] = ['academic' => $academic, 'mentor' => $mentor];
            }
        }

        return $suggestions;
    }

    /**
     * Avance de la estancia (alumnado con puesto, con ambos tutores, registrado y firmado) y el
     * siguiente paso recomendado. Solo cuenta los puestos con estudiante, de modo que quien no
     * puede ver los puestos libres no deduce cuántos hay.
     *
     * @param list<TrainingPosition> $assigned puestos con estudiante
     * @return array{
     *     steps: list<array{key: string, done: int, total: int}>,
     *     next: array{key: string, filter: string|null, count: int}
     * }
     */
    private function buildProgress(int $students, int $positionsTotal, array $assigned, bool $canManage, bool $canViewUnassigned): array
    {
        // La misma lógica que las tarjetas del inicio (ver StayProgress).
        return StayProgress::calculate(
            $students,
            $positionsTotal,
            array_map(static fn (TrainingPosition $p): array => [
                'state'       => $p->getState(),
                'signed'      => $p->isSigned(),
                'hasAcademic' => $p->getAcademicTutor() !== null,
                'hasMentor'   => $p->getWorkplaceMentor() !== null,
            ], $assigned),
            $canManage,
            $canViewUnassigned,
        );
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
                if ($position->hasActivePriority($today) && !$position->getPriorityProgrammeYear()?->getProgramme()->getId()->equals($programme->getId())) {
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

        // Quien no realiza la FFEOE no puede tener puesto: antes hay que cambiar su ficha (o quitarlo de la estancia).
        $profile = $this->profiles->findByStudentAndYear($student, $stay->getAcademicYear());
        if ($profile !== null && !$profile->takesFfeoe()) {
            $this->toast('stays.toast.student_no_ffeoe', [
                '%student%' => $student->getName()->getFirstName() . ' ' . $student->getName()->getLastName(),
            ]);

            return null;
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
                '%programme%' => $position->getPriorityProgrammeYear()?->getLabel() ?? '',
                '%date%'      => $position->getPriorityUntil()?->format('d/m/Y') ?? '',
            ]);

            return null;
        }

        // Un alumno solo puede tener un puesto por estancia. Sin esta comprobación, dos asignaciones
        // seguidas (varias asignaciones agrupadas en un lote, o dos docentes a la vez) violarían la
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

    /**
     * Quita de la estancia a todo el alumnado que no realiza la FFEOE y no tiene puesto, y que gestiona
     * quien lo pide (los de otras enseñanzas y los que ya tienen puesto no se tocan).
     */
    #[LiveAction]
    public function removeNonFfeoeStudents(): ?Response
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null || !$this->isGranted(StayVoter::MANAGE, $stay)) {
            throw new AccessDeniedException();
        }

        /** @var Teacher $actor */
        $actor    = $this->getUser();
        $profiles = $this->profiles->findByStay($stay);
        $positioned = [];
        foreach ($this->positions->findByStayOrdered($stay) as $tp) {
            if ($tp->getStudent() !== null) {
                $positioned[$tp->getStudent()->getId()->toRfc4122()] = true;
            }
        }

        $removed = 0;
        foreach ($stay->getStudents()->toArray() as $student) {
            $sid = $student->getId()->toRfc4122();
            if (($profiles[$sid] ?? null)?->takesFfeoe() !== false || isset($positioned[$sid])) {
                continue;
            }
            if ($this->scope->canManageStudent($actor, $stay, $student)) {
                $stay->removeStudent($student);
                $removed++;
            }
        }

        if ($removed === 0) {
            return null;
        }

        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        $this->toast('stays.toast.non_ffeoe_removed', ['%count%' => $removed]);

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

        $student        = $position->getStudent();
        $previousTutor  = $position->getAcademicTutor()?->getId()->toRfc4122() ?? '';
        $previousMentor = $position->getWorkplaceMentor()?->getId()->toRfc4122() ?? '';
        $position->setStudent(null);
        $position->setAcademicTutor(null);
        $position->setWorkplaceMentor(null);
        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        $this->toast(
            'stays.toast.position_unassigned',
            ['%student%' => $student->getName()->getFirstName() . ' ' . $student->getName()->getLastName()],
            ['action' => 'restoreAssignment', 'args' => [
                'positionId' => $positionId,
                'studentId'  => $student->getId()->toRfc4122(),
                'tutorId'    => $previousTutor,
                'mentorId'   => $previousMentor,
            ]],
        );

        return null;
    }

    /**
     * Deshace una liberación de puesto: vuelve a asignar el estudiante con las mismas comprobaciones que
     * una asignación normal (puesto libre, nivel, preferencia, alumno sin otro puesto) y le devuelve
     * las tutorías que tenía, si siguen existiendo.
     */
    #[LiveAction]
    public function restoreAssignment(
        #[LiveArg] string $positionId,
        #[LiveArg] string $studentId,
        #[LiveArg] string $tutorId = '',
        #[LiveArg] string $mentorId = '',
    ): ?Response {
        if (($response = $this->assignPosition($studentId, $positionId)) !== null) {
            return $response;
        }

        $stay = $this->stays->findById($this->stayId);
        $position = $stay !== null ? $this->positions->findByIdAndStay($positionId, $stay) : null;
        if ($stay === null || $position === null || $position->getStudent()?->getId()->toRfc4122() !== $studentId) {
            return null;
        }

        $changed = false;
        if ($tutorId !== '' && ($tutor = $this->teachers->findById($tutorId)) !== null) {
            $position->setAcademicTutor($tutor);
            $changed = true;
        }
        if ($mentorId !== '' && $position->getWorkcenter() !== null) {
            foreach ($position->getWorkcenter()->getCompany()->getWorkers() as $worker) {
                if ($worker->getId()->toRfc4122() === $mentorId) {
                    $position->setWorkplaceMentor($worker);
                    $changed = true;
                    break;
                }
            }
        }

        if ($changed && ($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        return null;
    }

    /**
     * Cambia el estado de un puesto (borrador → pendiente → registrado) sin salir del detalle.
     * Al salir de «Registrado» se retira la firma; `signed` solo cuenta al volver a «Registrado»
     * (lo usa «Deshacer» para restaurar la firma que había).
     */
    #[LiveAction]
    public function changeState(
        #[LiveArg] string $positionId,
        #[LiveArg] string $state,
        #[LiveArg] bool $signed = false,
    ): ?Response {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new AccessDeniedException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null) {
            return null;
        }

        if (!$this->isGranted(StayVoter::MANAGE_POSITION, $position)) {
            throw new AccessDeniedException();
        }

        $target = TrainingPositionState::tryFrom($state);
        $previous = $position->getState();
        if ($target === null || $target === $previous) {
            return null;
        }

        $previousSigned = $position->isSigned();
        if (!$this->stateChanger->transition($position, $target, $signed)) {
            $this->toast('stays.toast.state_requires_tutors', []);

            return null;
        }
        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        $this->toast(
            'stays.toast.state_changed',
            ['%student%' => $this->studentLabel($position), '%state%' => $this->translator->trans('stays.state.' . strtolower($target->value), [], 'stays')],
            ['action' => 'changeState', 'args' => ['positionId' => $positionId, 'state' => $previous->value, 'signed' => $previousSigned]],
        );

        return null;
    }

    /** Marca o quita la firma del convenio de un puesto registrado en Séneca. */
    #[LiveAction]
    public function setSigned(#[LiveArg] string $positionId, #[LiveArg] bool $signed): ?Response
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new AccessDeniedException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null) {
            return null;
        }

        if (!$this->isGranted(StayVoter::MANAGE_POSITION, $position)) {
            throw new AccessDeniedException();
        }

        if (!$this->stateChanger->sign($position, $signed)) {
            return null;
        }

        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        $this->toast(
            $signed ? 'stays.toast.signed' : 'stays.toast.unsigned',
            ['%student%' => $this->studentLabel($position)],
            ['action' => 'setSigned', 'args' => ['positionId' => $positionId, 'signed' => !$signed]],
        );

        return null;
    }

    /**
     * Acción masiva sobre los puestos marcados: `pending`, `done` o `draft` (estado), `sign` o
     * `unsign` (firma) y `tutor` (tutor dual docente). Los puestos que no se pueden cambiar
     * (otra coordinación, faltan tutores, estado incompatible) se omiten y se cuentan en el aviso.
     *
     * @param list<string> $positionIds
     */
    #[LiveAction]
    public function bulk(
        #[LiveArg] string $op,
        #[LiveArg] array $positionIds,
        #[LiveArg] string $teacherId = '',
    ): ?Response {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new AccessDeniedException();
        }

        $teacher = null;
        if ($op === 'tutor') {
            $teacher = $teacherId !== '' ? $this->teachers->findById($teacherId) : null;
            if ($teacher === null) {
                return null;
            }
        } elseif (!\in_array($op, ['pending', 'done', 'draft', 'sign', 'unsign'], true)) {
            return null;
        }

        $done = 0;
        $skipped = 0;
        $notify = [];
        $snapshot = [];
        foreach (array_slice(array_unique(array_filter($positionIds, 'is_string')), 0, 500) as $positionId) {
            $position = $this->positions->findByIdAndStay($positionId, $stay);
            if ($position === null || !$this->isGranted(StayVoter::MANAGE_POSITION, $position)) {
                ++$skipped;
                continue;
            }

            $before = [
                'id'     => $positionId,
                'state'  => $position->getState()->value,
                'signed' => $position->isSigned(),
                'tutor'  => $position->getAcademicTutor()?->getId()->toRfc4122() ?? '',
            ];

            $changed = match ($op) {
                'pending' => $this->stateChanger->transition($position, TrainingPositionState::PENDING),
                'done'    => $this->stateChanger->transition($position, TrainingPositionState::DONE),
                'draft'   => $this->stateChanger->transition($position, TrainingPositionState::DRAFT),
                'sign'    => $this->stateChanger->sign($position, true),
                'unsign'  => $this->stateChanger->sign($position, false),
                'tutor'   => $this->assignTutor($position, $teacher),
            };

            if ($changed) {
                ++$done;
                $snapshot[] = $before;
                if ($op === 'tutor') {
                    $notify[] = $position;
                }
            } else {
                ++$skipped;
            }
        }

        if ($done > 0 && ($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }
        foreach ($notify as $position) {
            $this->notifier->notifyTutorAssigned($position);
        }

        // El estado anterior viaja en el propio botón «Deshacer»; con demasiados puestos no se ofrece.
        $undo = $done > 0 && $done <= self::MAX_UNDO_BULK
            ? ['action' => 'restoreBulk', 'args' => ['snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]]
            : null;

        $this->toast($skipped > 0 ? 'stays.toast.bulk_partial' : 'stays.toast.bulk_done', [
            '%count%'   => $done,
            '%skipped%' => (string) $skipped,
        ], $undo);

        return null;
    }

    /**
     * Deshace una acción masiva: devuelve cada puesto a su estado, firma y tutor/a docente anteriores
     * (`snapshot` es la lista JSON que generó `bulk`). Respeta los permisos y la máquina de estados.
     */
    #[LiveAction]
    public function restoreBulk(#[LiveArg] string $snapshot): ?Response
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new AccessDeniedException();
        }

        $entries = json_decode($snapshot, true);
        if (!\is_array($entries)) {
            return null;
        }

        $restored = 0;
        foreach (array_slice($entries, 0, self::MAX_UNDO_BULK) as $entry) {
            if (!\is_array($entry) || !\is_string($entry['id'] ?? null)) {
                continue;
            }

            $position = $this->positions->findByIdAndStay($entry['id'], $stay);
            $target   = TrainingPositionState::tryFrom((string) ($entry['state'] ?? ''));
            if ($position === null || $target === null || !$this->isGranted(StayVoter::MANAGE_POSITION, $position)) {
                continue;
            }

            $changed = false;
            if ($position->getState() !== $target) {
                $changed = $this->stateChanger->transition($position, $target, (bool) ($entry['signed'] ?? false));
            } elseif ($position->isSigned() !== (bool) ($entry['signed'] ?? false)) {
                $changed = $this->stateChanger->sign($position, (bool) $entry['signed']);
            }

            $tutorId = (string) ($entry['tutor'] ?? '');
            if (($position->getAcademicTutor()?->getId()->toRfc4122() ?? '') !== $tutorId) {
                $tutor = $tutorId !== '' ? $this->teachers->findById($tutorId) : null;
                if ($tutorId === '' || $tutor !== null) {
                    $position->setAcademicTutor($tutor);
                    $changed = true;
                }
            }

            if ($changed) {
                ++$restored;
            }
        }

        if ($restored > 0 && ($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        $this->toast('stays.toast.bulk_undone', ['%count%' => $restored]);

        return null;
    }

    private function assignTutor(TrainingPosition $position, ?Teacher $teacher): bool
    {
        if ($teacher === null || $position->getStudent() === null || $position->getAcademicTutor() === $teacher) {
            return false;
        }
        $position->setAcademicTutor($teacher);

        return true;
    }

    private function studentLabel(TrainingPosition $position): string
    {
        $student = $position->getStudent();

        return $student !== null
            ? $student->getName()->getFirstName() . ' ' . $student->getName()->getLastName()
            : '—';
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

    /**
     * Guarda las horas dualizadas de un docente en un grupo de la estancia (ajuste «Calcular
     * asignaciones por docente»). Un valor vacío o 0 elimina el dato.
     */
    #[LiveAction]
    public function setTeacherHours(#[LiveArg] string $groupId, #[LiveArg] string $teacherId, #[LiveArg] string $hours): ?Response
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null || !$stay->isCalculateByTeacher() || !$this->isGranted(StayVoter::MANAGE, $stay)) {
            throw new AccessDeniedException();
        }

        // El grupo debe tener estudiantes en la estancia y el docente impartir en él.
        $group = null;
        foreach ($this->groups->findByStayWithStudents($stay) as $candidate) {
            if ($candidate->getId()->toRfc4122() === $groupId) {
                $group = $candidate;
                break;
            }
        }
        $teacher = $this->teachers->findById($teacherId);
        if ($group === null || $teacher === null || !$group->getTeachers()->contains($teacher)) {
            return null;
        }

        $value = is_numeric($hours) ? max(0, min(9999, (int) $hours)) : 0;
        $row   = $this->teacherHours->findOne($stay, $group, $teacher);

        if ($value === 0) {
            if ($row !== null) {
                $this->em->remove($row);
            }
        } elseif ($row !== null) {
            $row->setHours($value);
        } else {
            $this->em->persist(new StayTeacherHours($stay, $group, $teacher, $value));
        }

        if (($conflict = $this->flushWithRealtime($stay)) !== null) {
            return $conflict;
        }

        $this->toast('stays.toast.teacher_hours_set', [
            '%teacher%' => $teacher->getName()->getFirstName() . ' ' . $teacher->getName()->getLastName(),
            '%group%'   => $group->getName(),
            '%count%'   => $value,
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

    /**
     * @param array<string, string|int>                                 $params
     * @param array{action: string, args: array<string, scalar>}|null   $undo   acción en vivo que revierte el cambio
     */
    private function toast(string $key, array $params, ?array $undo = null): void
    {
        $message = $this->translator->trans($key, $params, 'stays');
        $this->addFlash('live_toast', $undo === null ? $message : ['message' => $message, 'undo' => $undo]);
    }
}
