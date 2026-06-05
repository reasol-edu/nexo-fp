<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Repository\AcademicYearRepository;
use App\Tests\Integration\RepositoryTestCase;

class AcademicYearRepositoryTest extends RepositoryTestCase
{
    private AcademicYearRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var AcademicYearRepository $repo */
        $repo       = self::getContainer()->get(AcademicYearRepository::class);
        $this->repo = $repo;
    }

    // ── findByCentreOrderedByName ─────────────────────────────────────────────

    public function testFindByCentreOrderedByNameReturnsYearsInDescendingOrder(): void
    {
        $centre = $this->makeCentre('41000001');
        $y1     = $this->makeYear($centre, '2022-2023');
        $y2     = $this->makeYear($centre, '2024-2025');
        $y3     = $this->makeYear($centre, '2023-2024');
        $this->persist($centre, $y1, $y2, $y3);

        $results = $this->repo->findByCentreOrderedByName($centre);

        self::assertCount(3, $results);
        self::assertSame('2024-2025', $results[0]->getName());
        self::assertSame('2023-2024', $results[1]->getName());
        self::assertSame('2022-2023', $results[2]->getName());
    }

    public function testFindByCentreOrderedByNameReturnsOnlyYearsForGivenCentre(): void
    {
        $centreA = $this->makeCentre('41000002');
        $centreB = $this->makeCentre('41000003');
        $yearA   = $this->makeYear($centreA, '2024-2025');
        $yearB   = $this->makeYear($centreB, '2023-2024');
        $this->persist($centreA, $centreB, $yearA, $yearB);

        $results = $this->repo->findByCentreOrderedByName($centreA);

        self::assertCount(1, $results);
        self::assertSame('2024-2025', $results[0]->getName());
    }

    public function testFindByCentreOrderedByNameReturnsEmptyForCentreWithNoYears(): void
    {
        $centre = $this->makeCentre('41000004');
        $this->persist($centre);

        self::assertCount(0, $this->repo->findByCentreOrderedByName($centre));
    }

    // ── findByCentreAndId ─────────────────────────────────────────────────────

    public function testFindByCentreAndIdReturnsYear(): void
    {
        $centre = $this->makeCentre('41000005');
        $year   = $this->makeYear($centre, '2024-2025');
        $this->persist($centre, $year);

        $result = $this->repo->findByCentreAndId($centre, $year->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($year->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByCentreAndIdReturnsNullForDifferentCentre(): void
    {
        $centreA = $this->makeCentre('41000006');
        $centreB = $this->makeCentre('41000007');
        $year    = $this->makeYear($centreA, '2024-2025');
        $this->persist($centreA, $centreB, $year);

        self::assertNull($this->repo->findByCentreAndId($centreB, $year->getId()->toRfc4122()));
    }

    public function testFindByCentreAndIdReturnsNullForNonExistentId(): void
    {
        $centre = $this->makeCentre('41000008');
        $this->persist($centre);

        self::assertNull($this->repo->findByCentreAndId($centre, '00000000-0000-0000-0000-000000000000'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())
            ->setCode($code)
            ->setName('IES ' . $code)
            ->setCity('Sevilla');
    }

    private function makeYear(EducationalCentre $centre, string $name): AcademicYear
    {
        return (new AcademicYear())
            ->setName($name)
            ->setEducationalCentre($centre);
    }
}
