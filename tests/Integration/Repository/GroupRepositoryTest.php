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
use App\Entity\Student;
use App\Entity\Teacher;
use App\Repository\GroupRepository;
use App\Tests\Integration\RepositoryTestCase;

class GroupRepositoryTest extends RepositoryTestCase
{
    private GroupRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var GroupRepository $repo */
        $repo       = self::getContainer()->get(GroupRepository::class);
        $this->repo = $repo;
    }

    // ── findByLevelOrderedByName ──────────────────────────────────────────────

    public function testFindByLevelOrderedByNameReturnsSortedGroups(): void
    {
        [, , , , $py] = $this->makeChain('41000001');
        $g1 = $this->makeGroup($py, 'DAM2C');
        $g2 = $this->makeGroup($py, 'DAM2A');
        $g3 = $this->makeGroup($py, 'DAM2B');
        $this->persist($g1, $g2, $g3);

        $results = $this->repo->findByLevelOrderedByName($py);

        self::assertCount(3, $results);
        self::assertSame('DAM2A', $results[0]->getName());
        self::assertSame('DAM2B', $results[1]->getName());
        self::assertSame('DAM2C', $results[2]->getName());
    }

    public function testFindByLevelOrderedByNameExcludesOtherLevels(): void
    {
        [, , , $prog, $pyA] = $this->makeChain('41000002');
        $pyB = (new ProgrammeYear())->setName('2.º DAM')->setProgramme($prog);
        $this->persist($pyB);

        $gA = $this->makeGroup($pyA, 'Grupo A');
        $gB = $this->makeGroup($pyB, 'Grupo B');
        $this->persist($gA, $gB);

        $results = $this->repo->findByLevelOrderedByName($pyA);

        self::assertCount(1, $results);
        self::assertSame('Grupo A', $results[0]->getName());
    }

    // ── findByLevelAndId ──────────────────────────────────────────────────────

    public function testFindByLevelAndIdReturnsGroup(): void
    {
        [, , , , $py] = $this->makeChain('41000003');
        $group = $this->makeGroup($py, 'DAM2A');
        $this->persist($group);

        $result = $this->repo->findByLevelAndId($py, $group->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($group->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByLevelAndIdReturnsNullForDifferentLevel(): void
    {
        [, , , $prog, $pyA] = $this->makeChain('41000004');
        $pyB   = (new ProgrammeYear())->setName('2.º DAM')->setProgramme($prog);
        $this->persist($pyB);

        $group = $this->makeGroup($pyA, 'DAM1A');
        $this->persist($group);

        self::assertNull($this->repo->findByLevelAndId($pyB, $group->getId()->toRfc4122()));
    }

    // ── findByProgrammeWithStudents ───────────────────────────────────────────

    public function testFindByProgrammeWithStudentsReturnsGroupsWithStudentsEagerLoaded(): void
    {
        [, , , $prog, $py] = $this->makeChain('41000005');
        $group   = $this->makeGroup($py, 'DAM2A');
        $student = new Student(new PersonName('Ana', 'Garcia'));
        $student->setStudentId('ST001');
        $this->persist($group, $student);

        $student->addGroup($group);
        $this->flush();

        $results = $this->repo->findByProgrammeWithStudents($prog);

        self::assertCount(1, $results);
        self::assertCount(1, $results[0]->getStudents());
    }

    public function testFindByProgrammeWithStudentsExcludesOtherProgrammes(): void
    {
        [, , $fam, $progA, $pyA] = $this->makeChain('41000006');

        // Build second programme in same family
        $centre = $fam->getAcademicYear()->getEducationalCentre();
        $year   = $fam->getAcademicYear();
        $progB  = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $pyB    = (new ProgrammeYear())->setName('1.º DAW')->setProgramme($progB);
        $this->persist($progB, $pyB);

        $gA = $this->makeGroup($pyA, 'DAM1A');
        $gB = $this->makeGroup($pyB, 'DAW1A');
        $this->persist($gA, $gB);

        $results = $this->repo->findByProgrammeWithStudents($progA);

        self::assertCount(1, $results);
        self::assertSame('DAM1A', $results[0]->getName());
    }

    // ── findByActiveYearOfCentreOrderedByName ─────────────────────────────────

    public function testFindByActiveYearOfCentreOrderedByNameReturnsGroups(): void
    {
        [$centre, $year, , , $py] = $this->makeChain('41000007');
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $g1 = $this->makeGroup($py, 'Grupo B');
        $g2 = $this->makeGroup($py, 'Grupo A');
        $this->persist($g1, $g2);

        $results = $this->repo->findByActiveYearOfCentreOrderedByName($centre);

        self::assertCount(2, $results);
        self::assertSame('Grupo A', $results[0]->getName());
        self::assertSame('Grupo B', $results[1]->getName());
    }

    public function testFindByActiveYearOfCentreOrderedByNameReturnsEmptyWhenNoActiveYear(): void
    {
        [$centre, , , , $py] = $this->makeChain('41000008');
        // No activeAcademicYear set
        $group = $this->makeGroup($py, 'Grupo A');
        $this->persist($group);

        self::assertCount(0, $this->repo->findByActiveYearOfCentreOrderedByName($centre));
    }

    // ── isTeacherInProgramme ──────────────────────────────────────────────────

    public function testIsTeacherInProgrammeReturnsTrueWhenTeacherIsTutor(): void
    {
        [, , , $prog, $py] = $this->makeChain('41000009');
        $teacher = $this->makeTeacher('tutor.one');
        $group   = $this->makeGroup($py, 'DAM1A');
        $this->persist($teacher, $group);
        $group->addTutor($teacher);
        $this->flush();

        self::assertTrue($this->repo->isTeacherInProgramme($teacher, $prog));
    }

    public function testIsTeacherInProgrammeReturnsTrueWhenTeacherIsGroupTeacher(): void
    {
        [, , , $prog, $py] = $this->makeChain('41000010');
        $teacher = $this->makeTeacher('teacher.one');
        $group   = $this->makeGroup($py, 'DAM1A');
        $this->persist($teacher, $group);
        $group->addTeacher($teacher);
        $this->flush();

        self::assertTrue($this->repo->isTeacherInProgramme($teacher, $prog));
    }

    public function testIsTeacherInProgrammeReturnsFalseWhenTeacherHasNoRole(): void
    {
        [, , , $prog] = $this->makeChain('41000011');
        $teacher = $this->makeTeacher('no.role');
        $this->persist($teacher);

        self::assertFalse($this->repo->isTeacherInProgramme($teacher, $prog));
    }

    public function testIsTeacherInProgrammeReturnsFalseForDifferentProgramme(): void
    {
        [$centre, $year, $fam, $progA, $pyA] = $this->makeChain('41000012');
        $progB   = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $pyB     = (new ProgrammeYear())->setName('1.º DAW')->setProgramme($progB);
        $teacher = $this->makeTeacher('tutor.other');
        $groupB  = $this->makeGroup($pyB, 'DAW1A');
        $this->persist($progB, $pyB, $teacher, $groupB);
        $groupB->addTutor($teacher);
        $this->flush();

        // Teacher is tutor in progB, not in progA
        self::assertFalse($this->repo->isTeacherInProgramme($teacher, $progA));
    }

    // ── findCountsByAcademicYear ──────────────────────────────────────────────

    public function testFindCountsByAcademicYearReturnsStudentAndTeacherCounts(): void
    {
        [$centre, $year, , , $py] = $this->makeChain('41000013');
        $group   = $this->makeGroup($py, 'DAM1A');
        $teacher = $this->makeTeacher('teacher.counts');
        $student = new Student(new PersonName('Ana', 'Garcia'));
        $student->setStudentId('ST001');
        $this->persist($group, $teacher, $student);
        $group->addTeacher($teacher);
        $student->addGroup($group);
        $this->flush();

        $counts = $this->repo->findCountsByAcademicYear($year, [$group]);

        $id = $group->getId()->toRfc4122();
        self::assertArrayHasKey($id, $counts);
        self::assertSame(1, $counts[$id]['students']);
        self::assertSame(1, $counts[$id]['teachers']);
    }

    public function testFindCountsByAcademicYearReturnsZerosForEmptyGroup(): void
    {
        [$centre, $year, , , $py] = $this->makeChain('41000014');
        $group = $this->makeGroup($py, 'DAM1A');
        $this->persist($group);

        $counts = $this->repo->findCountsByAcademicYear($year, [$group]);

        $id = $group->getId()->toRfc4122();
        self::assertSame(0, $counts[$id]['students']);
        self::assertSame(0, $counts[$id]['teachers']);
    }

    public function testFindCountsByAcademicYearReturnsEmptyForNoGroups(): void
    {
        [$centre, $year] = $this->makeChain('41000015');

        self::assertSame([], $this->repo->findCountsByAcademicYear($year, []));
    }

    // ── findByActiveYearOfCentreWithProgramme ─────────────────────────────────

    public function testFindByActiveYearOfCentreWithProgrammeReturnsOrderedGroups(): void
    {
        [$centre, $year, , , $py] = $this->makeChain('41000016');
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $g1 = $this->makeGroup($py, 'Grupo B');
        $g2 = $this->makeGroup($py, 'Grupo A');
        $this->persist($g1, $g2);

        $results = $this->repo->findByActiveYearOfCentreWithProgramme($centre);

        self::assertCount(2, $results);
        self::assertSame('Grupo A', $results[0]->getName());
        self::assertSame('Grupo B', $results[1]->getName());
    }

    public function testFindByActiveYearOfCentreWithProgrammeReturnsEmptyWhenNoActiveYear(): void
    {
        [$centre, , , , $py] = $this->makeChain('41000017');
        $this->persist($this->makeGroup($py, 'Grupo A'));

        self::assertCount(0, $this->repo->findByActiveYearOfCentreWithProgramme($centre));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Creates and persists Centre → Year → Family → Programme → ProgrammeYear.
     *
     * @return array{EducationalCentre, AcademicYear, ProfessionalFamily, Programme, ProgrammeYear}
     */
    private function makeChain(string $centreCode): array
    {
        $centre  = (new EducationalCentre())->setCode($centreCode)->setName('IES ' . $centreCode)->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $py      = (new ProgrammeYear())->setName('1.º DAM')->setProgramme($prog);
        $this->persist($centre, $year, $family, $prog, $py);
        return [$centre, $year, $family, $prog, $py];
    }

    private function makeGroup(ProgrammeYear $py, string $name): Group
    {
        return (new Group())->setName($name)->setProgrammeYear($py);
    }

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }
}
