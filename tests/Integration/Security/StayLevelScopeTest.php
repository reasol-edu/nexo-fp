<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Repository\ProgrammeYearRepository;
use App\Repository\StayRepository;
use App\Repository\StudentRepository;
use App\Repository\TrainingPositionRepository;
use App\Security\StayScope;
use App\Security\Voter\PositionAssignment;
use App\Security\Voter\StayVoter;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * La gestión de una estancia es por nivel: quien coordina solo 2.º de una enseñanza no gestiona a los
 * estudiantes de 1.º, no asigna sus puestos ni edita su ficha, aunque la enseñanza participe en la
 * estancia. La jefatura de familia y la tutoría de grupo conservan su alcance.
 *
 * Estas pruebas recorren las consultas DQL de los repositorios (también se ejecutan contra PostgreSQL,
 * MySQL y MariaDB con la variable DATABASE_URL).
 */
class StayLevelScopeTest extends RepositoryTestCase
{
    private StayVoter $voter;
    private StayRepository $stays;
    private ProgrammeYearRepository $levels;
    private StudentRepository $students;
    private TrainingPositionRepository $positions;
    private StayScope $scope;

    private AcademicYear $year;
    private ProgrammeYear $first;
    private ProgrammeYear $second;
    private Stay $both;
    private Stay $onlySecond;
    private Stay $onlyFirst;
    private Student $firstStudent;
    private Student $secondStudent;
    private Teacher $coordSecond;
    private Teacher $head;
    private Teacher $tutorFirst;
    private Teacher $nobody;

    protected function setUp(): void
    {
        parent::setUp();

        $c = self::getContainer();
        /** @var StayVoter $voter */
        $voter       = $c->get(StayVoter::class);
        $this->voter = $voter;
        /** @var StayRepository $stays */
        $stays        = $c->get(StayRepository::class);
        $this->stays  = $stays;
        /** @var ProgrammeYearRepository $levels */
        $levels       = $c->get(ProgrammeYearRepository::class);
        $this->levels = $levels;
        /** @var StudentRepository $students */
        $students       = $c->get(StudentRepository::class);
        $this->students = $students;
        /** @var TrainingPositionRepository $positions */
        $positions       = $c->get(TrainingPositionRepository::class);
        $this->positions = $positions;
        /** @var StayScope $scope */
        $scope       = $c->get(StayScope::class);
        $this->scope = $scope;

        $this->makeScenario();
    }

    // ── Coordinación de un solo nivel ────────────────────────────────────────

    public function testACoordinatorOfSecondYearOnlyManagesSecondYearStudents(): void
    {
        self::assertTrue($this->scope->canManageStudent($this->coordSecond, $this->both, $this->secondStudent));
        self::assertFalse($this->scope->canManageStudent($this->coordSecond, $this->both, $this->firstStudent));
        self::assertTrue($this->scope->canManageProgramme($this->coordSecond, $this->both, $this->first->getProgramme()), 'gestiona algún nivel de DAW');
    }

