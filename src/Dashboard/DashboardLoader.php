<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Dashboard\Facts\CompanyRef;
use App\Dashboard\Facts\DashboardFacts;
use App\Dashboard\Facts\GroupRef;
use App\Dashboard\Facts\LevelFacts;
use App\Dashboard\Facts\PositionFacts;
use App\Dashboard\Facts\ProgrammeRef;
use App\Dashboard\Facts\ScopeFacts;
use App\Dashboard\Facts\StayFacts;
use App\Dashboard\Facts\StudentFacts;
use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPositionState;
use App\Repository\CompanyRepository;
use App\Repository\GroupRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\StayRepository;
use App\Repository\StayTeacherHoursRepository;
use App\Repository\StudentYearProfileRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Carga los hechos del inicio con un número FIJO de consultas, sea cual sea el número de estancias,
 * puestos o estudiantes:
 *
 *  - responsabilidades de la persona: hasta 3 (enseñanzas que gestiona, grupos y empresas de enlace) y,
 *    para saber si administra el centro, la consulta del conjunto de administradores;
 *  - estancias (1), puestos (1), niveles de los puestos libres (1), estudiantes con sus grupos (1) y
 *    estudiantes del curso sin NUSS (1);
 *  - horas dualizadas (1, solo si alguna estancia tiene «Calcular asignaciones por docente»).
 *
 * Todo lo demás se calcula en memoria ({@see DashboardBuilder}).
 */
final class DashboardLoader
{
    public function __construct(
        private readonly StayRepository $stays,
        private readonly ProgrammeRepository $programmes,
        private readonly GroupRepository $groups,
        private readonly CompanyRepository $companies,
        private readonly StayTeacherHoursRepository $hours,
        private readonly StudentYearProfileRepository $profiles,
        private readonly ClockInterface $clock,
    ) {}

    public function load(EducationalCentre $centre, AcademicYear $year, Teacher $viewer): DashboardFacts
    {
        $today = $this->clock->now()->setTime(0, 0, 0);
        $scope = $this->scope($centre, $year, $viewer);

        $stayFacts = [];
        foreach ($this->stays->findForDashboard($year, $viewer) as $stay) {
            $stayFacts[] = $this->stayFacts($stay);
        }
        $stayIds = array_flip(array_map(static fn (StayFacts $s): string => $s->id, $stayFacts));
        if ($stayFacts === []) {
            return new DashboardFacts($today, $scope, [], [], []);
        }

        return new DashboardFacts(
            $today,
            $scope,
            $stayFacts,
            $this->positions($year, $viewer, $stayIds),
            $this->students($year, $viewer, $stayFacts),
            $this->hours($year, $stayFacts),
        );
    }

    private function scope(EducationalCentre $centre, AcademicYear $year, Teacher $viewer): ScopeFacts
    {
        $isAdmin = $viewer->isAdmin() || $centre->getAdmins()->contains($viewer);

        // La administración gestiona todo: no hace falta saber qué enseñanzas coordina o dirige.
        $manage = [];
        $headed = [];
        if (!$isAdmin) {
            foreach ($this->programmes->findManagedByTeacherInYear($year, $viewer) as $row) {
                $id          = (string) $row['id'];
                $manage[$id] = $row['name'];
                if ($row['headed']) {
                    $headed[] = $id;
                }
            }
        }

        $groups = array_map(
            static fn (array $r): GroupRef => new GroupRef((string) $r['id'], $r['name'], (string) $r['programme']),
            $this->groups->findTaughtByTeacherInYear($year, $viewer),
        );
        $companies = array_map(
            static fn (array $r): CompanyRef => new CompanyRef((string) $r['id'], $r['name'], $r['workers'] === 0),
            $this->companies->findLiaisonSummaryForTeacher($centre, $viewer),
        );

        return new ScopeFacts($viewer->getId()->toRfc4122(), $isAdmin, $manage, $headed, $groups, $companies);
    }

