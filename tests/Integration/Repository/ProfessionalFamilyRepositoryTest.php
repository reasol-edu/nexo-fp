<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Teacher;
use App\Repository\ProfessionalFamilyRepository;
use App\Tests\Integration\RepositoryTestCase;

class ProfessionalFamilyRepositoryTest extends RepositoryTestCase
{
    private ProfessionalFamilyRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ProfessionalFamilyRepository $repo */
        $repo       = self::getContainer()->get(ProfessionalFamilyRepository::class);
        $this->repo = $repo;
    }

    // ── isFamilyHeadInCentre ─────────────────────────────────────────────────

    public function testIsFamilyHeadInCentreReturnsTrueWhenTeacherIsHead(): void
    {
        $centre  = $this->makeCentre('41000001');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('head.one');
        $family  = $this->makeFamily($year, 'Informática', $teacher);
        $this->persist($centre, $teacher, $year, $family);

        self::assertTrue($this->repo->isFamilyHeadInCentre($teacher, $centre));
    }

    public function testIsFamilyHeadInCentreReturnsFalseWhenTeacherIsNotHead(): void
    {
        $centre  = $this->makeCentre('41000002');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('no.head');
        $family  = $this->makeFamily($year, 'Informática');
        $this->persist($centre, $teacher, $year, $family);

        self::assertFalse($this->repo->isFamilyHeadInCentre($teacher, $centre));
    }

    public function testIsFamilyHeadInCentreReturnsFalseWhenHeadBelongsToAnotherCentre(): void
    {
        $centreA = $this->makeCentre('41000003');
        $centreB = $this->makeCentre('41000004');
        $yearA   = $this->makeYear($centreA, '2024-2025');
        $teacher = $this->makeTeacher('head.two');
        $family  = $this->makeFamily($yearA, 'Informática', $teacher);
        $this->persist($centreA, $centreB, $teacher, $yearA, $family);

        // El docente es jefe en centreA; centreB no tiene familias con este jefe
        self::assertFalse($this->repo->isFamilyHeadInCentre($teacher, $centreB));
    }

    // ── findByAcademicYearFiltered ────────────────────────────────────────────

    public function testFindByAcademicYearFilteredReturnsAllFamiliesOrderedByName(): void
    {
        $centre = $this->makeCentre('41000005');
        $year   = $this->makeYear($centre, '2024-2025');
        $f1     = $this->makeFamily($year, 'Sanidad');
        $f2     = $this->makeFamily($year, 'Informática');
        $f3     = $this->makeFamily($year, 'Agraria');
        $this->persist($centre, $year, $f1, $f2, $f3);

        $results = $this->repo->findByAcademicYearFiltered($year);

        self::assertCount(3, $results);
        self::assertSame('Agraria', $results[0]->getName());
        self::assertSame('Informática', $results[1]->getName());
        self::assertSame('Sanidad', $results[2]->getName());
    }

    public function testFindByAcademicYearFilteredReturnsOnlyFamiliesForGivenYear(): void
    {
        $centre  = $this->makeCentre('41000006');
        $yearA   = $this->makeYear($centre, '2024-2025');
        $yearB   = $this->makeYear($centre, '2023-2024');
        $familyA = $this->makeFamily($yearA, 'Informática');
        $familyB = $this->makeFamily($yearB, 'Sanidad');
        $this->persist($centre, $yearA, $yearB, $familyA, $familyB);

        $results = $this->repo->findByAcademicYearFiltered($yearA);

        self::assertCount(1, $results);
        self::assertSame('Informática', $results[0]->getName());
    }

    public function testFindByAcademicYearFilteredSearchIsCaseInsensitive(): void
    {
        $centre = $this->makeCentre('41000007');
        $year   = $this->makeYear($centre, '2024-2025');
        // Nombres sin caracteres no-ASCII para que LOWER() de SQLite funcione correctamente
        $f1 = $this->makeFamily($year, 'Informatica y Comunicaciones');
        $f2 = $this->makeFamily($year, 'Sanidad');
        $this->persist($centre, $year, $f1, $f2);

        $results = $this->repo->findByAcademicYearFiltered($year, 'INFORMATICA');

        self::assertCount(1, $results);
        self::assertSame('Informatica y Comunicaciones', $results[0]->getName());
    }

    public function testFindByAcademicYearFilteredSearchByPartialName(): void
    {
        $centre = $this->makeCentre('41000008');
        $year   = $this->makeYear($centre, '2024-2025');
        // 'acion' (sin acento) aparece en 'Comunicaciones' y en 'Administracion'
        $f1 = $this->makeFamily($year, 'Comunicaciones');
        $f2 = $this->makeFamily($year, 'Administracion');
        $f3 = $this->makeFamily($year, 'Sanidad');
        $this->persist($centre, $year, $f1, $f2, $f3);

        $results = $this->repo->findByAcademicYearFiltered($year, 'acion');

        self::assertCount(2, $results);
    }

    public function testFindByAcademicYearFilteredReturnsEmptyWhenNoMatch(): void
    {
        $centre = $this->makeCentre('41000009');
        $year   = $this->makeYear($centre, '2024-2025');
        $family = $this->makeFamily($year, 'Informática');
        $this->persist($centre, $year, $family);

        $results = $this->repo->findByAcademicYearFiltered($year, 'Agraria');

        self::assertCount(0, $results);
    }

    // ── findByYearAndId ──────────────────────────────────────────────────────

    public function testFindByYearAndIdReturnsFamily(): void
    {
        $centre = $this->makeCentre('41000010');
        $year   = $this->makeYear($centre, '2024-2025');
        $family = $this->makeFamily($year, 'Informática');
        $this->persist($centre, $year, $family);

        $result = $this->repo->findByYearAndId($year, $family->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($family->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByYearAndIdReturnsNullForDifferentYear(): void
    {
        $centre = $this->makeCentre('41000011');
        $yearA  = $this->makeYear($centre, '2024-2025');
        $yearB  = $this->makeYear($centre, '2023-2024');
        $family = $this->makeFamily($yearA, 'Informática');
        $this->persist($centre, $yearA, $yearB, $family);

        $result = $this->repo->findByYearAndId($yearB, $family->getId()->toRfc4122());

        self::assertNull($result);
    }

    public function testFindByYearAndIdReturnsNullForNonExistentId(): void
    {
        $centre = $this->makeCentre('41000012');
        $year   = $this->makeYear($centre, '2024-2025');
        $this->persist($centre, $year);

        $result = $this->repo->findByYearAndId($year, '00000000-0000-0000-0000-000000000000');

        self::assertNull($result);
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

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Ana', 'García')))
            ->setUsername($username);
    }

    private function makeFamily(AcademicYear $year, string $name, ?Teacher $head = null): ProfessionalFamily
    {
        return (new ProfessionalFamily())
            ->setName($name)
            ->setAcademicYear($year)
            ->setHead($head);
    }
}
