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

    // ── findByAcademicYearVisibleToTeacher ────────────────────────────────────

    public function testVisibleToTeacherReturnsProgrammeWhenFamilyHead(): void
    {
        $centre  = $this->makeCentre('41001001');
        $year    = $this->makeYear($centre);
        $teacher = $this->makeTeacher('head.1');
        $fam     = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year)->setHead($teacher);
        $prog    = $this->makeProgramme($year, $fam, 'DAM');
        $other   = $this->makeProgramme($year, $fam, 'DAW');
        $this->persist($centre, $year, $teacher, $fam, $prog, $other);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(2, $results);
    }

    public function testVisibleToTeacherReturnsProgrammeWhenGroupTutor(): void
    {
        $centre  = $this->makeCentre('41001002');
        $year    = $this->makeYear($centre);
        $teacher = $this->makeTeacher('tutor.1');
        $fam     = $this->makeFamily($year, 'Informatica');
        $prog    = $this->makeProgramme($year, $fam, 'DAM');
        $other   = $this->makeProgramme($year, $fam, 'DAW');
        $level   = (new ProgrammeYear())->setName('1º')->setProgramme($prog);
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($level)->addTutor($teacher);
        $this->persist($centre, $year, $teacher, $fam, $prog, $other, $level, $group);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(1, $results);
        self::assertSame('DAM', $results[0]->getName());
    }

    public function testVisibleToTeacherReturnsProgrammeWhenGroupTeacher(): void
    {
        $centre  = $this->makeCentre('41001003');
        $year    = $this->makeYear($centre);
        $teacher = $this->makeTeacher('grp.teacher.1');
        $fam     = $this->makeFamily($year, 'Informatica');
        $prog    = $this->makeProgramme($year, $fam, 'DAM');
        $other   = $this->makeProgramme($year, $fam, 'DAW');
        $level   = (new ProgrammeYear())->setName('1º')->setProgramme($prog);
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($level);
        $this->persist($centre, $year, $teacher, $fam, $prog, $other, $level, $group);
        $group->addTeacher($teacher);
        $this->flush();

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(1, $results);
        self::assertSame('DAM', $results[0]->getName());
    }

    public function testVisibleToTeacherReturnsEmptyForUnrelatedTeacher(): void
    {
        $centre  = $this->makeCentre('41001004');
        $year    = $this->makeYear($centre);
        $teacher = $this->makeTeacher('unrelated.1');
        $fam     = $this->makeFamily($year, 'Informatica');
        $prog    = $this->makeProgramme($year, $fam, 'DAM');
        $this->persist($centre, $year, $teacher, $fam, $prog);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(0, $results);
    }

    public function testVisibleToTeacherDeduplicatesWhenMultipleGroupsInSameProgramme(): void
    {
        $centre  = $this->makeCentre('41001005');
        $year    = $this->makeYear($centre);
        $teacher = $this->makeTeacher('multi.group.1');
        $fam     = $this->makeFamily($year, 'Informatica');
        $prog    = $this->makeProgramme($year, $fam, 'DAM');
        $level   = (new ProgrammeYear())->setName('1º')->setProgramme($prog);
        $groupA  = (new Group())->setName('DAM1A')->setProgrammeYear($level)->addTutor($teacher);
        $groupB  = (new Group())->setName('DAM1B')->setProgrammeYear($level)->addTutor($teacher);
        $this->persist($centre, $year, $teacher, $fam, $prog, $level, $groupA, $groupB);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher);

        self::assertCount(1, $results);
    }

    public function testVisibleToTeacherReturnsProgrammeWhenOneOfMultipleTutors(): void
    {
        $centre   = $this->makeCentre('41001009');
        $year     = $this->makeYear($centre);
        $tutorA   = $this->makeTeacher('multi.tutor.a');
        $tutorB   = $this->makeTeacher('multi.tutor.b');
        $fam      = $this->makeFamily($year, 'Informatica');
        $prog     = $this->makeProgramme($year, $fam, 'DAM');
        $level    = (new ProgrammeYear())->setName('1º')->setProgramme($prog);
        $group    = (new Group())->setName('DAM1A')->setProgrammeYear($level);
        $this->persist($centre, $year, $tutorA, $tutorB, $fam, $prog, $level, $group);
        $group->addTutor($tutorA);
        $group->addTutor($tutorB);
        $this->flush();

        $resultsA = $this->repo->findByAcademicYearVisibleToTeacher($year, $tutorA);
        $resultsB = $this->repo->findByAcademicYearVisibleToTeacher($year, $tutorB);

        self::assertCount(1, $resultsA);
        self::assertSame('DAM', $resultsA[0]->getName());
        self::assertCount(1, $resultsB);
        self::assertSame('DAM', $resultsB[0]->getName());
    }

    public function testVisibleToTeacherFiltersWhenFamilyIdGiven(): void
    {
        $centre  = $this->makeCentre('41001006');
        $year    = $this->makeYear($centre);
        $teacher = $this->makeTeacher('fam.filter.1');
        $famA    = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year)->setHead($teacher);
        $famB    = (new ProfessionalFamily())->setName('Sanidad')->setAcademicYear($year)->setHead($teacher);
        $progA   = $this->makeProgramme($year, $famA, 'DAM');
        $progB   = $this->makeProgramme($year, $famB, 'Enfermeria');
        $this->persist($centre, $year, $teacher, $famA, $famB, $progA, $progB);

        $results = $this->repo->findByAcademicYearVisibleToTeacher($year, $teacher, $famA->getId()->toRfc4122());

        self::assertCount(1, $results);
        self::assertSame('DAM', $results[0]->getName());
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

    // ── isCoordinatorInCentre ─────────────────────────────────────────────────

    public function testIsCoordinatorInCentreReturnsTrueWhenTeacherCoordsInCentre(): void
    {
        $centre    = $this->makeCentre('41000013');
        $year      = $this->makeYear($centre);
        $fam       = $this->makeFamily($year, 'Informatica');
        $programme = $this->makeProgramme($year, $fam, 'DAW');
        $teacher   = $this->makeTeacher('coord.centre.1');
        $this->persist($centre, $year, $fam, $programme, $teacher);

        $programme->addCoordinator($teacher);
        $this->flush();

        self::assertTrue($this->repo->isCoordinatorInCentre($teacher, $centre));
    }

    public function testIsCoordinatorInCentreReturnsFalseForUnrelatedTeacher(): void
    {
        $centre    = $this->makeCentre('41000014');
        $year      = $this->makeYear($centre);
        $fam       = $this->makeFamily($year, 'Informatica');
        $programme = $this->makeProgramme($year, $fam, 'DAW');
        $teacher   = $this->makeTeacher('no.coord.centre');
        $this->persist($centre, $year, $fam, $programme, $teacher);

        self::assertFalse($this->repo->isCoordinatorInCentre($teacher, $centre));
    }

    public function testIsCoordinatorInCentreReturnsFalseForDifferentCentre(): void
    {
        $centreA   = $this->makeCentre('41000015');
        $centreB   = $this->makeCentre('41000016');
        $yearA     = $this->makeYear($centreA);
        $fam       = $this->makeFamily($yearA, 'Informatica');
        $programme = $this->makeProgramme($yearA, $fam, 'DAW');
        $teacher   = $this->makeTeacher('coord.centreA.only');
        $this->persist($centreA, $centreB, $yearA, $fam, $programme, $teacher);

        $programme->addCoordinator($teacher);
        $this->flush();

        self::assertFalse($this->repo->isCoordinatorInCentre($teacher, $centreB));
    }

    // ── findCoordinatedByAcademicYear ─────────────────────────────────────────

    public function testFindCoordinatedByAcademicYearReturnsOnlyCoordinatedProgrammes(): void
    {
        $centre  = $this->makeCentre('41000017');
        $year    = $this->makeYear($centre);
        $fam     = $this->makeFamily($year, 'Informatica');
        $progA   = $this->makeProgramme($year, $fam, 'DAM');
        $progB   = $this->makeProgramme($year, $fam, 'DAW');
        $teacher = $this->makeTeacher('coord.only.dam');
        $this->persist($centre, $year, $fam, $progA, $progB, $teacher);

        $progA->addCoordinator($teacher);
        $this->flush();

        $results = $this->repo->findCoordinatedByAcademicYear($teacher, $year);

        self::assertCount(1, $results);
        self::assertSame('DAM', $results[0]->getName());
    }

    public function testFindCoordinatedByAcademicYearReturnsEmptyForNonCoordinator(): void
    {
        $centre  = $this->makeCentre('41000018');
        $year    = $this->makeYear($centre);
        $fam     = $this->makeFamily($year, 'Informatica');
        $prog    = $this->makeProgramme($year, $fam, 'DAW');
        $teacher = $this->makeTeacher('no.coord.at.all');
        $this->persist($centre, $year, $fam, $prog, $teacher);

        $results = $this->repo->findCoordinatedByAcademicYear($teacher, $year);

        self::assertCount(0, $results);
    }

    public function testFindCoordinatedByAcademicYearIgnoresOtherYears(): void
    {
        $centre  = $this->makeCentre('41000019');
        $yearA   = $this->makeYear($centre, '2024-2025');
        $yearB   = $this->makeYear($centre, '2025-2026');
        $famA    = $this->makeFamily($yearA, 'Informatica');
        $famB    = $this->makeFamily($yearB, 'Informatica');
        $progA   = $this->makeProgramme($yearA, $famA, 'DAW');
        $progB   = $this->makeProgramme($yearB, $famB, 'DAW');
        $teacher = $this->makeTeacher('coord.year.a');
        $this->persist($centre, $yearA, $yearB, $famA, $famB, $progA, $progB, $teacher);

        $progA->addCoordinator($teacher);
        $progB->addCoordinator($teacher);
        $this->flush();

        $results = $this->repo->findCoordinatedByAcademicYear($teacher, $yearA);

        self::assertCount(1, $results);
        self::assertSame($yearA->getId()->toRfc4122(), $results[0]->getAcademicYear()->getId()->toRfc4122());
    }

    public function testFindCoordinatedByAcademicYearIsOrderedByFamilyThenName(): void
    {
        $centre = $this->makeCentre('41000020');
        $year   = $this->makeYear($centre);
        $famA   = $this->makeFamily($year, 'Administracion');
        $famB   = $this->makeFamily($year, 'Informatica');
        $progC  = $this->makeProgramme($year, $famB, 'DAM');
        $progA  = $this->makeProgramme($year, $famA, 'ASIR');
        $progB  = $this->makeProgramme($year, $famB, 'DAW');
        $teacher = $this->makeTeacher('coord.order');
        $this->persist($centre, $year, $famA, $famB, $progA, $progB, $progC, $teacher);

        $progA->addCoordinator($teacher);
        $progB->addCoordinator($teacher);
        $progC->addCoordinator($teacher);
        $this->flush();

        $results = $this->repo->findCoordinatedByAcademicYear($teacher, $year);

        self::assertCount(3, $results);
        self::assertSame('ASIR', $results[0]->getName()); // Administracion/ASIR
        self::assertSame('DAM',  $results[1]->getName()); // Informatica/DAM
        self::assertSame('DAW',  $results[2]->getName()); // Informatica/DAW
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
