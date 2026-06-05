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
}
