<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Repository\ProgrammeYearRepository;
use App\Tests\Integration\RepositoryTestCase;

class ProgrammeYearRepositoryTest extends RepositoryTestCase
{
    private ProgrammeYearRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ProgrammeYearRepository $repo */
        $repo       = self::getContainer()->get(ProgrammeYearRepository::class);
        $this->repo = $repo;
    }

    // ── findByProgrammeOrderedByName ──────────────────────────────────────────

    public function testFindByProgrammeOrderedByNameReturnsSortedProgrammeYears(): void
    {
        $programme = $this->makeChain('41000001');
        $py1       = $this->makeProgrammeYear($programme, '2.º DAM');
        $py2       = $this->makeProgrammeYear($programme, '1.º DAM');
        $this->persist($py1, $py2);

        $results = $this->repo->findByProgrammeOrderedByName($programme);

        self::assertCount(2, $results);
        self::assertSame('1.º DAM', $results[0]->getName());
        self::assertSame('2.º DAM', $results[1]->getName());
    }

    public function testFindByProgrammeOrderedByNameExcludesOtherProgrammes(): void
    {
        $progA = $this->makeChain('41000002');
        $progB = $this->makeChain('41000003');
        $pyA   = $this->makeProgrammeYear($progA, '1.º DAM');
        $pyB   = $this->makeProgrammeYear($progB, '1.º DAW');
        $this->persist($pyA, $pyB);

        $results = $this->repo->findByProgrammeOrderedByName($progA);

        self::assertCount(1, $results);
        self::assertSame('1.º DAM', $results[0]->getName());
    }

    // ── findByProgrammeAndId ──────────────────────────────────────────────────

    public function testFindByProgrammeAndIdReturnsProgrammeYear(): void
    {
        $programme = $this->makeChain('41000004');
        $py        = $this->makeProgrammeYear($programme, '1.º DAM');
        $this->persist($py);

        $result = $this->repo->findByProgrammeAndId($programme, $py->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($py->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByProgrammeAndIdReturnsNullForDifferentProgramme(): void
    {
        $progA = $this->makeChain('41000005');
        $progB = $this->makeChain('41000006');
        $py    = $this->makeProgrammeYear($progA, '1.º DAM');
        $this->persist($py);

        self::assertNull($this->repo->findByProgrammeAndId($progB, $py->getId()->toRfc4122()));
    }

    public function testFindByProgrammeAndIdReturnsNullForNonExistentId(): void
    {
        $programme = $this->makeChain('41000007');

        self::assertNull($this->repo->findByProgrammeAndId($programme, '00000000-0000-0000-0000-000000000000'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeChain(string $centreCode): Programme
    {
        $centre  = (new EducationalCentre())->setCode($centreCode)->setName('IES ' . $centreCode)->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $this->persist($centre, $year, $family, $prog);
        return $prog;
    }

    private function makeProgrammeYear(Programme $programme, string $name): ProgrammeYear
    {
        return (new ProgrammeYear())->setName($name)->setProgramme($programme);
    }
}
