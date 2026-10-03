<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Repository\GroupRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;
use App\Repository\StayRepository;
use App\Repository\TeacherRepository;
use App\Repository\TrainingPositionRepository;
use App\Tests\Integration\RepositoryTestCase;

/**
 * Consultas sobre estancias que reúnen varias enseñanzas (y a veces varias familias).
 */
class StayMultiProgrammeRepositoryTest extends RepositoryTestCase
{
    private StayRepository $stays;
    private ProgrammeRepository $programmes;
    private GroupRepository $groups;
    private TeacherRepository $teachers;
    private ProgrammeYearRepository $levels;
    private TrainingPositionRepository $positions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stays      = self::getContainer()->get(StayRepository::class);
        $this->programmes = self::getContainer()->get(ProgrammeRepository::class);
        $this->groups     = self::getContainer()->get(GroupRepository::class);
        $this->teachers   = self::getContainer()->get(TeacherRepository::class);
        $this->levels     = self::getContainer()->get(ProgrammeYearRepository::class);
        $this->positions  = self::getContainer()->get(TrainingPositionRepository::class);
    }

    // ── Listado y filtros ─────────────────────────────────────────────────────

    public function testFilteredQueryReturnsASharedStayOnlyOnce(): void
    {
        $s = $this->scenario();

        $results = $this->stays->createByCentreFilteredQuery($s['year'])->getResult();

        self::assertCount(1, $results);
    }

    public function testFilteredQueryMatchesByEitherProgrammeNameFamilyOrId(): void
    {
        $s = $this->scenario();

        foreach (['DAW', 'DAM', 'Sanitaria'] as $search) {
            self::assertCount(1, $this->stays->createByCentreFilteredQuery($s['year'], $search)->getResult(), $search);
        }
        self::assertCount(0, $this->stays->createByCentreFilteredQuery($s['year'], 'Cocina')->getResult());

        foreach ([$s['daw'], $s['dam'], $s['care']] as $programme) {
            $rows = $this->stays->createByCentreFilteredQuery($s['year'], '', '', $programme->getId()->toRfc4122())->getResult();
            self::assertCount(1, $rows, $programme->getName());
        }
        self::assertCount(1, $this->stays->createByCentreFilteredQuery($s['year'], '', $s['sanidad']->getId()->toRfc4122())->getResult());
        self::assertCount(0, $this->stays->createByCentreFilteredQuery($s['year'], '', $s['otherFamily']->getId()->toRfc4122())->getResult());
    }

    public function testViewerSeesTheStayThroughAnyOfItsProgrammes(): void
    {
        $s = $this->scenario();

        self::assertCount(1, $this->stays->createByCentreFilteredQuery($s['year'], viewer: $s['coordDaw'])->getResult());
        self::assertCount(1, $this->stays->createByCentreFilteredQuery($s['year'], viewer: $s['coordDam'])->getResult());
        self::assertCount(1, $this->stays->createByCentreFilteredQuery($s['year'], viewer: $s['head'])->getResult());
        self::assertCount(1, $this->stays->createByCentreFilteredQuery($s['year'], viewer: $s['tutorDam'])->getResult());
        self::assertCount(0, $this->stays->createByCentreFilteredQuery($s['year'], viewer: $s['outsider'])->getResult());
    }

    public function testActiveSearchAndCalendarQueriesReturnTheStayOnce(): void
    {
        $s = $this->scenario();
        $from = new \DateTimeImmutable('-60 days');
        $to   = new \DateTimeImmutable('+60 days');

        self::assertCount(1, $this->stays->findActiveAndUpcoming($s['year'], $s['coordDam']));
        self::assertCount(1, $this->stays->searchByYearForViewer($s['year'], 'DAM', $s['coordDam']));
        self::assertCount(1, $this->stays->searchByYearForViewer($s['year'], 'FFEOE'));
        self::assertCount(1, $this->stays->findOverlappingPeriod($s['year'], $from, $to, $s['coordDaw']));
        self::assertCount(0, $this->stays->findOverlappingPeriod($s['year'], $from, $to, $s['outsider']));
    }

    public function testDashboardCountersCountTheSharedStayOnce(): void
    {
        $s = $this->scenario();

        $stats = $this->stays->findDashboardStats($s['year'], $s['coordDam']);

        self::assertSame(1, $stats['total_stays']);
        self::assertSame(3, $stats['total_positions']);
        self::assertSame(2, $stats['occupied']);
    }

    public function testPaginatedListsForACoordinatorCanBeIteratedNotJustCounted(): void
    {
        $s = $this->scenario();
        $s['damPosition']->setState(TrainingPositionState::DONE)->setSigned(false);
        $this->flush();

        // Regresión: la subconsulta de límite de Doctrine fallaba al recorrer (no al contar) estas
        // consultas con los EXISTS anidados del filtro por docente.
        $stays = new \App\Pagination\Paginator($this->stays->createByCentreFilteredQuery($s['year'], viewer: $s['coordDam']), 1, 20, false);
        self::assertSame(1, $stays->getTotalItems());
        self::assertSame(['FFEOE compartida'], array_map(static fn (Stay $st): string => $st->getName(), iterator_to_array($stays->getItems())));

        $pending = new \App\Pagination\Paginator($this->positions->createPendingSignaturesQuery($s['year'], viewer: $s['coordDam']), 1, 20, false);
        self::assertSame(1, $pending->getTotalItems());
        self::assertCount(1, iterator_to_array($pending->getItems()));
    }

    // ── Recuentos por familia ─────────────────────────────────────────────────

    public function testPositionCountsFollowTheFamilyOfTheOfferedLevelsOrTheStudent(): void
    {
        $s = $this->scenario();

        $rows = [];
        foreach ($this->stays->countPositionsByFamily($s['year']) as $row) {
            $rows[$row['family_name']] = $row;
        }

        // Informática: el puesto compartido libre (DAW+DAM) y el puesto de DAM (ocupado por el alumno de DAM)
        // Sanidad: el puesto compartido libre ofertado también a Cuidados y el ocupado por la alumna de Cuidados
        self::assertSame(2, $rows['Informática']['total']);
        self::assertSame(1, $rows['Informática']['occupied']);
        self::assertSame(2, $rows['Sanidad']['total']);
        self::assertSame(1, $rows['Sanidad']['occupied']);
        self::assertSame(1, $rows['Sanidad']['signed']);
    }

    public function testStudentCountsFollowEachStudentsFamily(): void
    {
        $s = $this->scenario();

        $rows = [];
        foreach ($this->stays->countStudentsByFamilyState($s['year']) as $row) {
            $rows[$row['family_name']] = $row;
        }

        // Informática: Ana (DAW) sin puesto, Luis (DAM) con puesto en borrador
        self::assertSame(1, $rows['Informática']['unassigned']);
        self::assertSame(1, $rows['Informática']['draft']);
        // Sanidad: Eva (Cuidados) con puesto registrado y firmado
        self::assertSame(0, $rows['Sanidad']['unassigned']);
        self::assertSame(1, $rows['Sanidad']['signed']);
    }

    public function testFamilyCountsRespectTheViewerFilter(): void
    {
        $s = $this->scenario();

        // La jefatura de Informática ve la estancia (por DAW y DAM) y por tanto todas sus familias.
        $names = array_column($this->stays->countPositionsByFamily($s['year'], $s['head']), 'family_name');
        self::assertContains('Informática', $names);

        self::assertSame([], $this->stays->countPositionsByFamily($s['year'], $s['outsider']));
        self::assertSame([], $this->stays->countStudentsByFamilyState($s['year'], $s['outsider']));
    }

    // ── Repositorios de apoyo a la gestión por enseñanza ──────────────────────

    public function testFindCoordinatedAndHeadedProgrammesInStay(): void
    {
        $s = $this->scenario();

        $coordinated = $this->programmes->findCoordinatedByInStay($s['coordDam'], $s['stay']);
        self::assertSame(['DAM'], array_map(static fn (Programme $p): string => $p->getName(), $coordinated));

        $headed = array_map(static fn (Programme $p): string => $p->getName(), $this->programmes->findHeadedByInStay($s['head'], $s['stay']));
        sort($headed);
        self::assertSame(['DAM', 'DAW'], $headed);

        self::assertSame([], $this->programmes->findCoordinatedByInStay($s['outsider'], $s['stay']));
        self::assertSame([], $this->programmes->findHeadedByInStay($s['outsider'], $s['stay']));
    }

    public function testStayGroupsIncludeEveryProgrammeOfTheStayButNotOthers(): void
    {
        $s = $this->scenario();

        $names = array_map(static fn (Group $g): string => $g->getName(), $this->groups->findByStayWithStudents($s['stay']));

        self::assertSame(['CUI2A', 'DAM2A', 'DAW2A'], $names);
    }

    public function testIsTeacherInStayProgrammes(): void
    {
        $s = $this->scenario();

        self::assertTrue($this->groups->isTeacherInStayProgrammes($s['tutorDam'], $s['stay']));
        self::assertFalse($this->groups->isTeacherInStayProgrammes($s['outsider'], $s['stay']));
    }

    public function testStayTeachersCoverEveryProgramme(): void
    {
        $s = $this->scenario();

        $usernames = array_map(static fn (Teacher $t): string => $t->getUsername(), $this->teachers->findByStayProgrammesOrderedByName($s['stay']));

        self::assertContains('tutor.dam', $usernames);
        self::assertContains('tutor.daw', $usernames);
        self::assertNotContains('outsider', $usernames);
    }

    public function testStayLevelsAreGroupedByProgrammeAndLimitedToTheStay(): void
    {
        $s = $this->scenario();

        $names = array_map(static fn (ProgrammeYear $l): string => $l->getName(), $this->levels->findByStayOrderedByName($s['stay']));
        self::assertSame(['2.º CUI', '2.º DAM', '2.º DAW'], $names);

        self::assertNotNull($this->levels->findByStayAndId($s['stay'], $s['damLevel']->getId()->toRfc4122()));
        self::assertNull($this->levels->findByStayAndId($s['stay'], $s['foreignLevel']->getId()->toRfc4122()));
    }

    // ── Puestos pendientes de firma ───────────────────────────────────────────

    public function testPendingSignaturesFilterByAnyProgrammeAndRespectTheViewer(): void
    {
        $s = $this->scenario();
        $position = $s['damPosition'];
        $position->setState(TrainingPositionState::DONE)->setSigned(false);
        $this->flush();

        $count = fn (string $programmeId = '', ?Teacher $viewer = null, string $search = ''): int => count(
            $this->positions->createPendingSignaturesQuery($s['year'], $search, '', $programmeId, viewer: $viewer)->getResult()
        );

        self::assertSame(1, $count());
        self::assertSame(1, $count($s['dam']->getId()->toRfc4122()));
        self::assertSame(1, $count($s['daw']->getId()->toRfc4122()), 'El filtro por enseñanza usa todas las de la estancia');
        self::assertSame(0, $count($s['otherProgramme']->getId()->toRfc4122()));
        self::assertSame(1, $count('', $s['coordDaw']));
        self::assertSame(0, $count('', $s['outsider']));
        self::assertSame(1, $count('', null, 'DAM'));
    }

    // ── Escenario ─────────────────────────────────────────────────────────────

    /**
     * Estancia con DAW y DAM (Informática) y Cuidados (Sanidad), más una enseñanza ajena fuera de la estancia.
     * Alumnado: Ana (DAW, sin puesto), Luis (DAM, puesto borrador) y Eva (Cuidados, puesto firmado).
     * Puestos: compartido libre (DAW+DAM+Cuidados), uno de DAM (Luis) y uno de Cuidados (Eva).
     *
     * @return array<string, mixed>
     */
    private function scenario(): array
    {
        $centre = (new EducationalCentre())->setCode('41000099')->setName('IES Multi')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        $head     = (new Teacher(new PersonName('Jefa', 'Informática')))->setUsername('head.inf');
        $coordDaw = (new Teacher(new PersonName('Coord', 'Daw')))->setUsername('coord.daw');
        $coordDam = (new Teacher(new PersonName('Coord', 'Dam')))->setUsername('coord.dam');
        $tutorDaw = (new Teacher(new PersonName('Tutor', 'Daw')))->setUsername('tutor.daw');
        $tutorDam = (new Teacher(new PersonName('Tutor', 'Dam')))->setUsername('tutor.dam');
        $outsider = (new Teacher(new PersonName('Fuera', 'Fuera')))->setUsername('outsider');

        $informatica = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year)->setHead($head);
        $sanidad     = (new ProfessionalFamily())->setName('Sanidad')->setAcademicYear($year);
        $otherFamily = (new ProfessionalFamily())->setName('Hostelería')->setAcademicYear($year);

        $daw  = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($informatica)->addCoordinator($coordDaw);
        $dam  = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($informatica)->addCoordinator($coordDam);
        $care = (new Programme())->setName('Cuidados Auxiliares Sanitaria')->setAcademicYear($year)->setProfessionalFamily($sanidad);
        $otherProgramme = (new Programme())->setName('Cocina')->setAcademicYear($year)->setProfessionalFamily($otherFamily);

        $dawLevel     = (new ProgrammeYear())->setName('2.º DAW')->setProgramme($daw);
        $damLevel     = (new ProgrammeYear())->setName('2.º DAM')->setProgramme($dam);
        $careLevel    = (new ProgrammeYear())->setName('2.º CUI')->setProgramme($care);
        $foreignLevel = (new ProgrammeYear())->setName('2.º COC')->setProgramme($otherProgramme);

        $dawGroup  = (new Group())->setName('DAW2A')->setProgrammeYear($dawLevel)->addTutor($tutorDaw);
        $damGroup  = (new Group())->setName('DAM2A')->setProgrammeYear($damLevel)->addTutor($tutorDam);
        $careGroup = (new Group())->setName('CUI2A')->setProgrammeYear($careLevel);
        $cookGroup = (new Group())->setName('COC2A')->setProgrammeYear($foreignLevel);

        $ana  = (new Student(new PersonName('Ana', 'Daw')))->setStudentId('S-ANA');
        $luis = (new Student(new PersonName('Luis', 'Dam')))->setStudentId('S-LUIS');
        $eva  = (new Student(new PersonName('Eva', 'Cuidados')))->setStudentId('S-EVA');
        $dawGroup->addStudent($ana);
        $damGroup->addStudent($luis);
        $careGroup->addStudent($eva);

        $stay = (new Stay())
            ->setName('FFEOE compartida')
            ->setAcademicYear($year)
            ->addProgramme($daw)->addProgramme($dam)->addProgramme($care)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable('+30 days'))
            ->addStudent($ana)->addStudent($luis)->addStudent($eva);

        $shared      = (new TrainingPosition())->setStay($stay)->addProgrammeYear($dawLevel)->addProgrammeYear($damLevel)->addProgrammeYear($careLevel);
        $damPosition = (new TrainingPosition())->setStay($stay)->addProgrammeYear($damLevel)->setStudent($luis);
        $carePosition = (new TrainingPosition())->setStay($stay)->addProgrammeYear($careLevel)->setStudent($eva)
            ->setState(TrainingPositionState::DONE)->setSigned(true)->setSignedAt(new \DateTimeImmutable('-1 day'));

        $this->persist(
            $centre, $year, $head, $coordDaw, $coordDam, $tutorDaw, $tutorDam, $outsider,
            $informatica, $sanidad, $otherFamily, $daw, $dam, $care, $otherProgramme,
            $dawLevel, $damLevel, $careLevel, $foreignLevel, $dawGroup, $damGroup, $careGroup, $cookGroup,
            $ana, $luis, $eva, $stay, $shared, $damPosition, $carePosition,
        );

        return compact(
            'centre', 'year', 'head', 'coordDaw', 'coordDam', 'tutorDaw', 'tutorDam', 'outsider', 'sanidad', 'otherFamily',
            'daw', 'dam', 'care', 'otherProgramme', 'damLevel', 'foreignLevel', 'stay', 'shared', 'damPosition',
        );
    }
}