    private function stayFacts(Stay $stay): StayFacts
    {
        $programmes = [];
        foreach ($stay->getProgrammesSorted() as $programme) {
            $family       = $programme->getProfessionalFamily();
            $programmes[] = new ProgrammeRef(
                $programme->getId()->toRfc4122(),
                $programme->getName(),
                $family->getName(),
                $family->getHead()?->getId()->toRfc4122(),
            );
        }

        return new StayFacts(
            $stay->getId()->toRfc4122(),
            $stay->getName(),
            $programmes,
            $stay->getStartDate(),
            $stay->getEndDate(),
            $stay->isCalculateByTeacher(),
        );
    }

    /**
     * @param array<string, int> $stayIds
     * @return list<PositionFacts>
     */
    private function positions(AcademicYear $year, Teacher $viewer, array $stayIds): array
    {
        $levelsByPosition = [];
        foreach ($this->stays->findDashboardFreePositionLevelRows($year, $viewer) as $row) {
            $levelsByPosition[(string) $row['positionId']][] = (string) $row['level'];
        }

        $positions = [];
        foreach ($this->stays->findDashboardPositionRows($year, $viewer) as $row) {
            $stayId = (string) $row['stay'];
            if (!isset($stayIds[$stayId])) {
                continue;
            }
            $id    = (string) $row['id'];
            $state = $row['state'];

            $positions[] = new PositionFacts(
                $id,
                $stayId,
                $state instanceof TrainingPositionState ? $state : TrainingPositionState::from((string) $state),
                (bool) $row['signed'],
                $this->id($row['student']),
                $this->id($row['tutor']),
                $this->id($row['mentor']),
                $this->id($row['company']),
                $this->id($row['workcenter']),
                $row['startDate'],
                $row['endDate'],
                $row['priorityUntil'],
                $this->id($row['priorityProgramme']),
                $levelsByPosition[$id] ?? [],
            );
        }

        return $positions;
    }

    /**
     * @param list<StayFacts> $stayFacts
     * @return array<string, array<string, StudentFacts>>
     */
    private function students(AcademicYear $year, Teacher $viewer, array $stayFacts): array
    {
        $programmesOfStay = [];
        foreach ($stayFacts as $stay) {
            $programmesOfStay[$stay->id] = array_flip($stay->programmeIds());
        }

        /** @var array<string, array<string, list<LevelFacts>>> $levels */
        $levels = [];
        foreach ($this->stays->findDashboardStudentRows($year, $viewer) as $row) {
            $stayId    = (string) $row['stay'];
            $studentId = (string) $row['student'];
            if (!isset($programmesOfStay[$stayId])) {
                continue;
            }
            $levels[$stayId][$studentId] ??= [];

            // Solo cuentan los grupos de las enseñanzas de la estancia.
            $programmeId = $this->id($row['programme']);
            if ($programmeId !== null && isset($programmesOfStay[$stayId][$programmeId])) {
                $levels[$stayId][$studentId][] = new LevelFacts((string) $row['level'], $programmeId, (string) $row['grp'], (string) $row['groupName']);
            }
        }

        $missingNuss = $this->profiles->findMissingNussStudentIds($year);

        $students = [];
        foreach ($levels as $stayId => $byStudent) {
            foreach ($byStudent as $studentId => $studentLevels) {
                $students[$stayId][$studentId] = new StudentFacts($studentId, $studentLevels, isset($missingNuss[$studentId]));
            }
        }

        return $students;
    }

    /**
     * @param list<StayFacts> $stayFacts
     * @return array<string, array<string, array<string, int>>>
     */
    private function hours(AcademicYear $year, array $stayFacts): array
    {
        if (array_filter($stayFacts, static fn (StayFacts $s): bool => $s->calculateByTeacher) === []) {
            return [];
        }

        $hours = [];
        foreach ($this->hours->findRowsByYear($year) as $row) {
            $hours[(string) $row['stay']][(string) $row['grp']][(string) $row['teacher']] = (int) $row['hours'];
        }

        return $hours;
    }

    private function id(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
