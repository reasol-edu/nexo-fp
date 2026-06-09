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
use App\Repository\TeacherRepository;
use App\Tests\Integration\RepositoryTestCase;

class TeacherRepositoryTest extends RepositoryTestCase
{
    private TeacherRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var TeacherRepository $repo */
        $repo       = self::getContainer()->get(TeacherRepository::class);
        $this->repo = $repo;
    }

    // ── findById ─────────────────────────────────────────────────────────────

    public function testFindByIdReturnsTeacher(): void
    {
        $teacher = $this->makeTeacher('find.by.id');
        $this->persist($teacher);

        $result = $this->repo->findById($teacher->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame('find.by.id', $result->getUsername());
    }

    public function testFindByIdReturnsNullForNonExistentId(): void
    {
        self::assertNull($this->repo->findById('00000000-0000-0000-0000-000000000000'));
    }

    // ── findAllOrderedByName ──────────────────────────────────────────────────

    public function testFindAllOrderedByNameReturnsSortedTeachers(): void
    {
        $this->persist(
            $this->makeTeacher('t.zeta',  'Zeta',  'Gomez'),
            $this->makeTeacher('t.alpha', 'Alpha', 'Gomez'),
            $this->makeTeacher('t.beta',  'Beta',  'Alvarez'),
        );

        $results = $this->repo->findAllOrderedByName();

        self::assertCount(3, $results);
        self::assertSame('t.beta',  $results[0]->getUsername()); // Alvarez/Beta
        self::assertSame('t.alpha', $results[1]->getUsername()); // Gomez/Alpha
        self::assertSame('t.zeta',  $results[2]->getUsername()); // Gomez/Zeta
    }

    // ── createFilteredOrderedByNameQuery ──────────────────────────────────────

    public function testCreateFilteredOrderedByNameQueryWithoutSearchReturnsAll(): void
    {
        $this->persist(
            $this->makeTeacher('t1', 'Ana',   'Garcia'),
            $this->makeTeacher('t2', 'Pedro', 'Lopez'),
        );

        $results = $this->repo->createFilteredOrderedByNameQuery()->getResult();

        self::assertCount(2, $results);
    }

    public function testCreateFilteredOrderedByNameQueryFiltersResults(): void
    {
        $this->persist(
            $this->makeTeacher('t1', 'Ana',   'Garcia'),
            $this->makeTeacher('t2', 'Pedro', 'Lopez'),
        );

        $results = $this->repo->createFilteredOrderedByNameQuery('Ana')->getResult();

        self::assertCount(1, $results);
        self::assertSame('t1', $results[0]->getUsername());
    }

    // ── findNoneQuery ─────────────────────────────────────────────────────────

    public function testFindNoneQueryReturnsEmptyResult(): void
    {
        $this->persist($this->makeTeacher('some.teacher'));

        $results = $this->repo->findNoneQuery()->getResult();

        self::assertCount(0, $results);
    }

    // ── findByProgrammeOrderedByName ──────────────────────────────────────────

    public function testFindByProgrammeOrderedByNameReturnsTutors(): void
    {
        $prog    = $this->makeProgrammeChain('41000003');
        $py      = (new ProgrammeYear())->setName('1.º DAM')->setProgramme($prog);
        $teacher = $this->makeTeacher('tutor.prog', 'Ana', 'Garcia');
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($py);
        $this->persist($py, $teacher, $group);
        $group->addTutor($teacher);
        $this->flush();

        $results = $this->repo->findByProgrammeOrderedByName($prog);

        self::assertCount(1, $results);
        self::assertSame('tutor.prog', $results[0]->getUsername());
    }

    public function testFindByProgrammeOrderedByNameReturnsGroupTeachers(): void
    {
        $prog    = $this->makeProgrammeChain('41000004');
        $py      = (new ProgrammeYear())->setName('1.º DAM')->setProgramme($prog);
        $teacher = $this->makeTeacher('grp.teacher', 'Pedro', 'Lopez');
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($py);
        $this->persist($py, $teacher, $group);
        $group->addTeacher($teacher);
        $this->flush();

        $results = $this->repo->findByProgrammeOrderedByName($prog);

        self::assertCount(1, $results);
        self::assertSame('grp.teacher', $results[0]->getUsername());
    }

    public function testFindByProgrammeOrderedByNameExcludesUnrelatedTeachers(): void
    {
        $prog      = $this->makeProgrammeChain('41000005');
        $unrelated = $this->makeTeacher('unrelated.one');
        $this->persist($unrelated);

        $results = $this->repo->findByProgrammeOrderedByName($prog);

        self::assertCount(0, $results);
    }

    public function testFindByProgrammeOrderedByNameDeduplicatesAcrossGroups(): void
    {
        $prog    = $this->makeProgrammeChain('41000006');
        $py      = (new ProgrammeYear())->setName('1.º DAM')->setProgramme($prog);
        $teacher = $this->makeTeacher('multi.group', 'Ana', 'Garcia');
        $g1      = (new Group())->setName('DAM1A')->setProgrammeYear($py);
        $g2      = (new Group())->setName('DAM1B')->setProgrammeYear($py);
        $this->persist($py, $teacher, $g1, $g2);
        $g1->addTutor($teacher);
        $g2->addTutor($teacher);
        $this->flush();

        $results = $this->repo->findByProgrammeOrderedByName($prog);

        self::assertCount(1, $results);
    }

    // ── findByUsername ───────────────────────────────────────────────────────

    public function testFindByUsernameReturnsTeacher(): void
    {
        $teacher = $this->makeTeacher('ana.garcia', 'Ana', 'García');
        $this->persist($teacher);

        $result = $this->repo->findByUsername('ana.garcia');

        self::assertNotNull($result);
        self::assertSame('ana.garcia', $result->getUsername());
    }

    public function testFindByUsernameReturnsNullForUnknownUsername(): void
    {
        $teacher = $this->makeTeacher('known.user');
        $this->persist($teacher);

        self::assertNull($this->repo->findByUsername('unknown.user'));
    }

    // ── findByFullName ────────────────────────────────────────────────────────

    public function testFindByFullNameReturnsTeacher(): void
    {
        $teacher = $this->makeTeacher('ana.garcia', 'Ana', 'García');
        $this->persist($teacher);

        $result = $this->repo->findByFullName('Ana', 'García');

        self::assertNotNull($result);
        self::assertSame('ana.garcia', $result->getUsername());
    }

    public function testFindByFullNameIsCaseInsensitive(): void
    {
        // Nombre sin acentos: LOWER() de SQLite sólo baja ASCII correctamente
        $teacher = $this->makeTeacher('carlos.lopez', 'Carlos', 'Lopez');
        $this->persist($teacher);

        $result = $this->repo->findByFullName('CARLOS', 'LOPEZ');

        self::assertNotNull($result);
        self::assertSame('carlos.lopez', $result->getUsername());
    }

    public function testFindByFullNameReturnsNullWhenNoMatch(): void
    {
        $teacher = $this->makeTeacher('ana.garcia', 'Ana', 'García');
        $this->persist($teacher);

        self::assertNull($this->repo->findByFullName('Ana', 'Martínez'));
    }

    // ── search ───────────────────────────────────────────────────────────────

    public function testSearchMatchesFirstName(): void
    {
        $teacher = $this->makeTeacher('ana.garcia', 'Ana', 'García');
        $this->persist($teacher);

        $results = $this->repo->search('Ana');

        self::assertCount(1, $results);
        self::assertSame('ana.garcia', $results[0]->getUsername());
    }

    public function testSearchMatchesLastName(): void
    {
        $teacher = $this->makeTeacher('carlos.lopez', 'Carlos', 'López');
        $this->persist($teacher);

        $results = $this->repo->search('López');

        self::assertCount(1, $results);
        self::assertSame('carlos.lopez', $results[0]->getUsername());
    }

    public function testSearchMatchesUsername(): void
    {
        $teacher = $this->makeTeacher('maria.ruiz', 'María', 'Ruiz');
        $this->persist($teacher);

        $results = $this->repo->search('ruiz');

        self::assertCount(1, $results);
        self::assertSame('maria.ruiz', $results[0]->getUsername());
    }

    public function testSearchIsCaseInsensitive(): void
    {
        $teacher = $this->makeTeacher('pedro.sanchez', 'Pedro', 'Sánchez');
        $this->persist($teacher);

        $results = $this->repo->search('PEDRO');

        self::assertCount(1, $results);
    }

    public function testSearchRespectsLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->persist($this->makeTeacher("teacher.{$i}", 'Busca', "Apellido{$i}"));
        }

        $results = $this->repo->search('Busca', 3);

        self::assertCount(3, $results);
    }

    public function testSearchReturnsEmptyWhenNoMatch(): void
    {
        $teacher = $this->makeTeacher('ana.garcia', 'Ana', 'García');
        $this->persist($teacher);

        self::assertCount(0, $this->repo->search('XYZ'));
    }

    public function testSearchReturnsOrderedByLastNameThenFirstName(): void
    {
        // Nombres sin acentos; 'Alvarez' aparece en lastName de t1 y t2
        $t1 = $this->makeTeacher('t1', 'Zlatan', 'Alvarez');
        $t2 = $this->makeTeacher('t2', 'Ana',    'Alvarez');
        $t3 = $this->makeTeacher('t3', 'Beatriz', 'Castro');
        $this->persist($t1, $t2, $t3);

        $results = $this->repo->search('Alvarez');

        self::assertCount(2, $results);
        // Orden: lastName ASC → Alvarez/Alvarez, luego firstName ASC → Ana < Zlatan
        self::assertSame('t2', $results[0]->getUsername()); // Ana Alvarez
        self::assertSame('t1', $results[1]->getUsername()); // Zlatan Alvarez
    }

    // ── countAll / countActive / countAdmins ──────────────────────────────────

    public function testCountAllReturnsZeroOnEmptyDatabase(): void
    {
        self::assertSame(0, $this->repo->countAll());
    }

    public function testCountAllReturnsCorrectCount(): void
    {
        $this->persist(
            $this->makeTeacher('t1'),
            $this->makeTeacher('t2'),
            $this->makeTeacher('t3'),
        );

        self::assertSame(3, $this->repo->countAll());
    }

    public function testCountActiveCountsOnlyActiveTeachers(): void
    {
        $active   = $this->makeTeacher('active.one');
        $inactive = $this->makeTeacher('inactive.one')->setActive(false);
        $this->persist($active, $inactive);

        self::assertSame(1, $this->repo->countActive());
    }

    public function testCountAdminsCountsOnlyAdminTeachers(): void
    {
        $admin    = $this->makeTeacher('admin.one')->setAdmin(true);
        $nonAdmin = $this->makeTeacher('regular.one');
        $this->persist($admin, $nonAdmin);

        self::assertSame(1, $this->repo->countAdmins());
    }

    // ── createByAcademicYearFilteredQuery ─────────────────────────────────────

    public function testCreateByAcademicYearFilteredQueryReturnsOnlyTeachersInYear(): void
    {
        $centre  = $this->makeCentre('41000001');
        $yearA   = $this->makeYear($centre, '2024-2025');
        $yearB   = $this->makeYear($centre, '2023-2024');
        $teacherA = $this->makeTeacher('teacher.a');
        $teacherB = $this->makeTeacher('teacher.b');
        $this->persist($centre, $yearA, $yearB, $teacherA, $teacherB);

        // Link teacher A to yearA, teacher B to yearB
        $yearA->addTeacher($teacherA);
        $yearB->addTeacher($teacherB);
        $this->flush();

        $results = $this->repo->createByAcademicYearFilteredQuery($yearA)->getResult();

        self::assertCount(1, $results);
        self::assertSame('teacher.a', $results[0]->getUsername());
    }

    public function testCreateByAcademicYearFilteredQueryFiltersbySearch(): void
    {
        $centre   = $this->makeCentre('41000002');
        $year     = $this->makeYear($centre, '2024-2025');
        $teacherA = $this->makeTeacher('ana.garcia', 'Ana', 'García');
        $teacherB = $this->makeTeacher('pedro.ruiz', 'Pedro', 'Ruiz');
        $this->persist($centre, $year, $teacherA, $teacherB);

        $year->addTeacher($teacherA);
        $year->addTeacher($teacherB);
        $this->flush();

        $results = $this->repo->createByAcademicYearFilteredQuery($year, 'Ana')->getResult();

        self::assertCount(1, $results);
        self::assertSame('ana.garcia', $results[0]->getUsername());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeTeacher(string $username, string $firstName = 'Test', string $lastName = 'Teacher'): Teacher
    {
        return (new Teacher(new PersonName($firstName, $lastName)))
            ->setUsername($username);
    }

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

    private function makeProgrammeChain(string $centreCode): Programme
    {
        $centre = $this->makeCentre($centreCode);
        $year   = $this->makeYear($centre, '2024-2025');
        $family = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $this->persist($centre, $year, $family, $prog);
        return $prog;
    }
}
