<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\AcademicYear;
use App\Entity\Company;
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
use App\Entity\Workcenter;
use App\Repository\CompanyRepository;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Security\StayScope;
use App\Security\Voter\PositionAssignment;
use App\Security\Voter\StayVoter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Matriz de permisos de una estancia con una o varias enseñanzas. Coordinación y jefatura de
 * familia se simulan con los repositorios que alimentan {@see StayScope}.
 */
#[AllowMockObjectsWithoutExpectations]
class StayVoterTest extends TestCase
{
    private ProgrammeRepository&MockObject $programmes;
    private ProfessionalFamilyRepository&MockObject $families;
    private GroupRepository&MockObject $groups;
    private CompanyRepository&MockObject $companies;
    private StayVoter $voter;

    protected function setUp(): void
    {
        $this->programmes = $this->createMock(ProgrammeRepository::class);
        $this->families   = $this->createMock(ProfessionalFamilyRepository::class);
        $this->groups     = $this->createMock(GroupRepository::class);
        $this->companies  = $this->createMock(CompanyRepository::class);
        $this->voter      = new StayVoter(
            $this->programmes,
            $this->groups,
            $this->companies,
            new StayScope($this->programmes),
            $this->families,
        );
    }

    // ── supports() ──────────────────────────────────────────────────────────