    public function testACoordinatorOfSecondYearCannotAssignAPositionToAFirstYearStudent(): void
    {
        $position = $this->freePosition($this->both);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($this->coordSecond), new PositionAssignment($position, $this->secondStudent), [StayVoter::ASSIGN]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($this->coordSecond), new PositionAssignment($position, $this->firstStudent), [StayVoter::ASSIGN]),
        );
    }

    public function testTheFamilyHeadAndAdminsAssignToBothLevels(): void
    {
        $position = $this->freePosition($this->both);

        foreach ([$this->firstStudent, $this->secondStudent] as $student) {
            self::assertSame(
                VoterInterface::ACCESS_GRANTED,
                $this->voter->vote($this->token($this->head), new PositionAssignment($position, $student), [StayVoter::ASSIGN]),
            );
        }
    }

    public function testManagingAStayRequiresManagingAtLeastOneOfItsLevels(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->coordSecond), $this->both, [StayVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->coordSecond), $this->onlySecond, [StayVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($this->coordSecond), $this->onlyFirst, [StayVoter::MANAGE]), 'la estancia solo de 1.º no es suya');
    }

    public function testDeletingAStayRequiresManagingEveryOneOfItsLevels(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($this->coordSecond), $this->both, [StayVoter::DELETE]), 'comparte la estancia con la coordinación de 1.º');
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->coordSecond), $this->onlySecond, [StayVoter::DELETE]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->head), $this->both, [StayVoter::DELETE]));
    }

    // ── Ficha del estudiante ─────────────────────────────────────────────────

    public function testTheProfileOfAStudentIsEditedByThatLevelsCoordinatorOnly(): void
    {
        self::assertTrue($this->students->isResponsibleInYear($this->coordSecond, $this->secondStudent, $this->year));
        self::assertFalse($this->students->isResponsibleInYear($this->coordSecond, $this->firstStudent, $this->year), 'solo coordina 2.º');
        self::assertSame(
            [$this->secondStudent->getId()->toRfc4122()],
            $this->students->findResponsibleStudentIds($this->coordSecond, $this->year),
        );
    }

    public function testTheFamilyHeadAndTheGroupTutorKeepTheirProfileScope(): void
    {
        self::assertTrue($this->students->isResponsibleInYear($this->head, $this->firstStudent, $this->year));
        self::assertTrue($this->students->isResponsibleInYear($this->head, $this->secondStudent, $this->year));

        self::assertTrue($this->students->isResponsibleInYear($this->tutorFirst, $this->firstStudent, $this->year));
        self::assertFalse($this->students->isResponsibleInYear($this->tutorFirst, $this->secondStudent, $this->year));

        self::assertFalse($this->students->isResponsibleInYear($this->nobody, $this->firstStudent, $this->year));
    }

    // ── Consultas por nivel ──────────────────────────────────────────────────

    public function testLevelsOfAStayCoordinatedOrHeadedByATeacher(): void
    {
        self::assertSame([$this->second->getId()->toRfc4122()], $this->ids($this->levels->findCoordinatedByInStay($this->coordSecond, $this->both)));
        self::assertSame([], $this->levels->findCoordinatedByInStay($this->coordSecond, $this->onlyFirst));
        self::assertEqualsCanonicalizing(
            [$this->first->getId()->toRfc4122(), $this->second->getId()->toRfc4122()],
            $this->ids($this->levels->findHeadedByInStay($this->head, $this->both)),
        );
        self::assertSame([$this->second->getId()->toRfc4122()], $this->ids($this->levels->findHeadedByInStay($this->head, $this->onlySecond)), 'solo los niveles que participan en la estancia');
    }

    public function testManagedLevelsOfTheYearForTheDashboard(): void
    {
        $rows = $this->levels->findManagedByTeacherInYear($this->year, $this->coordSecond);

        self::assertCount(1, $rows);
        self::assertSame($this->second->getId()->toRfc4122(), (string) $rows[0]['level']);
        self::assertSame('DAW', $rows[0]['name']);
        self::assertFalse($rows[0]['headed']);

        $headRows = $this->levels->findManagedByTeacherInYear($this->year, $this->head);
        self::assertCount(2, $headRows);
        self::assertTrue($headRows[0]['headed']);
        self::assertSame([], $this->levels->findManagedByTeacherInYear($this->year, $this->nobody));
    }

    public function testTheStayListShowsTheStaysThatIncludeALevelOfTheViewer(): void
    {
        $names = static fn (array $stays): array => array_values(array_map(static fn (Stay $s): string => $s->getName(), $stays));

        $coordinator = $names($this->stays->createByCentreFilteredQuery($this->year, viewer: $this->coordSecond)->getResult());
        sort($coordinator);
        self::assertSame(['Ambos', 'Solo 2.º'], $coordinator, 'la estancia solo de 1.º no aparece');

        $tutor = $names($this->stays->createByCentreFilteredQuery($this->year, viewer: $this->tutorFirst)->getResult());
        sort($tutor);
        self::assertSame(['Ambos', 'Solo 1.º'], $tutor, 'la tutoría de un grupo de 1.º no ve la estancia solo de 2.º');

        $head = $names($this->stays->createByCentreFilteredQuery($this->year, viewer: $this->head)->getResult());
        self::assertCount(3, $head);

        self::assertSame([], $this->stays->createByCentreFilteredQuery($this->year, viewer: $this->nobody)->getResult());
    }

    public function testStayLevelsAreExposedPerProgrammeAndNeverDuplicated(): void
    {
        self::assertCount(2, $this->both->getProgrammeYears());
        self::assertSame(['DAW'], array_map(static fn (Programme $p): string => $p->getName(), $this->both->getProgrammes()->toArray()));
        self::assertSame([$this->second], $this->onlySecond->getProgrammeYears()->toArray());
        self::assertSame([$this->second], $this->both->getProgrammeYearsOfStudent($this->secondStudent));
    }

    // ── Escenario ────────────────────────────────────────────────────────────

    private function makeScenario(): void
    {
        $centre = (new EducationalCentre())->setCode('41009999')->setName('IES Niveles')->setCity('Sevilla');
        $this->year = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);

        $this->head        = $this->teacher('jefa');
        $this->coordSecond = $this->teacher('coord.segundo');
        $this->tutorFirst  = $this->teacher('tutor.primero');
        $this->nobody      = $this->teacher('nadie');

        $family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($this->year)->setHead($this->head);
        $programme = (new Programme())->setName('DAW')->setAcademicYear($this->year)->setProfessionalFamily($family);
        $this->first  = (new ProgrammeYear())->setName('1.º DAW')->setProgramme($programme);
        $this->second = (new ProgrammeYear())->setName('2.º DAW')->setProgramme($programme);
        $this->second->addCoordinator($this->coordSecond);

        $firstGroup  = (new Group())->setName('DAW1A')->setProgrammeYear($this->first)->addTutor($this->tutorFirst);
        $secondGroup = (new Group())->setName('DAW2A')->setProgrammeYear($this->second);
        $this->firstStudent  = (new Student(new PersonName('Ana', 'Primero')))->setStudentId('2025-001');
        $this->secondStudent = (new Student(new PersonName('Eva', 'Segundo')))->setStudentId('2025-002');
        $firstGroup->addStudent($this->firstStudent);
        $secondGroup->addStudent($this->secondStudent);

        $this->both       = $this->stay('Ambos', $this->first, $this->second);
        $this->onlySecond = $this->stay('Solo 2.º', $this->second);
        $this->onlyFirst  = $this->stay('Solo 1.º', $this->first);
        $this->both->addStudent($this->firstStudent)->addStudent($this->secondStudent);

        $this->persist(
            $centre, $this->year, $this->head, $this->coordSecond, $this->tutorFirst, $this->nobody, $family, $programme,
            $this->first, $this->second, $firstGroup, $secondGroup, $this->firstStudent, $this->secondStudent,
            $this->both, $this->onlySecond, $this->onlyFirst,
        );
        $centre->setActiveAcademicYear($this->year);
        $this->flush();
    }

    private function stay(string $name, ProgrammeYear ...$levels): Stay
    {
        $stay = (new Stay())->setName($name)->setAcademicYear($this->year)
            ->setStartDate(new \DateTimeImmutable('-10 days'))->setEndDate(new \DateTimeImmutable('+60 days'));
        foreach ($levels as $level) {
            $stay->addProgrammeYear($level);
        }

        return $stay;
    }

    private function freePosition(Stay $stay): TrainingPosition
    {
        $position = (new TrainingPosition())->setStay($stay);
        $this->persist($position);

        return $position;
    }

    private function teacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Docente', $username)))->setUsername($username);
    }

    /**
     * @param list<ProgrammeYear> $levels
     *
     * @return list<string>
     */
    private function ids(array $levels): array
    {
        return array_values(array_map(static fn (ProgrammeYear $l): string => $l->getId()->toRfc4122(), $levels));
    }

    private function token(Teacher $teacher): TokenInterface
    {
        $stub = $this->createStub(TokenInterface::class);
        $stub->method('getUser')->willReturn($teacher);

        return $stub;
    }
}
