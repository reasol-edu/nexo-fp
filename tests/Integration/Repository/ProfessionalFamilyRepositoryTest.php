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

    // ── findByAcademicYearVisibleToTeacher ────────────────────────────────────

    public function testVisibleToTeacherReturnsFamilyWhenFamilyHead(): void
    {
        $centre  = $this->makeCentre('41000013');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('fam.head.vis.1');
        $famA    = $this->makeFamily($year, 'Informatica', $teacher);
        $famB    = $this->makeFamily($year, 'Sanidad');
        $this->persist($centre, $year, $teacher, $famA, $famB);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(1, $results);
        self::assertSame('Informatica', $results[0]->getName());
    }

    public function testVisibleToTeacherReturnsFamilyWhenGroupTutor(): void
    {
        $centre  = $this->makeCentre('41000014');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('tutor.fam.vis.1');
        $famA    = $this->makeFamily($year, 'Informatica');
        $famB    = $this->makeFamily($year, 'Sanidad');
        $prog    = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($famA);
        $level   = (new ProgrammeYear())->setName('1º')->setProgramme($prog);
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($level)->addTutor($teacher);
        $this->persist($centre, $year, $teacher, $famA, $famB, $prog, $level, $group);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(1, $results);
        self::assertSame('Informatica', $results[0]->getName());
    }

    public function testVisibleToTeacherReturnsFamilyWhenGroupTeacher(): void
    {
        $centre  = $this->makeCentre('41000015');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('grp.teacher.fam.1');
        $famA    = $this->makeFamily($year, 'Informatica');
        $famB    = $this->makeFamily($year, 'Sanidad');
        $prog    = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($famA);
        $level   = (new ProgrammeYear())->setName('1º')->setProgramme($prog);
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($level);
        $this->persist($centre, $year, $teacher, $famA, $famB, $prog, $level, $group);
        $group->addTeacher($teacher);
        $this->flush();

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(1, $results);
        self::assertSame('Informatica', $results[0]->getName());
    }

    public function testVisibleToTeacherReturnsEmptyForUnrelatedTeacher(): void
    {
        $centre  = $this->makeCentre('41000016');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('unrelated.fam.1');
        $fam     = $this->makeFamily($year, 'Informatica');
        $this->persist($centre, $year, $teacher, $fam);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(0, $results);
    }

    public function testVisibleToTeacherDeduplicatesWhenMultipleProgrammesInSameFamily(): void
    {
        $centre  = $this->makeCentre('41000017');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('multi.prog.fam.1');
        $fam     = $this->makeFamily($year, 'Informatica', $teacher);
        $progA   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $progB   = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $this->persist($centre, $year, $teacher, $fam, $progA, $progB);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(1, $results);
    }

    public function testVisibleToTeacherIgnoresFamiliesFromOtherYears(): void
    {
        $centre  = $this->makeCentre('41000018');
        $yearA   = $this->makeYear($centre, '2024-2025');
        $yearB   = $this->makeYear($centre, '2023-2024');
        $teacher = $this->makeTeacher('year.fam.vis.1');
        $famA    = $this->makeFamily($yearA, 'Informatica', $teacher);
        $famB    = $this->makeFamily($yearB, 'Informatica', $teacher);
        $this->persist($centre, $yearA, $yearB, $teacher, $famA, $famB);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($yearA, $teacher);

        self::assertCount(1, $results);
        self::assertSame($yearA->getId()->toRfc4122(), $results[0]->getAcademicYear()->getId()->toRfc4122());
    }

    public function testVisibleToTeacherIsOrderedByName(): void
    {
        $centre  = $this->makeCentre('41000019');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('order.fam.vis.1');
        $famC    = $this->makeFamily($year, 'Sanidad', $teacher);
        $famA    = $this->makeFamily($year, 'Administracion', $teacher);
        $famB    = $this->makeFamily($year, 'Informatica', $teacher);
        $this->persist($centre, $year, $teacher, $famA, $famB, $famC);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(3, $results);
        self::assertSame('Administracion', $results[0]->getName());
        self::assertSame('Informatica',    $results[1]->getName());
        self::assertSame('Sanidad',        $results[2]->getName());
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
