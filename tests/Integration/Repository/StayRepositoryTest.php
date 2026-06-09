<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Entity\Workcenter;
use App\Repository\StayRepository;
use App\Tests\Integration\RepositoryTestCase;

class StayRepositoryTest extends RepositoryTestCase
{
    private StayRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var StayRepository $repo */
        $repo       = self::getContainer()->get(StayRepository::class);
        $this->repo = $repo;
    }

    // ── findById ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsStay(): void
    {
        [, , $stay] = $this->makeChain('41000001');

        $result = $this->repo->findById($stay->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($stay->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByIdReturnsNullForNonExistentId(): void
    {
        self::assertNull($this->repo->findById('00000000-0000-0000-0000-000000000000'));
    }

    // ── existsByNameAndYear ───────────────────────────────────────────────────

    public function testExistsByNameAndYearReturnsTrueWhenNameExists(): void
    {
        [$year, , $stay] = $this->makeChain('41000002');

        self::assertTrue($this->repo->existsByNameAndYear($stay->getName(), $year));
    }

    public function testExistsByNameAndYearReturnsFalseWhenNameNotFound(): void
    {
        [$year] = $this->makeChain('41000003');

        self::assertFalse($this->repo->existsByNameAndYear('Nombre inexistente', $year));
    }

    public function testExistsByNameAndYearExcludesGivenStay(): void
    {
        [$year, , $stay] = $this->makeChain('41000004');

        // Excluding itself should return false (no other stay with the same name exists)
        self::assertFalse($this->repo->existsByNameAndYear($stay->getName(), $year, $stay));
    }

    // ── createByCentreFilteredQuery ───────────────────────────────────────────

    public function testCreateByCentreFilteredQueryReturnsStaysForYear(): void
    {
        [$yearA, , $stayA] = $this->makeChain('41000005');
        [$yearB, , $stayB] = $this->makeChain('41000006');

        $resultsA = $this->repo->createByCentreFilteredQuery($yearA)->getResult();

        self::assertCount(1, $resultsA);
        self::assertSame($stayA->getId()->toRfc4122(), $resultsA[0]->getId()->toRfc4122());
    }

    public function testCreateByCentreFilteredQueryFiltersSearchByStayName(): void
    {
        [$year, $prog] = $this->makeChain('41000007');
        $fam    = $prog->getProfessionalFamily();
        $stayB  = $this->makeStay($year, $prog, 'FCT Informatica B');
        $this->persist($stayB);

        $results = $this->repo->createByCentreFilteredQuery($year, 'Informatica B')->getResult();

        self::assertCount(1, $results);
        self::assertSame('FCT Informatica B', $results[0]->getName());
    }

    public function testCreateByCentreFilteredQueryFiltersByFamilyId(): void
    {
        [$year, $prog] = $this->makeChain('41000008');
        $centre = $prog->getAcademicYear()->getEducationalCentre();
        $famB   = (new ProfessionalFamily())->setName('Sanidad')->setAcademicYear($year);
        $progB  = (new Programme())->setName('Enfermeria')->setAcademicYear($year)->setProfessionalFamily($famB);
        $stayB  = $this->makeStay($year, $progB, 'FCT Sanidad');
        $this->persist($famB, $progB, $stayB);

        $famAId = $prog->getProfessionalFamily()->getId()->toRfc4122();

        $results = $this->repo->createByCentreFilteredQuery($year, '', $famAId)->getResult();

        self::assertCount(1, $results);
        self::assertSame($prog->getProfessionalFamily()->getName(), $results[0]->getProgramme()->getProfessionalFamily()->getName());
    }

    public function testCreateByCentreFilteredQueryFiltersByProgrammeId(): void
    {
        [$year, $progA] = $this->makeChain('41000009');
        $famA  = $progA->getProfessionalFamily();
        $progB = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($famA);
        $stayB = $this->makeStay($year, $progB, 'FCT DAW');
        $this->persist($progB, $stayB);

        $results = $this->repo->createByCentreFilteredQuery(
            $year,
            '',
            '',
            $progA->getId()->toRfc4122()
        )->getResult();

        self::assertCount(1, $results);
        self::assertSame($progA->getName(), $results[0]->getProgramme()->getName());
    }

    public function testCreateByCentreFilteredQueryWithEmptyPeriodsReturnsNothing(): void
    {
        [$year] = $this->makeChain('41000010');

        $results = $this->repo->createByCentreFilteredQuery($year, '', '', '', [])->getResult();

        self::assertCount(0, $results);
    }

    public function testCreateByCentreFilteredQueryFiltersCurrentPeriodOnly(): void
    {
        [$year, $prog] = $this->makeChain('41000011');

        $past    = $this->makeStay($year, $prog, 'FCT Past',    '2025-01-01', '2025-06-30');
        $future  = $this->makeStay($year, $prog, 'FCT Future',  '2027-01-01', '2027-06-30');
        $this->persist($past, $future);

        $results = $this->repo->createByCentreFilteredQuery($year, '', '', '', ['current'])->getResult();

        // The first stay from makeChain starts 2026-03-01 and ends 2026-06-30;
        // today is 2026-06-05 so it is still current
        self::assertCount(1, $results);
    }

    // ── createByCentreFilteredQuery: filtrado por rol de viewer ──────────────

    public function testLiaisonSeesOnlyStaysWhereCompanyHasPositions(): void
    {
        [$year, $prog] = $this->makeChain('41000020');
        $centre = $prog->getAcademicYear()->getEducationalCentre();

        $stayWith    = $this->makeStay($year, $prog, 'FCT Con Puestos');
        $stayWithout = $this->makeStay($year, $prog, 'FCT Sin Puestos');
        $company     = $this->makeCompany($centre, 'Empresa Enlace S.L.');
        $workcenter  = $this->makeWorkcenter($company, 'Sede');
        $position    = (new TrainingPosition())->setStay($stayWith)->setWorkcenter($workcenter)
            ->setStartDate(new \DateTimeImmutable('2026-03-01'))
            ->setEndDate(new \DateTimeImmutable('2026-06-30'));
        $liaison = (new Teacher(new PersonName('Ana', 'Enlace')))->setUsername('liaison.filter.1');

        $this->persist($stayWith, $stayWithout, $company, $workcenter, $position, $liaison);
        $company->addLiaison($liaison);
        $this->flush();

        $results = $this->repo->createByCentreFilteredQuery($year, viewer: $liaison)->getResult();

        self::assertCount(1, $results);
        self::assertSame($stayWith->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testLiaisonSeesNoStaysWhenCompanyHasNoPositionsAnywhere(): void
    {
        [$year, $prog] = $this->makeChain('41000021');
        $centre = $prog->getAcademicYear()->getEducationalCentre();

        $company = $this->makeCompany($centre, 'Empresa Sin Puestos S.L.');
        $liaison = (new Teacher(new PersonName('Pedro', 'Enlace')))->setUsername('liaison.filter.2');

        $this->persist($company, $liaison);
        $company->addLiaison($liaison);
        $this->flush();

        $results = $this->repo->createByCentreFilteredQuery($year, viewer: $liaison)->getResult();

        self::assertCount(0, $results);
    }

    public function testLiaisonSeesStaysFromMultipleStaysWhereCompanyHasPositions(): void
    {
        [$year, $prog] = $this->makeChain('41000022');
        $centre = $prog->getAcademicYear()->getEducationalCentre();

        $stayA      = $this->makeStay($year, $prog, 'FCT A');
        $stayB      = $this->makeStay($year, $prog, 'FCT B');
        $stayC      = $this->makeStay($year, $prog, 'FCT C Sin Puestos');
        $company    = $this->makeCompany($centre, 'Empresa Multi S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede Multi');
        $posA       = (new TrainingPosition())->setStay($stayA)->setWorkcenter($workcenter)
            ->setStartDate(new \DateTimeImmutable('2026-03-01'))
            ->setEndDate(new \DateTimeImmutable('2026-06-30'));
        $posB       = (new TrainingPosition())->setStay($stayB)->setWorkcenter($workcenter)
            ->setStartDate(new \DateTimeImmutable('2026-03-01'))
            ->setEndDate(new \DateTimeImmutable('2026-06-30'));
        $liaison    = (new Teacher(new PersonName('Maria', 'Enlace')))->setUsername('liaison.filter.3');

        $this->persist($stayA, $stayB, $stayC, $company, $workcenter, $posA, $posB, $liaison);
        $company->addLiaison($liaison);
        $this->flush();

        $results = $this->repo->createByCentreFilteredQuery($year, viewer: $liaison)->getResult();

        self::assertCount(2, $results);
        $ids = array_map(fn ($s) => $s->getId()->toRfc4122(), $results);
        self::assertContains($stayA->getId()->toRfc4122(), $ids);
        self::assertContains($stayB->getId()->toRfc4122(), $ids);
    }

    public function testNonLiaisonTeacherSeesNoStaysWithoutRoleInProgramme(): void
    {
        [$year, $prog] = $this->makeChain('41000023');

        $teacher = (new Teacher(new PersonName('Carlos', 'Sin Rol')))->setUsername('teacher.norole');
        $this->persist($teacher);

        $results = $this->repo->createByCentreFilteredQuery($year, viewer: $teacher)->getResult();

        self::assertCount(0, $results);
    }

    // ── findStatsForStays ─────────────────────────────────────────────────────

    public function testFindStatsForStaysReturnsEmptyForEmptyInput(): void
    {
        self::assertSame([], $this->repo->findStatsForStays([]));
    }

    public function testFindStatsForStaysReturnsZerosForStayWithNoPositions(): void
    {
        [, , $stay] = $this->makeChain('41000012');

        $stats = $this->repo->findStatsForStays([$stay]);

        $id = $stay->getId()->toRfc4122();
        self::assertArrayHasKey($id, $stats);
        self::assertSame(0, $stats[$id]['total_positions']);
        self::assertSame(0, $stats[$id]['occupied']);
        self::assertSame(0, $stats[$id]['free']);
        self::assertSame(0, $stats[$id]['signed']);
    }

    public function testFindStatsForStaysAggregatesPositionStats(): void
    {
        [$year, $prog, $stay] = $this->makeChain('41000013');
        $centre = $prog->getAcademicYear()->getEducationalCentre();

        $company    = $this->makeCompany($centre, 'Empresa S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Oficina');
        $this->persist($company, $workcenter);

        // 1 signed DONE position with workcenter
        $p1 = (new TrainingPosition())
            ->setStay($stay)
            ->setState(TrainingPositionState::DONE)
            ->setSigned(true)
            ->setWorkcenter($workcenter);

        // 1 occupied (PENDING) position
        $p2 = (new TrainingPosition())
            ->setStay($stay)
            ->setState(TrainingPositionState::PENDING);

        // 1 free DRAFT position
        $p3 = (new TrainingPosition())
            ->setStay($stay)
            ->setState(TrainingPositionState::DRAFT);

        $this->persist($p1, $p2, $p3);

        $stats = $this->repo->findStatsForStays([$stay]);

        $id = $stay->getId()->toRfc4122();
        self::assertSame(3, $stats[$id]['total_positions']);
        self::assertSame(1, $stats[$id]['signed']);
        self::assertSame(1, $stats[$id]['state_done']);
        self::assertSame(1, $stats[$id]['state_pending']);
        self::assertSame(1, $stats[$id]['state_draft']);
        self::assertSame(1, $stats[$id]['companies']);
    }

    // ── findDashboardStats: filtrado por viewer ───────────────────────────────

    public function testFindDashboardStatsWithNullViewerReturnsAllStays(): void
    {
        [$year, $prog] = $this->makeChain('41000030');
        $fam  = $prog->getProfessionalFamily();
        $stay2 = $this->makeStay($year, $prog, 'FCT DAM B');
        $this->persist($stay2);

        $stats = $this->repo->findDashboardStats($year, null);

        self::assertSame(2, $stats['total_stays']);
    }

    public function testFindDashboardStatsWithAdminViewerReturnsAllStays(): void
    {
        [$year, $prog] = $this->makeChain('41000031');
        $stay2  = $this->makeStay($year, $prog, 'FCT DAM B');
        $admin  = (new Teacher(new PersonName('Admin', 'Global')))->setUsername('admin.dash.1')->setAdmin(true);
        $this->persist($stay2, $admin);

        $stats = $this->repo->findDashboardStats($year, $admin);

        self::assertSame(2, $stats['total_stays']);
    }

    public function testFindDashboardStatsFiltersStaysByGroupTutor(): void
    {
        [$year, $progA, $stayA] = $this->makeChain('41000032');
        $fam   = $progA->getProfessionalFamily();
        $progB = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayB = $this->makeStay($year, $progB, 'FCT DAW');
        $tutor = (new Teacher(new PersonName('Tu', 'Tor')))->setUsername('tutor.dash.1');
        $level = (new ProgrammeYear())->setName('1º')->setProgramme($progA);
        $group = (new Group())->setName('DAM1A')->setProgrammeYear($level)->addTutor($tutor);
        $this->persist($progB, $stayB, $tutor, $level, $group);

        $stats = $this->repo->findDashboardStats($year, $tutor);

        self::assertSame(1, $stats['total_stays']);
    }

    public function testFindDashboardStatsFiltersPositionsByGroupTutor(): void
    {
        [$year, $progA, $stayA] = $this->makeChain('41000033');
        $centre = $progA->getAcademicYear()->getEducationalCentre();
        $fam    = $progA->getProfessionalFamily();
        $progB  = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayB  = $this->makeStay($year, $progB, 'FCT DAW');
        $tutor  = (new Teacher(new PersonName('Tu', 'Tor')))->setUsername('tutor.dash.2');
        $level  = (new ProgrammeYear())->setName('1º')->setProgramme($progA);
        $group  = (new Group())->setName('DAM1A')->setProgrammeYear($level)->addTutor($tutor);
        $company    = $this->makeCompany($centre, 'Empresa Dash S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Oficina');
        $posA1 = (new TrainingPosition())->setStay($stayA)->setWorkcenter($workcenter);
        $posA2 = (new TrainingPosition())->setStay($stayA)->setWorkcenter($workcenter);
        $posB1 = (new TrainingPosition())->setStay($stayB)->setWorkcenter($workcenter);
        $this->persist($progB, $stayB, $tutor, $level, $group, $company, $workcenter, $posA1, $posA2, $posB1);

        $stats = $this->repo->findDashboardStats($year, $tutor);

        self::assertSame(1, $stats['total_stays']);
        self::assertSame(2, $stats['total_positions']);
    }

    public function testFindDashboardStatsReturnsZerosForUnrelatedTeacher(): void
    {
        [$year] = $this->makeChain('41000034');
        $outsider = (new Teacher(new PersonName('Out', 'Sider')))->setUsername('outsider.dash.1');
        $this->persist($outsider);

        $stats = $this->repo->findDashboardStats($year, $outsider);

        self::assertSame(0, $stats['total_stays']);
        self::assertSame(0, $stats['total_positions']);
    }

    public function testFindDashboardStatsFiltersByFamilyHead(): void
    {
        $centre  = (new EducationalCentre())->setCode('41000037')->setName('IES 41000037')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $head    = (new Teacher(new PersonName('Head', 'Fam')))->setUsername('head.dash.1');
        $famA    = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year)->setHead($head);
        $famB    = (new ProfessionalFamily())->setName('Sanidad')->setAcademicYear($year);
        $progA   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($famA);
        $progB   = (new Programme())->setName('Enfermeria')->setAcademicYear($year)->setProfessionalFamily($famB);
        $stayA   = $this->makeStay($year, $progA, 'FCT DAM');
        $stayB   = $this->makeStay($year, $progB, 'FCT Enfermeria');
        $this->persist($centre, $year, $head, $famA, $famB, $progA, $progB, $stayA, $stayB);

        $stats = $this->repo->findDashboardStats($year, $head);

        self::assertSame(1, $stats['total_stays']);
    }

    public function testFindDashboardStatsFiltersByProgrammeCoordinator(): void
    {
        $centre  = (new EducationalCentre())->setCode('41000038')->setName('IES 41000038')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $coord   = (new Teacher(new PersonName('Co', 'Ord')))->setUsername('coord.dash.1');
        $fam     = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $progA   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $progB   = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayA   = $this->makeStay($year, $progA, 'FCT DAM');
        $stayB   = $this->makeStay($year, $progB, 'FCT DAW');
        $this->persist($centre, $year, $coord, $fam, $progA, $progB, $stayA, $stayB);
        $progA->addCoordinator($coord);
        $this->flush();

        $stats = $this->repo->findDashboardStats($year, $coord);

        self::assertSame(1, $stats['total_stays']);
    }

    public function testFindDashboardStatsFiltersByCentreAdmin(): void
    {
        $centre  = (new EducationalCentre())->setCode('41000039')->setName('IES 41000039')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $admin   = (new Teacher(new PersonName('Centre', 'Admin')))->setUsername('centre.admin.dash.1');
        $fam     = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $progA   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $progB   = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayA   = $this->makeStay($year, $progA, 'FCT DAM');
        $stayB   = $this->makeStay($year, $progB, 'FCT DAW');
        $this->persist($centre, $year, $admin, $fam, $progA, $progB, $stayA, $stayB);
        $centre->addAdmin($admin);
        $this->flush();

        $stats = $this->repo->findDashboardStats($year, $admin);

        self::assertSame(2, $stats['total_stays']);
    }

    public function testFindDashboardStatsFiltersByCompanyLiaison(): void
    {
        $centre     = (new EducationalCentre())->setCode('41000040')->setName('IES 41000040')->setCity('Sevilla');
        $year       = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $fam        = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog       = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayWith   = $this->makeStay($year, $prog, 'FCT Con Puestos');
        $stayWithout = $this->makeStay($year, $prog, 'FCT Sin Puestos');
        $liaison    = (new Teacher(new PersonName('En', 'Lace')))->setUsername('liaison.dash.1');
        $company    = $this->makeCompany($centre, 'Empresa Dash Enlace S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede');
        $position   = (new TrainingPosition())->setStay($stayWith)->setWorkcenter($workcenter);
        $this->persist($centre, $year, $fam, $prog, $stayWith, $stayWithout, $liaison, $company, $workcenter, $position);
        $company->addLiaison($liaison);
        $this->flush();

        $stats = $this->repo->findDashboardStats($year, $liaison);

        self::assertSame(1, $stats['total_stays']);
    }

    public function testFindDashboardStatsFiltersByGroupTeacher(): void
    {
        $centre  = (new EducationalCentre())->setCode('41000041')->setName('IES 41000041')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $teacher = (new Teacher(new PersonName('Do', 'Cente')))->setUsername('teacher.dash.1');
        $fam     = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $progA   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $progB   = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayA   = $this->makeStay($year, $progA, 'FCT DAM');
        $stayB   = $this->makeStay($year, $progB, 'FCT DAW');
        $level   = (new ProgrammeYear())->setName('1º')->setProgramme($progA);
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($level);
        $this->persist($centre, $year, $teacher, $fam, $progA, $progB, $stayA, $stayB, $level, $group);
        $group->addTeacher($teacher);
        $this->flush();

        $stats = $this->repo->findDashboardStats($year, $teacher);

        self::assertSame(1, $stats['total_stays']);
    }

    // ── findActiveAndUpcoming: filtrado por viewer ────────────────────────────

    public function testFindActiveAndUpcomingWithNullViewerReturnsAll(): void
    {
        [$year, $prog] = $this->makeChain('41000035');
        $stay2 = $this->makeStay($year, $prog, 'FCT DAM B');
        $this->persist($stay2);

        $results = $this->repo->findActiveAndUpcoming($year, null);

        self::assertCount(2, $results);
    }

    public function testFindActiveAndUpcomingFiltersForGroupTutor(): void
    {
        [$year, $progA] = $this->makeChain('41000036');
        $fam   = $progA->getProfessionalFamily();
        $progB = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayB = $this->makeStay($year, $progB, 'FCT DAW');
        $tutor = (new Teacher(new PersonName('Tu', 'Tor')))->setUsername('tutor.upcoming.1');
        $level = (new ProgrammeYear())->setName('1º')->setProgramme($progA);
        $group = (new Group())->setName('DAM1A')->setProgrammeYear($level)->addTutor($tutor);
        $this->persist($progB, $stayB, $tutor, $level, $group);

        $results = $this->repo->findActiveAndUpcoming($year, $tutor);

        self::assertCount(1, $results);
        self::assertSame($progA->getName(), $results[0]->getProgramme()->getName());
    }

    public function testFindActiveAndUpcomingFiltersByFamilyHead(): void
    {
        $centre = (new EducationalCentre())->setCode('41000042')->setName('IES 41000042')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $head   = (new Teacher(new PersonName('Head', 'Fam')))->setUsername('head.upcoming.1');
        $famA   = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year)->setHead($head);
        $famB   = (new ProfessionalFamily())->setName('Sanidad')->setAcademicYear($year);
        $progA  = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($famA);
        $progB  = (new Programme())->setName('Enfermeria')->setAcademicYear($year)->setProfessionalFamily($famB);
        $stayA  = $this->makeStay($year, $progA, 'FCT DAM');
        $stayB  = $this->makeStay($year, $progB, 'FCT Enfermeria');
        $this->persist($centre, $year, $head, $famA, $famB, $progA, $progB, $stayA, $stayB);

        $results = $this->repo->findActiveAndUpcoming($year, $head);

        self::assertCount(1, $results);
        self::assertSame($progA->getName(), $results[0]->getProgramme()->getName());
    }

    public function testFindActiveAndUpcomingFiltersByProgrammeCoordinator(): void
    {
        $centre = (new EducationalCentre())->setCode('41000043')->setName('IES 41000043')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $coord  = (new Teacher(new PersonName('Co', 'Ord')))->setUsername('coord.upcoming.1');
        $fam    = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $progA  = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $progB  = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayA  = $this->makeStay($year, $progA, 'FCT DAM');
        $stayB  = $this->makeStay($year, $progB, 'FCT DAW');
        $this->persist($centre, $year, $coord, $fam, $progA, $progB, $stayA, $stayB);
        $progA->addCoordinator($coord);
        $this->flush();

        $results = $this->repo->findActiveAndUpcoming($year, $coord);

        self::assertCount(1, $results);
        self::assertSame($progA->getName(), $results[0]->getProgramme()->getName());
    }

    public function testFindActiveAndUpcomingFiltersByCentreAdmin(): void
    {
        $centre = (new EducationalCentre())->setCode('41000044')->setName('IES 41000044')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $admin  = (new Teacher(new PersonName('Centre', 'Admin')))->setUsername('centre.admin.upcoming.1');
        $fam    = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $progA  = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $progB  = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayA  = $this->makeStay($year, $progA, 'FCT DAM');
        $stayB  = $this->makeStay($year, $progB, 'FCT DAW');
        $this->persist($centre, $year, $admin, $fam, $progA, $progB, $stayA, $stayB);
        $centre->addAdmin($admin);
        $this->flush();

        $results = $this->repo->findActiveAndUpcoming($year, $admin);

        self::assertCount(2, $results);
    }

    public function testFindActiveAndUpcomingFiltersByCompanyLiaison(): void
    {
        $centre     = (new EducationalCentre())->setCode('41000045')->setName('IES 41000045')->setCity('Sevilla');
        $year       = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $fam        = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog       = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stayWith   = $this->makeStay($year, $prog, 'FCT Con Puestos');
        $stayWithout = $this->makeStay($year, $prog, 'FCT Sin Puestos');
        $liaison    = (new Teacher(new PersonName('En', 'Lace')))->setUsername('liaison.upcoming.1');
        $company    = $this->makeCompany($centre, 'Empresa Upcoming Enlace S.L.');
        $workcenter = $this->makeWorkcenter($company, 'Sede');
        $position   = (new TrainingPosition())->setStay($stayWith)->setWorkcenter($workcenter);
        $this->persist($centre, $year, $fam, $prog, $stayWith, $stayWithout, $liaison, $company, $workcenter, $position);
        $company->addLiaison($liaison);
        $this->flush();

        $results = $this->repo->findActiveAndUpcoming($year, $liaison);

        self::assertCount(1, $results);
        self::assertSame($stayWith->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Builds and persists Centre → Year → Family → Programme → Stay.
     *
     * @return array{AcademicYear, Programme, Stay}
     */
    private function makeChain(string $centreCode): array
    {
        $centre  = (new EducationalCentre())->setCode($centreCode)->setName('IES ' . $centreCode)->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $stay    = $this->makeStay($year, $prog, 'FCT DAM ' . $centreCode);
        $this->persist($centre, $year, $family, $prog, $stay);
        return [$year, $prog, $stay];
    }

    private function makeStay(
        AcademicYear $year,
        Programme $programme,
        string $name,
        string $start = '2026-03-01',
        string $end = '2026-06-30',
    ): Stay {
        return (new Stay())
            ->setName($name)
            ->setAcademicYear($year)
            ->setProgramme($programme)
            ->setStartDate(new \DateTimeImmutable($start))
            ->setEndDate(new \DateTimeImmutable($end));
    }

    private function makeCompany(EducationalCentre $centre, string $name): Company
    {
        return (new Company())
            ->setName($name)
            ->setVatNumber('B' . substr(md5($name), 0, 8))
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }

    private function makeWorkcenter(Company $company, string $name): Workcenter
    {
        return (new Workcenter())->setName($name)->setCity('Sevilla')->setCompany($company);
    }
}
