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
use App\Repository\StudentRepository;
use App\Tests\Integration\RepositoryTestCase;

class StudentRepositoryTest extends RepositoryTestCase
{
    private StudentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var StudentRepository $repo */
        $repo       = self::getContainer()->get(StudentRepository::class);
        $this->repo = $repo;
    }

    // ── findById ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsStudent(): void
    {
        $student = $this->makeStudent('ST001', 'Ana', 'Garcia');
        $this->persist($student);

        $result = $this->repo->findById($student->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($student->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByIdReturnsNullForNonExistentId(): void
    {
        self::assertNull($this->repo->findById('00000000-0000-0000-0000-000000000000'));
    }

    // ── findByStudentId ───────────────────────────────────────────────────────

    public function testFindByStudentIdReturnsStudent(): void
    {
        $student = $this->makeStudent('ST001', 'Ana', 'Garcia');
        $this->persist($student);

        $result = $this->repo->findByStudentId('ST001');

        self::assertNotNull($result);
        self::assertSame('ST001', $result->getStudentId());
    }

    public function testFindByStudentIdReturnsNullForUnknownId(): void
    {
        $student = $this->makeStudent('ST001');
        $this->persist($student);

        self::assertNull($this->repo->findByStudentId('UNKNOWN'));
    }

    // ── createByCentreFilteredQuery ───────────────────────────────────────────

    public function testCreateByCentreFilteredQueryReturnsStudentsInActiveYear(): void
    {
        [$centre, $year, $group] = $this->makeGroupChain('41000001');
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $s1 = $this->makeStudent('ST001', 'Carlos', 'Ruiz');
        $s2 = $this->makeStudent('ST002', 'Ana',    'Garcia');
        $this->persist($s1, $s2);

        $s1->addGroup($group);
        $s2->addGroup($group);
        $this->flush();

        $results = $this->repo->createByCentreFilteredQuery($centre)->getResult();

        self::assertCount(2, $results);
        // Ordered by lastName ASC, firstName ASC
        self::assertSame('ST002', $results[0]->getStudentId()); // Garcia, Ana
        self::assertSame('ST001', $results[1]->getStudentId()); // Ruiz, Carlos
    }

    public function testCreateByCentreFilteredQueryFiltersBySearch(): void
    {
        [$centre, $year, $group] = $this->makeGroupChain('41000002');
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $s1 = $this->makeStudent('ST001', 'Ana',   'Garcia');
        $s2 = $this->makeStudent('ST002', 'Pedro', 'Lopez');
        $this->persist($s1, $s2);

        $s1->addGroup($group);
        $s2->addGroup($group);
        $this->flush();

        $results = $this->repo->createByCentreFilteredQuery($centre, 'Garcia')->getResult();

        self::assertCount(1, $results);
        self::assertSame('ST001', $results[0]->getStudentId());
    }

    public function testCreateByCentreFilteredQueryFiltersByGroupId(): void
    {
        [$centre, $year, $groupA] = $this->makeGroupChain('41000003');
        // Create a second group in the same chain
        $prog    = $groupA->getProgrammeYear()->getProgramme();
        $py      = $groupA->getProgrammeYear();
        $groupB  = (new Group())->setName('B')->setProgrammeYear($py);
        $this->persist($groupB);

        $centre->setActiveAcademicYear($year);
        $this->flush();

        $s1 = $this->makeStudent('ST001', 'Ana',   'Garcia');
        $s2 = $this->makeStudent('ST002', 'Pedro', 'Lopez');
        $this->persist($s1, $s2);

        $s1->addGroup($groupA);
        $s2->addGroup($groupB);
        $this->flush();

        $results = $this->repo->createByCentreFilteredQuery(
            $centre,
            '',
            $groupA->getId()->toRfc4122()
        )->getResult();

        self::assertCount(1, $results);
        self::assertSame('ST001', $results[0]->getStudentId());
    }

    // ── countByActiveYear ─────────────────────────────────────────────────────

    public function testCountByActiveYearWithoutViewer(): void
    {
        [$centre, , $groupA, $groupB] = $this->makeTwoProgrammeChain('41001001');

        $s1 = $this->makeStudent('ST001'); $s2 = $this->makeStudent('ST002');
        $s3 = $this->makeStudent('ST003'); $s4 = $this->makeStudent('ST004');
        $this->persist($s1, $s2, $s3, $s4);
        $s1->addGroup($groupA); $s2->addGroup($groupA);
        $s3->addGroup($groupB); $s4->addGroup($groupB);
        $this->flush();

        self::assertSame(4, $this->repo->countByActiveYear($centre));
    }

    public function testCountByActiveYearGlobalAdminSeesAll(): void
    {
        [$centre, , $groupA, $groupB] = $this->makeTwoProgrammeChain('41001002');

        $s1 = $this->makeStudent('ST001'); $s2 = $this->makeStudent('ST002');
        $s3 = $this->makeStudent('ST003');
        $this->persist($s1, $s2, $s3);
        $s1->addGroup($groupA); $s2->addGroup($groupA); $s3->addGroup($groupB);
        $this->flush();

        $admin = $this->makeTeacher('admin');
        $admin->setAdmin(true);
        $this->persist($admin);

        self::assertSame(3, $this->repo->countByActiveYear($centre, $admin));
    }

    public function testCountByActiveYearCentreAdminSeesAll(): void
    {
        [$centre, $year, $groupA, $groupB] = $this->makeTwoProgrammeChain('41001003');

        $s1 = $this->makeStudent('ST001'); $s2 = $this->makeStudent('ST002');
        $s3 = $this->makeStudent('ST003');
        $this->persist($s1, $s2, $s3);
        $s1->addGroup($groupA); $s2->addGroup($groupA); $s3->addGroup($groupB);
        $this->flush();

        $centreAdmin = $this->makeTeacher('centreadmin');
        $year->addTeacher($centreAdmin);
        $centre->addAdmin($centreAdmin);
        $this->persist($centreAdmin);
        $this->flush();

        self::assertSame(3, $this->repo->countByActiveYear($centre, $centreAdmin));
    }

    public function testCountByActiveYearCoordinatorSeesProgrammeStudents(): void
    {
        [$centre, $year, $groupA, $groupB, $progA] = $this->makeTwoProgrammeChain('41001004');

        $s1 = $this->makeStudent('ST001'); $s2 = $this->makeStudent('ST002');
        $s3 = $this->makeStudent('ST003');
        $this->persist($s1, $s2, $s3);
        $s1->addGroup($groupA); $s2->addGroup($groupA); $s3->addGroup($groupB);
        $this->flush();

        $coordinator = $this->makeTeacher('coordinator');
        $year->addTeacher($coordinator);
        $progA->addCoordinator($coordinator);
        $this->persist($coordinator);
        $this->flush();

        self::assertSame(2, $this->repo->countByActiveYear($centre, $coordinator));
    }

    public function testCountByActiveYearFamilyHeadSeesFamilyStudents(): void
    {
        [$centre, $year, $groupA, $groupB, , $familyA] = $this->makeTwoProgrammeChain('41001005');

        $s1 = $this->makeStudent('ST001'); $s2 = $this->makeStudent('ST002');
        $s3 = $this->makeStudent('ST003');
        $this->persist($s1, $s2, $s3);
        $s1->addGroup($groupA); $s2->addGroup($groupA); $s3->addGroup($groupB);
        $this->flush();

        $head = $this->makeTeacher('familyhead');
        $year->addTeacher($head);
        $familyA->setHead($head);
        $this->persist($head);
        $this->flush();

        // familyA only has progA → groupA, so head sees 2 students
        self::assertSame(2, $this->repo->countByActiveYear($centre, $head));
    }

    public function testCountByActiveYearGroupTeacherSeesProgrammeStudents(): void
    {
        // Teacher in groupA2 (second group of progA) must see ALL students of progA,
        // not just groupA2 students.
        [$centre, $year, $groupA, , $progA] = $this->makeTwoProgrammeChain('41001006');

        $pyA    = $groupA->getProgrammeYear();
        $groupA2 = (new Group())->setName('A2')->setProgrammeYear($pyA);
        $this->persist($groupA2);

        $s1 = $this->makeStudent('ST001'); $s2 = $this->makeStudent('ST002');
        $s3 = $this->makeStudent('ST003');
        $this->persist($s1, $s2, $s3);
        $s1->addGroup($groupA);  // groupA
        $s2->addGroup($groupA);  // groupA
        $s3->addGroup($groupA2); // groupA2 — teacher's group
        $this->flush();

        $teacher = $this->makeTeacher('groupteacher');
        $year->addTeacher($teacher);
        $groupA2->addTeacher($teacher);
        $this->persist($teacher);
        $this->flush();

        // Teacher is in groupA2 of progA → sees all 3 students of progA
        self::assertSame(3, $this->repo->countByActiveYear($centre, $teacher));
    }

    public function testCountByActiveYearUnrelatedTeacherSeesZero(): void
    {
        [$centre, $year, $groupA, $groupB] = $this->makeTwoProgrammeChain('41001007');

        $s1 = $this->makeStudent('ST001'); $s2 = $this->makeStudent('ST002');
        $this->persist($s1, $s2);
        $s1->addGroup($groupA); $s2->addGroup($groupB);
        $this->flush();

        $unrelated = $this->makeTeacher('unrelated');
        $year->addTeacher($unrelated);
        $this->persist($unrelated);
        $this->flush();

        self::assertSame(0, $this->repo->countByActiveYear($centre, $unrelated));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Builds and persists Centre → Year → Family → Programme → ProgrammeYear → Group.
     *
     * @return array{EducationalCentre, AcademicYear, Group}
     */
    private function makeGroupChain(string $centreCode): array
    {
        $centre  = (new EducationalCentre())->setCode($centreCode)->setName('IES ' . $centreCode)->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $py      = (new ProgrammeYear())->setName('1.º DAM')->setProgramme($prog);
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($py);
        $this->persist($centre, $year, $family, $prog, $py, $group);
        return [$centre, $year, $group];
    }

    private function makeStudent(string $studentId, string $firstName = 'Test', string $lastName = 'Student'): Student
    {
        return (new Student(new PersonName($firstName, $lastName)))->setStudentId($studentId);
    }

    private function makeTeacher(string $username): Teacher
    {
        $t = new Teacher(new PersonName($username, 'Test'));
        $t->setUsername($username);
        $t->setPassword('x');
        return $t;
    }

    /**
     * Builds and persists:
     *   Centre → Year
     *     FamilyA → ProgrammeA → ProgrammeYearA → GroupA
     *     FamilyB → ProgrammeB → ProgrammeYearB → GroupB
     *
     * Returns [centre, year, groupA, groupB, programmeA, familyA].
     *
     * @return array{EducationalCentre, AcademicYear, Group, Group, Programme, ProfessionalFamily}
     */
    private function makeTwoProgrammeChain(string $centreCode): array
    {
        $centre  = (new EducationalCentre())->setCode($centreCode)->setName('IES ' . $centreCode)->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $centre->setActiveAcademicYear($year);

        $familyA = (new ProfessionalFamily())->setName('FamiliaA')->setAcademicYear($year);
        $progA   = (new Programme())->setName('ProgA')->setAcademicYear($year)->setProfessionalFamily($familyA);
        $pyA     = (new ProgrammeYear())->setName('1.º ProgA')->setProgramme($progA);
        $groupA  = (new Group())->setName('GA')->setProgrammeYear($pyA);

        $familyB = (new ProfessionalFamily())->setName('FamiliaB')->setAcademicYear($year);
        $progB   = (new Programme())->setName('ProgB')->setAcademicYear($year)->setProfessionalFamily($familyB);
        $pyB     = (new ProgrammeYear())->setName('1.º ProgB')->setProgramme($progB);
        $groupB  = (new Group())->setName('GB')->setProgrammeYear($pyB);

        $this->persist($centre, $year, $familyA, $progA, $pyA, $groupA, $familyB, $progB, $pyB, $groupB);
        return [$centre, $year, $groupA, $groupB, $progA, $familyA];
    }
}
