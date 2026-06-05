<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Repository\CompanyRepository;
use App\Repository\GroupRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\StayRepository;
use App\Repository\TeacherRepository;
use App\Repository\TrainingPositionRepository;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
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
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenant,
        private readonly CompanyRepository $companies,
        private readonly ProgrammeRepository $programmes,
    ) {}

    /** @return array<string, mixed> */
    public function getData(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stay = $this->stays->findById($this->stayId);
        if ($stay === null) {
            throw new \RuntimeException('Stay not found: ' . $this->stayId);
        }

        $canManage    = $this->computeCanManage($stay);
        $allPositions = $this->positions->findByStayOrdered($stay);

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

        $workersByCompanyId = [];
        foreach ($studentPositionMap as $tp) {
            if ($tp->getWorkplaceMentor() === null && $tp->getWorkcenter() !== null) {
                $company = $tp->getWorkcenter()->getCompany();
                $cid     = $company->getId()->toRfc4122();
                if (!isset($workersByCompanyId[$cid])) {
                    $workers = $company->getWorkers()->toArray();
                    usort($workers, fn ($a, $b) =>
                        $a->getName()->getLastName() <=> $b->getName()->getLastName()
                        ?: $a->getName()->getFirstName() <=> $b->getName()->getFirstName()
                    );
                    $workersByCompanyId[$cid] = $workers;
                }
            }
        }

        $statsMap = $this->stays->findStatsForStays([$stay]);
        $stats    = $statsMap[$stay->getId()->toRfc4122()] ?? [];

        $this->cache = [
            'stay'                             => $stay,
            'can_manage'                       => $canManage,
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

    private function computeCanManage(Stay $stay): bool
    {
        $centre = $this->tenant->getSelectedCentre();
        if ($centre === null) {
            return false;
        }

        /** @var Teacher|null $teacher */
        $teacher = $this->getUser();
        if ($teacher === null) {
            return false;
        }

        if ($teacher->isAdmin()) {
            return true;
        }

        $tid = $teacher->getId()->toRfc4122();

        foreach ($centre->getAdmins() as $admin) {
            if ($admin->getId()->toRfc4122() === $tid) {
                return true;
            }
        }

        if ($this->programmes->isCoordinatorOf($teacher, $stay->getProgramme())) {
            return true;
        }

        if ($this->companies->hasLiaisonInCentre($teacher, $centre)) {
            return true;
        }

        return false;
    }

    #[LiveAction]
    public function assignPosition(#[LiveArg] string $studentId, #[LiveArg] string $positionId): void
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null || !$this->computeCanManage($stay)) {
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
            return;
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null || $position->getStudent() !== null) {
            return;
        }

        $position->setStudent($student);
        $this->em->flush();
    }

    #[LiveAction]
    public function unassignPosition(#[LiveArg] string $positionId): void
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null || !$this->computeCanManage($stay)) {
            throw new AccessDeniedException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null
            || $position->getStudent() === null
            || $position->getState() !== TrainingPositionState::DRAFT
        ) {
            return;
        }

        $position->setStudent(null);
        $this->em->flush();
    }

    #[LiveAction]
    public function setAcademicTutor(#[LiveArg] string $positionId, #[LiveArg] string $teacherId): void
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null || !$this->computeCanManage($stay)) {
            throw new AccessDeniedException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null || $position->getStudent() === null) {
            return;
        }

        $teacher = $this->teachers->findById($teacherId);
        if ($teacher === null) {
            return;
        }

        $position->setAcademicTutor($teacher);
        $this->em->flush();
    }

    #[LiveAction]
    public function setWorkplaceMentor(#[LiveArg] string $positionId, #[LiveArg] string $workerId): void
    {
        $stay = $this->stays->findById($this->stayId);
        if ($stay === null || !$this->computeCanManage($stay)) {
            throw new AccessDeniedException();
        }

        $position = $this->positions->findByIdAndStay($positionId, $stay);
        if ($position === null || $position->getStudent() === null || $position->getWorkcenter() === null) {
            return;
        }

        $mentor = null;
        foreach ($position->getWorkcenter()->getCompany()->getWorkers() as $w) {
            if ($w->getId()->toRfc4122() === $workerId) {
                $mentor = $w;
                break;
            }
        }
        if ($mentor === null) {
            return;
        }

        $position->setWorkplaceMentor($mentor);
        $this->em->flush();
    }
}
