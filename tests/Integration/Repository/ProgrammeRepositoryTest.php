<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Teacher;
use App\Repository\ProgrammeRepository;
use App\Tests\Integration\RepositoryTestCase;

class ProgrammeRepositoryTest extends RepositoryTestCase
{
    private ProgrammeRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ProgrammeRepository $repo */
        $repo       = self::getContainer()->get(ProgrammeRepository::class);
        $this->repo = $repo;
    }

    // ── findByAcademicYearFilteredByFamily ────────────────────────────────────

    public function testFindByAcademicYearFilteredByFamilyReturnsAllOrderedByFamilyAndName(): void
    {
        $centre = $this->makeCentre('41000001');
        $year   = $this->makeYear($centre);
        $famA   = $this->makeFamily($year, 'Administracion');
        $famB   = $this->makeFamily($year, 'Informatica');
        $p1     = $this->makeProgramme($year, $famB, 'DAM');
        $p2     = $this->makeProgramme($year, $famA, 'ASIR');
        $p3     = $this->makeProgramme($year, $famB, 'DAW');
        $this->persist($centre, $year, $famA, $famB, $p1, $p2, $p3);

        $results = $this->repo->findByAcademicYearFilteredByFamily($year);

        self::assertCount(3, $results);
        self::assertSame('ASIR', $results[0]->getName()); // Administracion/ASIR
        self::assertSame('DAM',  $results[1]->getName()); // Informatica/DAM
        self::assertSame('DAW',  $results[2]->getName()); // Informatica/DAW
    }

    public function testFindByAcademicYearFilteredByFamilyFiltersWhenFamilyIdGiven(): void
    {
        $centre = $this->makeCentre('41000002');
        $year   = $this->makeYear($centre);
        $famA   = $this->makeFamily($year, 'Informatica');
        $famB   = $this->makeFamily($year, 'Sanidad');
        $pA     = $this->makeProgramme($year, $famA, 'DAM');
        $pB     = $this->makeProgramme($year, $famB, 'Enfermeria');
        $this->persist($centre, $year, $famA, $famB, $pA, $pB);

        $results = $this->repo->findByAcademicYearFilteredByFamily($year, $famA->getId()->toRfc4122());

        self::assertCount(1, $results);
        self::assertSame('DAM', $results[0]->getName());
    }

    public function testFindByAcademicYearFilteredByFamilyReturnsOnlyGivenYear(): void
    {
        $centre = $this->makeCentre('41000003');
        $yearA  = $this->makeYear($centre, '2024-2025');
        $yearB  = $this->makeYear($centre, '2023-2024');
        $famA   = $this->makeFamily($yearA, 'Informatica');
        $famB   = $this->makeFamily($yearB, 'Informatica');
        $pA     = $this->makeProgramme($yearA, $famA, 'DAM');
        $pB     = $this->makeProgramme($yearB, $famB, 'DAM');
        $this->persist($centre, $yearA, $yearB, $famA, $famB, $pA, $pB);

        $results = $this->repo->findByAcademicYearFilteredByFamily($yearA);

        self::assertCount(1, $results);
    }

    // ── findByAcademicYearOrderedByFamilyAndName ──────────────────────────────

    public function testFindByAcademicYearOrderedByFamilyAndName(): void
    {
        $centre = $this->makeCentre('41000004');
        $year   = $this->makeYear($centre);
        $famA   = $this->makeFamily($year, 'Administracion');
        $famB   = $this->makeFamily($year, 'Informatica');
        $p1     = $this->makeProgramme($year, $famB, 'DAM');
        $p2     = $this->makeProgramme($year, $famA, 'ASIR');
        $this->persist($centre, $year, $famA, $famB, $p1, $p2);

        $results = $this->repo->findByAcademicYearOrderedByFamilyAndName($year);

        self::assertCount(2, $results);
        self::assertSame('ASIR', $results[0]->getName());
        self::assertSame('DAM',  $results[1]->getName());
    }

    // ── findByAcademicYearAndId ───────────────────────────────────────────────

    public function testFindByAcademicYearAndIdReturnsProgramme(): void
    {
        $centre    = $this->makeCentre('41000005');
        $year      = $this->makeYear($centre);
        $fam       = $this->makeFamily($year, 'Informatica');
        $programme = $this->makeProgramme($year, $fam, 'DAM');
        $this->persist($centre, $year, $fam, $programme);

        $result = $this->repo->findByAcademicYearAndId($year, $programme->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($programme->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByAcademicYearAndIdReturnsNullForDifferentYear(): void
    {
        $centre    = $this->makeCentre('41000006');
        $yearA     = $this->makeYear($centre, '2024-2025');
        $yearB     = $this->makeYear($centre, '2023-2024');
        $famA      = $this->makeFamily($yearA, 'Informatica');
        $programme = $this->makeProgramme($yearA, $famA, 'DAM');
        $this->persist($centre, $yearA, $yearB, $famA, $programme);

        self::assertNull($this->repo->findByAcademicYearAndId($yearB, $programme->getId()->toRfc4122()));
    }

    // ── findByFamilyOrderedByName ─────────────────────────────────────────────

    public function testFindByFamilyOrderedByNameReturnsSortedProgrammes(): void
    {
        $centre = $this->makeCentre('41000007');
        $year   = $this->makeYear($centre);
        $fam    = $this->makeFamily($year, 'Informatica');
        $p1     = $this->makeProgramme($year, $fam, 'SMR');
        $p2     = $this->makeProgramme($year, $fam, 'DAM');
        $p3     = $this->makeProgramme($year, $fam, 'ASIR');
        $this->persist($centre, $year, $fam, $p1, $p2, $p3);

        $results = $this->repo->findByFamilyOrderedByName($fam);

        self::assertCount(3, $results);
        self::assertSame('ASIR', $results[0]->getName());
        self::assertSame('DAM',  $results[1]->getName());
        self::assertSame('SMR',  $results[2]->getName());
    }

    // ── findByFamilyAndId ─────────────────────────────────────────────────────

    public function testFindByFamilyAndIdReturnsProgramme(): void
    {
        $centre    = $this->makeCentre('41000008');
        $year      = $this->makeYear($centre);
        $fam       = $this->makeFamily($year, 'Informatica');
        $programme = $this->makeProgramme($year, $fam, 'DAM');
        $this->persist($centre, $year, $fam, $programme);

        $result = $this->repo->findByFamilyAndId($fam, $programme->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($programme->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByFamilyAndIdReturnsNullForDifferentFamily(): void
    {
        $centre    = $this->makeCentre('41000009');
        $year      = $this->makeYear($centre);
        $famA      = $this->makeFamily($year, 'Informatica');
        $famB      = $this->makeFamily($year, 'Sanidad');
        $programme = $this->makeProgramme($year, $famA, 'DAM');
        $this->persist($centre, $year, $famA, $famB, $programme);

        self::assertNull($this->repo->findByFamilyAndId($famB, $programme->getId()->toRfc4122()));
    }

    // ── isCoordinatorOf ───────────────────────────────────────────────────────

    public function testIsCoordinatorOfReturnsTrueWhenTeacherIsCoordinator(): void
    {
        $centre    = $this->makeCentre('41000010');
        $year      = $this->makeYear($centre);
        $fam       = $this->makeFamily($year, 'Informatica');
        $programme = $this->makeProgramme($year, $fam, 'DAM');
        $teacher   = $this->makeTeacher('coord.one');
        $this->persist($centre, $year, $fam, $programme, $teacher);

        $programme->addCoordinator($teacher);
        $this->flush();

        self::assertTrue($this->repo->isCoordinatorOf($teacher, $programme));
    }

    public function testIsCoordinatorOfReturnsFalseWhenTeacherIsNotCoordinator(): void
    {
        $centre    = $this->makeCentre('41000011');
        $year      = $this->makeYear($centre);
        $fam       = $this->makeFamily($year, 'Informatica');
        $programme = $this->makeProgramme($year, $fam, 'DAM');
        $teacher   = $this->makeTeacher('no.coord');
        $this->persist($centre, $year, $fam, $programme, $teacher);

        self::assertFalse($this->repo->isCoordinatorOf($teacher, $programme));
    }

    public function testIsCoordinatorOfReturnsFalseForDifferentProgramme(): void
    {
        $centre  = $this->makeCentre('41000012');
        $year    = $this->makeYear($centre);
        $fam     = $this->makeFamily($year, 'Informatica');
        $progA   = $this->makeProgramme($year, $fam, 'DAM');
        $progB   = $this->makeProgramme($year, $fam, 'DAW');
        $teacher = $this->makeTeacher('coord.two');
        $this->persist($centre, $year, $fam, $progA, $progB, $teacher);

        $progA->addCoordinator($teacher);
        $this->flush();

        self::assertFalse($this->repo->isCoordinatorOf($teacher, $progB));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
    }

    private function makeYear(EducationalCentre $centre, string $name = '2024-2025'): AcademicYear
    {
        return (new AcademicYear())->setName($name)->setEducationalCentre($centre);
    }

    private function makeFamily(AcademicYear $year, string $name): ProfessionalFamily
    {
        return (new ProfessionalFamily())->setName($name)->setAcademicYear($year);
    }

    private function makeProgramme(AcademicYear $year, ProfessionalFamily $family, string $name): Programme
    {
        return (new Programme())->setName($name)->setAcademicYear($year)->setProfessionalFamily($family);
    }

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }
}