    public function testSupportsStayAttributesWithStay(): void
    {
        foreach ([StayVoter::VIEW, StayVoter::VIEW_UNASSIGNED, StayVoter::MANAGE, StayVoter::DELETE, StayVoter::ADD_POSITION] as $attribute) {
            $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [$attribute]);

            self::assertSame(VoterInterface::ACCESS_GRANTED, $result, $attribute);
        }
    }

    public function testSupportsManagePositionWithTrainingPosition(): void
    {
        $position = $this->position($this->stay(), $this->company());

        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testSupportsAssignWithPositionAssignment(): void
    {
        $stay       = $this->stay();
        $assignment = new PositionAssignment($this->position($stay, $this->company()), $this->student());

        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $assignment, [StayVoter::ASSIGN]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testSupportsCreateWithCentre(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->centre(), [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAbstainsOnUnknownAttribute(): void
    {
        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), ['unknown']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsWhenSubjectDoesNotMatchAttribute(): void
    {
        $admin = $this->token($this->teacher(admin: true));

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($admin, $this->centre(), [StayVoter::MANAGE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($admin, $this->centre(), [StayVoter::ADD_POSITION]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($admin, $this->stay(), [StayVoter::CREATE]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($admin, $this->stay(), [StayVoter::MANAGE_POSITION]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($admin, $this->stay(), [StayVoter::ASSIGN]));
    }

    public function testDeniesWhenUserIsNotATeacher(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $this->stay(), [StayVoter::VIEW]));
    }

    // ── VIEW ─────────────────────────────────────────────────────────────────

    public function testViewGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);

        $this->programmes->expects($this->never())->method('findCoordinatedByInStay');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW]));
    }

    public function testViewGrantedToCoordinatorOfAnyProgramme(): void
    {
        $stay = $this->stayWithTwoProgrammes();
        $this->coordinates($stay->getProgrammesSorted()[1]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $stay, [StayVoter::VIEW]));
    }

    public function testViewGrantedToFamilyHead(): void
    {
        $stay = $this->stay();
        $this->programmes->method('findHeadedByInStay')->willReturn([$stay->getProgrammesSorted()[0]]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $stay, [StayVoter::VIEW]));
    }

    public function testViewGrantedToTeacherOfAnyProgramme(): void
    {
        $this->groups->method('isTeacherInStayProgrammes')->willReturn(true);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]));
    }

    public function testViewGrantedToLiaisonWithPositionInStay(): void
    {
        $this->companies->method('hasLiaisonPositionInStay')->willReturn(true);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]));
    }

    public function testViewDeniedToUnrelatedTeacher(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]));
    }

    // ── MANAGE / VIEW_UNASSIGNED / ADD_POSITION ──────────────────────────────

    public function testManageGrantedToCoordinatorOfAnyProgramme(): void
    {
        $stay = $this->stayWithTwoProgrammes();
        $this->coordinates($stay->getProgrammesSorted()[0]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $stay, [StayVoter::MANAGE]));
    }

    public function testManageDeniedToGroupTeacherAndLiaison(): void
    {
        $this->groups->method('isTeacherInStayProgrammes')->willReturn(true);
        $this->companies->method('hasLiaisonInCentre')->willReturn(true);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::MANAGE]));
    }

    public function testViewUnassignedGrantedToCoordinatorAndLiaisonButNotGroupTeacher(): void
    {
        $stay = $this->stay();

        $this->companies->method('hasLiaisonInCentre')->willReturn(true);
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $stay, [StayVoter::VIEW_UNASSIGNED]));
    }

    public function testViewUnassignedDeniedToGroupTeacher(): void
    {
        $this->groups->method('isTeacherInStayProgrammes')->willReturn(true);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW_UNASSIGNED]));
    }

    public function testAddPositionGrantedToLiaisonInCentre(): void
    {
        $this->companies->method('hasLiaisonInCentre')->willReturn(true);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::ADD_POSITION]));
    }

    // ── DELETE ───────────────────────────────────────────────────────────────

    public function testDeleteGrantedToCoordinatorOfSingleProgrammeStay(): void
    {
        $stay = $this->stay();
        $this->coordinates($stay->getProgrammesSorted()[0]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $stay, [StayVoter::DELETE]));
    }

    public function testDeleteDeniedToCoordinatorOfOnlyOneOfSeveralProgrammes(): void
    {
        $stay = $this->stayWithTwoProgrammes();
        $this->coordinates($stay->getProgrammesSorted()[0]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($this->teacher()), $stay, [StayVoter::DELETE]));
    }

    public function testDeleteGrantedToWhoManagesAllProgrammes(): void
    {
        $stay = $this->stayWithTwoProgrammes();
        $this->coordinates(...$stay->getProgrammesSorted());

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $stay, [StayVoter::DELETE]));
    }

    public function testDeleteGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stayWithTwoProgrammes();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($teacher), $stay, [StayVoter::DELETE]));
    }

    // ── MANAGE_POSITION ──────────────────────────────────────────────────────

    public function testManageFreePositionGrantedToAnyCoordinatorOfTheStay(): void
    {
        $stay     = $this->stayWithTwoProgrammes();
        $position = $this->position($stay, $this->company());
        $this->coordinates($stay->getProgrammesSorted()[1]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $position, [StayVoter::MANAGE_POSITION]));
    }

    public function testManageAssignedPositionDeniedToCoordinatorOfAnotherProgramme(): void
    {
        [$stay, $dawStudent] = $this->twoProgrammeStayWithStudents();
        $position = $this->position($stay, $this->company())->setStudent($dawStudent);
        $this->coordinates($stay->getProgrammesSorted()[0]); // DAM

        // El alumno es de DAW: quien coordina DAM no gestiona su puesto.
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($this->teacher()), $position, [StayVoter::MANAGE_POSITION]));
    }

    public function testManageAssignedPositionGrantedToCoordinatorOfTheStudent(): void
    {
        [$stay, $dawStudent] = $this->twoProgrammeStayWithStudents();
        $position = $this->position($stay, $this->company())->setStudent($dawStudent);
        $this->coordinates($stay->getProgrammesSorted()[1]); // DAW

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $position, [StayVoter::MANAGE_POSITION]));
    }

    public function testManageFreePositionGrantedToLiaisonOfItsCompany(): void
    {
        $teacher  = $this->teacher();
        $position = $this->position($this->stay(), $this->company($teacher));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION]));
    }

    public function testManageAssignedPositionDeniedToLiaison(): void
    {
        $teacher  = $this->teacher();
        $position = $this->position($this->stay(), $this->company($teacher))->setStudent($this->student());

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION]));
    }

    // ── ASSIGN ───────────────────────────────────────────────────────────────

    public function testAssignGrantedToCoordinatorOfTheStudentsProgramme(): void
    {
        [$stay, $dawStudent] = $this->twoProgrammeStayWithStudents();
        $position = $this->position($stay, $this->company());
        $this->coordinates($stay->getProgrammesSorted()[1]); // DAW

        $result = $this->voter->vote($this->token($this->teacher()), new PositionAssignment($position, $dawStudent), [StayVoter::ASSIGN]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAssignDeniedToCoordinatorOfAnotherProgramme(): void
    {
        [$stay, $dawStudent] = $this->twoProgrammeStayWithStudents();
        $position = $this->position($stay, $this->company());
        $this->coordinates($stay->getProgrammesSorted()[0]); // DAM

        $result = $this->voter->vote($this->token($this->teacher()), new PositionAssignment($position, $dawStudent), [StayVoter::ASSIGN]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAssignGrantedToHeadOfTheStudentsFamily(): void
    {
        [$stay, $dawStudent] = $this->twoProgrammeStayWithStudents();
        $position = $this->position($stay, $this->company());
        $this->programmes->method('findHeadedByInStay')->willReturn([$stay->getProgrammesSorted()[1]]);

        $result = $this->voter->vote($this->token($this->teacher()), new PositionAssignment($position, $dawStudent), [StayVoter::ASSIGN]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAssignGrantedToCentreAdmin(): void
    {
        [$stay, $dawStudent] = $this->twoProgrammeStayWithStudents();
        $teacher = $this->teacher();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);

        $result = $this->voter->vote($this->token($teacher), new PositionAssignment($this->position($stay, $this->company()), $dawStudent), [StayVoter::ASSIGN]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAssignGrantedToLiaisonForFreePositionOfItsCompany(): void
    {
        [$stay, $dawStudent] = $this->twoProgrammeStayWithStudents();
        $teacher  = $this->teacher();
        $position = $this->position($stay, $this->company($teacher));

        $result = $this->voter->vote($this->token($teacher), new PositionAssignment($position, $dawStudent), [StayVoter::ASSIGN]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAssignDeniedToUnrelatedTeacher(): void
    {
        [$stay, $dawStudent] = $this->twoProgrammeStayWithStudents();

        $result = $this->voter->vote($this->token($this->teacher()), new PositionAssignment($this->position($stay, $this->company()), $dawStudent), [StayVoter::ASSIGN]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testStudentWithoutGroupInTheStayIsManagedByAnyCoordinator(): void
    {
        $stay = $this->stayWithTwoProgrammes();
        $this->coordinates($stay->getProgrammesSorted()[0]);
        $orphan = $this->student();

        $result = $this->voter->vote($this->token($this->teacher()), new PositionAssignment($this->position($stay, $this->company()), $orphan), [StayVoter::ASSIGN]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ── CREATE ───────────────────────────────────────────────────────────────

    public function testCreateGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $centre->addAdmin($teacher);

        $this->programmes->expects($this->never())->method('isCoordinatorInCentre');

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE]));
    }

    public function testCreateGrantedToCoordinator(): void
    {
        $this->programmes->method('isCoordinatorInCentre')->willReturn(true);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $this->centre(), [StayVoter::CREATE]));
    }

    public function testCreateGrantedToFamilyHead(): void
    {
        $this->families->method('isFamilyHeadInCentre')->willReturn(true);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token($this->teacher()), $this->centre(), [StayVoter::CREATE]));
    }

    public function testCreateDeniedToUnrelatedTeacher(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token($this->teacher()), $this->centre(), [StayVoter::CREATE]));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** El docente de la prueba coordina estas enseñanzas de la estancia. */
    private function coordinates(Programme ...$programmes): void
    {
        $this->programmes->method('findCoordinatedByInStay')->willReturn(array_values($programmes));
    }

    private function teacher(bool $admin = false): Teacher
    {
        return $this->withId((new Teacher(new PersonName('Ana', 'García')))
            ->setUsername('ana.garcia')
            ->setAdmin($admin));
    }

    private function student(): Student
    {
        return $this->withId((new Student(new PersonName('Luis', 'Pérez')))->setStudentId('S-' . random_int(1000, 9999)));
    }

    private function centre(): EducationalCentre
    {
        return $this->withId((new EducationalCentre())
            ->setCode('41012345')
            ->setName('IES Test')
            ->setCity('Sevilla'));
    }

    private function stay(): Stay
    {
        $stay = $this->emptyStay();
        $year = $stay->getAcademicYear();
        $stay->addProgramme($this->programme('DAW', $year));

        return $stay;
    }

    private function stayWithTwoProgrammes(): Stay
    {
        $stay = $this->emptyStay();
        $year = $stay->getAcademicYear();
        $stay->addProgramme($this->programme('DAW', $year));
        $stay->addProgramme($this->programme('DAM', $year));

        return $stay;
    }

    /**
     * Estancia con DAM (índice 0 al ordenar por nombre) y DAW (índice 1), y un alumno de DAW.
     *
     * @return array{0: Stay, 1: Student}
     */
    private function twoProgrammeStayWithStudents(): array
    {
        $stay    = $this->stayWithTwoProgrammes();
        $daw     = $stay->getProgrammesSorted()[1];
        $level   = $this->withId((new ProgrammeYear())->setName('1.º DAW')->setProgramme($daw));
        $group   = $this->withId((new Group())->setName('1DAW')->setProgrammeYear($level));
        $student = $this->student();
        $student->addGroup($group);

        return [$stay, $student];
    }

    private function emptyStay(): Stay
    {
        $year = $this->withId((new AcademicYear())->setName('2024-2025')->setEducationalCentre($this->centre()));

        $stay = $this->withId(new Stay());
        $stay->setName('Estancia')
             ->setAcademicYear($year)
             ->setStartDate(new \DateTimeImmutable('2025-03-01'))
             ->setEndDate(new \DateTimeImmutable('2025-06-30'));

        return $stay;
    }

    private function programme(string $name, AcademicYear $year): Programme
    {
        $family = $this->withId((new ProfessionalFamily())->setName('Informática')->setAcademicYear($year));

        return $this->withId((new Programme())->setName($name)->setAcademicYear($year)->setProfessionalFamily($family));
    }

    private function company(Teacher ...$liaisons): Company
    {
        $company = $this->withId((new Company())
            ->setName('Empresa Test SL')
            ->setVatNumber('B12345678'));
        foreach ($liaisons as $liaison) {
            $company->addLiaison($liaison);
        }

        return $company;
    }

    private function position(Stay $stay, Company $company): TrainingPosition
    {
        $workcenter = (new Workcenter())
            ->setName('Sede Test')
            ->setCompany($company);

        return $this->withId((new TrainingPosition())
            ->setStay($stay)
            ->setWorkcenter($workcenter)
            ->setStartDate(new \DateTimeImmutable('2025-03-01'))
            ->setEndDate(new \DateTimeImmutable('2025-06-30')));
    }

    /** Asigna un UUID a una entidad sin persistir (en un test unitario no hay generador de ids). */
    private function withId(object $entity): object
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, Uuid::v4());

        return $entity;
    }

    private function token(mixed $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
