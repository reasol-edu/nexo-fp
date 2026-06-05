<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
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
