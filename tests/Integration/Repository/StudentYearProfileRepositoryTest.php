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
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\StudentYearProfile;
use App\Entity\Teacher;
use App\Repository\StayRepository;
use App\Repository\StudentRepository;
use App\Repository\StudentYearProfileRepository;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Uid\Uuid;

/** Consultas de la ficha del estudiante por curso y su efecto en los recuentos y permisos. */
class StudentYearProfileRepositoryTest extends RepositoryTestCase
{
    private StudentYearProfileRepository $profiles;
    private StudentRepository $students;
    private StayRepository $stays;
    private EducationalCentre $centre;
    private AcademicYear $year;
    private ProfessionalFamily $family;
    private Programme $programme;
    private Group $group;
    private Stay $stay;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var StudentYearProfileRepository $profiles */
        $profiles       = self::getContainer()->get(StudentYearProfileRepository::class);
        $this->profiles = $profiles;
        /** @var StudentRepository $students */
        $students       = self::getContainer()->get(StudentRepository::class);
        $this->students = $students;
        /** @var StayRepository $stays */
        $stays          = self::getContainer()->get(StayRepository::class);
        $this->stays    = $stays;

        $this->centre    = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $this->year      = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($this->centre);
        $this->family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($this->year);
        $this->programme = (new Programme())->setName('DAW')->setProfessionalFamily($this->family)->setAcademicYear($this->year);
        $level           = (new ProgrammeYear())->setName('Primero')->setProgramme($this->programme);
        $this->group     = (new Group())->setName('DAW1A')->setProgrammeYear($level);
        $this->stay      = (new Stay())->setName('FFEOE')->setAcademicYear($this->year)->addProgramme($this->programme)
            ->setStartDate(new \DateTimeImmutable('-10 days'))->setEndDate(new \DateTimeImmutable('+40 days'));
        $this->persist($this->centre, $this->year, $this->family, $this->programme, $level, $this->group, $this->stay);
        $this->centre->setActiveAcademicYear($this->year);
        $this->flush();
    }

    // ── Fichas ───────────────────────────────────────────────────────────────

    public function testFindByStudentAndYearIsScopedToTheYear(): void
    {
        $student = $this->student();
        $this->profile($student)->setHasCar(true);
        $other = (new AcademicYear())->setName('2026-2027')->setEducationalCentre($this->centre);
        $this->persist($other);
        $this->flush();
        $this->em->clear();

        $student = $this->em->getReference(Student::class, $student->getId());
        $found   = $this->profiles->findByStudentAndYear($student, $this->em->getReference(AcademicYear::class, $this->year->getId()));
        $other   = $this->profiles->findByStudentAndYear($student, $this->em->getReference(AcademicYear::class, $other->getId()));

        self::assertTrue($found?->hasCar());
        self::assertNull($other, 'cada curso empieza con la ficha vacía');
    }

    public function testFindByStayReturnsTheProfilesOfTheEnrolledStudentsOnly(): void
    {
        $enrolled = $this->student();
        $this->profile($enrolled)->setHasCar(true);
        $notEnrolled = $this->student(enrol: false);
        $this->profile($notEnrolled)->setHasCar(true);
        $this->flush();
        $this->em->clear();

        $stay = $this->em->getReference(Stay::class, $this->stay->getId());
        $map  = $this->profiles->findByStay($stay);

        self::assertSame([$enrolled->getId()->toRfc4122()], array_keys($map));
    }

    public function testFindMissingNussIgnoresThoseWhoDoNotDoTheFfeoeAndThoseNotInformed(): void
    {
        $missing = $this->student();
        $this->profile($missing)->setHasNuss(false);
        $excluded = $this->student();
        $this->profile($excluded)->setHasNuss(false)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $has = $this->student();
        $this->profile($has)->setHasNuss(true);
        $unknown = $this->student();
        $this->profile($unknown);
        $this->flush();
        $this->em->clear();

        $ids = $this->profiles->findMissingNussStudentIds($this->em->getReference(AcademicYear::class, $this->year->getId()));

        self::assertSame([$missing->getId()->toRfc4122()], array_keys($ids));
    }

    // ── Recuentos: quien no realiza la FFEOE no cuenta ───────────────────────

    public function testStudentsWhoDoNotDoTheFfeoeAreLeftOutOfTheCounts(): void
    {
        $this->student();
        $this->student();
        $excluded = $this->student();
        $this->profile($excluded)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $this->flush();
        $this->em->clear();

        $year = $this->em->getReference(AcademicYear::class, $this->year->getId());
        $rows = $this->stays->countStudentsWithoutPositionByStay($year);
        self::assertCount(1, $rows);
        self::assertSame(2, $rows[0]['students_without_position']);

        $stats = $this->stays->findStatsForStays([$this->em->getReference(Stay::class, $this->stay->getId())]);
        self::assertSame(2, $stats[$this->stay->getId()->toRfc4122()]['students']);
    }

    public function testEveryoneCountsWhenThereAreNoProfiles(): void
    {
        $this->student();
        $this->student();
        $this->em->clear();

        $rows = $this->stays->countStudentsWithoutPositionByStay($this->em->getReference(AcademicYear::class, $this->year->getId()));

        self::assertSame(2, $rows[0]['students_without_position']);
    }

    // ── Lista de estudiantes: filtro por ficha ───────────────────────────────

    public function testTheStudentListCanBeFilteredByNussAndByFfeoe(): void
    {
        $missing = $this->student();
        $this->profile($missing)->setHasNuss(false);
        $excluded = $this->student();
        $this->profile($excluded)->setHasNuss(false)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $this->student();
        $this->flush();
        $this->em->clear();

        $centre = $this->em->getReference(EducationalCentre::class, $this->centre->getId());
        $year   = $this->em->getReference(AcademicYear::class, $this->year->getId());
        $ids    = fn (string $filter): array => array_map(
            static fn (Student $s): string => $s->getId()->toRfc4122(),
            $this->students->createByCentreFilteredQuery($centre, '', '', '', 'asc', $year, $filter)->getResult(),
        );

        self::assertSame([$missing->getId()->toRfc4122()], $ids(StudentYearProfile::FILTER_NO_NUSS), 'sin NUSS: solo quien realiza la FFEOE');
        self::assertSame([$excluded->getId()->toRfc4122()], $ids(StudentYearProfile::FILTER_NO_FFEOE));
        self::assertCount(3, $ids(''));
    }

    // ── Responsables del estudiante ──────────────────────────────────────────

    public function testCoordinatorHeadAndGroupTutorAreResponsibleButAGroupTeacherIsNot(): void
    {
        $student     = $this->student();
        $coordinator = $this->teacher('coordinadora');
        $head        = $this->teacher('jefe');
        $tutor       = $this->teacher('tutora');
        $teacher     = $this->teacher('docente');
        $stranger    = $this->teacher('ajeno');
        $this->programme->addCoordinator($coordinator);
        $this->family->setHead($head);
        $this->group->addTutor($tutor);
        $this->group->addTeacher($teacher);
        $this->flush();
        $this->em->clear();

        $student = $this->em->getReference(Student::class, $student->getId());
        $year    = $this->em->getReference(AcademicYear::class, $this->year->getId());
        $is      = fn (Teacher $t): bool => $this->students->isResponsibleInYear($this->em->getReference(Teacher::class, $t->getId()), $student, $year);

        self::assertTrue($is($coordinator));
        self::assertTrue($is($head));
        self::assertTrue($is($tutor));
        self::assertFalse($is($teacher), 'impartir en el grupo no basta');
        self::assertFalse($is($stranger));
    }

    public function testResponsibleStudentIdsListsOnlyTheirOwnStudents(): void
    {
        $mine = $this->student();
        $otherGroup = (new Group())->setName('DAW1B')->setProgrammeYear($this->group->getProgrammeYear());
        $this->persist($otherGroup);
        $theirs = (new Student(new PersonName('Otro', 'Grupo')))->setStudentId('X-1');
        $otherGroup->addStudent($theirs);
        $this->persist($theirs);
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->em->clear();

        $ids = $this->students->findResponsibleStudentIds(
            $this->em->getReference(Teacher::class, $tutor->getId()),
            $this->em->getReference(AcademicYear::class, $this->year->getId()),
        );

        self::assertSame([$mine->getId()->toRfc4122()], $ids);
    }

    public function testIsInYearLooksAtTheGroupsOfTheYear(): void
    {
        $student  = $this->student();
        $outsider = (new Student(new PersonName('Sin', 'Grupo')))->setStudentId('X-2');
        $this->persist($outsider);
        $this->em->clear();

        $year = $this->em->getReference(AcademicYear::class, $this->year->getId());

        self::assertTrue($this->students->isInYear($this->em->getReference(Student::class, $student->getId()), $year));
        self::assertFalse($this->students->isInYear($this->em->getReference(Student::class, $outsider->getId()), $year));
    }

    // ── Ayudantes ────────────────────────────────────────────────────────────

    private function student(bool $enrol = true): Student
    {
        $student = (new Student(new PersonName('Alumno' . ++$this->seq, 'Apellido')))->setStudentId('2025-' . $this->seq);
        $this->group->addStudent($student);
        if ($enrol) {
            $this->stay->addStudent($student);
        }
        $this->persist($student);

        return $student;
    }

    private function profile(Student $student): StudentYearProfile
    {
        $profile = new StudentYearProfile($student, $this->year);
        $this->em->persist($profile);

        return $profile;
    }

    private function teacher(string $username): Teacher
    {
        $teacher = (new Teacher(new PersonName('Test', 'Teacher ' . ++$this->seq)))->setUsername($username);
        $this->persist($teacher);

        return $teacher;
    }
}
