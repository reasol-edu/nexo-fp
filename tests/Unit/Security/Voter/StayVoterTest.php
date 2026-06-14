<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Programme;
use App\Entity\ProfessionalFamily;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\Workcenter;
use App\Repository\CompanyRepository;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Security\Voter\StayVoter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

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
        $this->voter      = new StayVoter($this->programmes, $this->families, $this->groups, $this->companies);
    }

    // ── supports() ──────────────────────────────────────────────────────────

    public function testSupportsManageWithStay(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [StayVoter::MANAGE]);

        self::assertNotSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testSupportsManagePositionWithTrainingPosition(): void
    {
        $position = $this->position($this->stay(), $this->company());

        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $position, [StayVoter::MANAGE_POSITION]);

        self::assertNotSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testSupportsCreateWithCentre(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->centre(), [StayVoter::CREATE]);

        self::assertNotSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsOnUnknownAttribute(): void
    {
        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), ['unknown']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsWhenManageReceivesCentreInsteadOfStay(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->centre(), [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsWhenCreateReceivesStayInsteadOfCentre(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsWhenManagePositionReceivesStay(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testSupportsAddPositionWithStay(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [StayVoter::ADD_POSITION]);

        self::assertNotSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsWhenAddPositionReceivesCentre(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->centre(), [StayVoter::ADD_POSITION]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // ── VIEW: acceso por rol ─────────────────────────────────────────────────

    public function testViewGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);

        $this->programmes->expects($this->never())->method('isCoordinatorOf');

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedToCoordinator(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(true);
        $this->families->expects($this->never())->method('isFamilyHeadOfProgramme');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedToFamilyHead(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(true);
        $this->groups->expects($this->never())->method('isTeacherInProgramme');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedToTeacherInProgramme(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->groups->method('isTeacherInProgramme')->willReturn(true);
        $this->companies->expects($this->never())->method('hasLiaisonPositionInStay');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedToLiaisonWhoHasPositionInStay(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->groups->method('isTeacherInProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonPositionInStay')->willReturn(true);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewDeniedToLiaisonWithNoPositionInStay(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->groups->method('isTeacherInProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonPositionInStay')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testViewDeniedToUnrelatedTeacher(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->groups->method('isTeacherInProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonPositionInStay')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── Administrador global ─────────────────────────────────────────────────

    public function testGlobalAdminIsGrantedView(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGlobalAdminIsGrantedManage(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGlobalAdminIsGrantedCreate(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->centre(), [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGlobalAdminIsGrantedAddPosition(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [StayVoter::ADD_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGlobalAdminIsGrantedManagePosition(): void
    {
        $position = $this->position($this->stay(), $this->company());

        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ── Usuario no autenticado ───────────────────────────────────────────────

    public function testAnonymousUserIsDeniedManage(): void
    {
        $result = $this->voter->vote($this->token(null), $this->stay(), [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAnonymousUserIsDeniedCreate(): void
    {
        $result = $this->voter->vote($this->token(null), $this->centre(), [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── MANAGE: acceso por rol ───────────────────────────────────────────────

    public function testManageGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);

        $this->programmes->expects($this->never())->method('isCoordinatorOf');
        $this->companies->expects($this->never())->method('hasLiaisonInCentre');
        $this->companies->expects($this->never())->method('hasLiaisonPositionInStay');

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManageGrantedToCoordinator(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(true);
        $this->families->expects($this->never())->method('isFamilyHeadOfProgramme');
        $this->companies->expects($this->never())->method('hasLiaisonInCentre');
        $this->companies->expects($this->never())->method('hasLiaisonPositionInStay');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManageGrantedToFamilyHead(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(true);
        $this->companies->expects($this->never())->method('hasLiaisonInCentre');
        $this->companies->expects($this->never())->method('hasLiaisonPositionInStay');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManageDeniedToLiaison(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testManageDeniedToUnrelatedTeacher(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── MANAGE_POSITION: acceso por rol ──────────────────────────────────────

    public function testManagePositionGrantedToCentreAdmin(): void
    {
        $teacher  = $this->teacher();
        $stay     = $this->stay();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);
        $position = $this->position($stay, $this->company());

        $this->programmes->expects($this->never())->method('isCoordinatorOf');

        $result = $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManagePositionGrantedToCoordinator(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(true);
        $this->families->expects($this->never())->method('isFamilyHeadOfProgramme');

        $position = $this->position($this->stay(), $this->company());

        $result = $this->voter->vote($this->token($this->teacher()), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManagePositionGrantedToFamilyHead(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(true);

        $position = $this->position($this->stay(), $this->company());

        $result = $this->voter->vote($this->token($this->teacher()), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManagePositionGrantedToLiaisonOfPositionCompany(): void
    {
        $teacher  = $this->teacher();
        $company  = $this->company($teacher);
        $position = $this->position($this->stay(), $company);

        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);

        $result = $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManagePositionDeniedToLiaisonOfDifferentCompany(): void
    {
        $liaison  = $this->teacher();
        $other    = $this->company();           // liaison is NOT in this company
        $position = $this->position($this->stay(), $other);

        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);

        $result = $this->voter->vote($this->token($liaison), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testManagePositionDeniedToViewOnlyTeacher(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);

        $position = $this->position($this->stay(), $this->company());

        $result = $this->voter->vote($this->token($this->teacher()), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testManagePositionDeniedWhenPositionHasNoWorkcenter(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);

        $stay     = $this->stay();
        $position = (new TrainingPosition())
            ->setStay($stay)
            ->setStartDate(new \DateTimeImmutable('2025-03-01'))
            ->setEndDate(new \DateTimeImmutable('2025-06-30'));

        $result = $this->voter->vote($this->token($this->teacher()), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── MANAGE_POSITION: enlace con estudiante asignado ─────────────────────

    public function testManagePositionDeniedToLiaisonWhenStudentAssigned(): void
    {
        $teacher  = $this->teacher();
        $company  = $this->company($teacher);
        $student  = (new Student(new PersonName('Ana', 'López')))->setStudentId('2024-001');
        $position = $this->position($this->stay(), $company);
        $position->setStudent($student);

        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);

        $result = $this->voter->vote($this->token($teacher), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testManagePositionGrantedToCoordinatorEvenWhenStudentAssigned(): void
    {
        $student  = (new Student(new PersonName('Ana', 'López')))->setStudentId('2024-001');
        $position = $this->position($this->stay(), $this->company());
        $position->setStudent($student);

        $this->programmes->method('isCoordinatorOf')->willReturn(true);

        $result = $this->voter->vote($this->token($this->teacher()), $position, [StayVoter::MANAGE_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ── ADD_POSITION: acceso por rol ─────────────────────────────────────────

    public function testAddPositionGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);

        $this->programmes->expects($this->never())->method('isCoordinatorOf');

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::ADD_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAddPositionGrantedToCoordinator(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(true);
        $this->families->expects($this->never())->method('isFamilyHeadOfProgramme');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::ADD_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAddPositionGrantedToFamilyHead(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(true);
        $this->companies->expects($this->never())->method('hasLiaisonInCentre');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::ADD_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAddPositionGrantedToLiaison(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::ADD_POSITION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAddPositionDeniedToNonLiaison(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::ADD_POSITION]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAddPositionDeniedToGroupTeacher(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::ADD_POSITION]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── VIEW_UNASSIGNED: acceso por rol ──────────────────────────────────────

    public function testSupportsViewUnassignedWithStay(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->stay(), [StayVoter::VIEW_UNASSIGNED]);

        self::assertNotSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsWhenViewUnassignedReceivesCentre(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->centre(), [StayVoter::VIEW_UNASSIGNED]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testViewUnassignedGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);

        $this->programmes->expects($this->never())->method('isCoordinatorOf');
        $this->companies->expects($this->never())->method('hasLiaisonInCentre');

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW_UNASSIGNED]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewUnassignedGrantedToCoordinator(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(true);
        $this->companies->expects($this->never())->method('hasLiaisonInCentre');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW_UNASSIGNED]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewUnassignedGrantedToFamilyHead(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(true);
        $this->companies->expects($this->never())->method('hasLiaisonInCentre');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW_UNASSIGNED]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewUnassignedGrantedToLiaison(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW_UNASSIGNED]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewUnassignedDeniedToGroupTeacher(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW_UNASSIGNED]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── CREATE: acceso por rol ───────────────────────────────────────────────

    public function testCreateGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $centre->addAdmin($teacher);

        $this->programmes->expects($this->never())->method('isCoordinatorInCentre');

        $result = $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateGrantedToCoordinator(): void
    {
        $this->programmes->method('isCoordinatorInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($this->teacher()), $this->centre(), [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateDeniedToLiaison(): void
    {
        $this->programmes->method('isCoordinatorInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->centre(), [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testCreateDeniedToUnrelatedTeacher(): void
    {
        $this->programmes->method('isCoordinatorInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($this->teacher()), $this->centre(), [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function teacher(bool $admin = false): Teacher
    {
        return (new Teacher(new PersonName('Ana', 'García')))
            ->setUsername('ana.garcia')
            ->setAdmin($admin);
    }

    private function centre(): EducationalCentre
    {
        return (new EducationalCentre())
            ->setCode('41012345')
            ->setName('IES Test')
            ->setCity('Sevilla');
    }

    private function stay(): Stay
    {
        $centre  = $this->centre();
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($family);
        $stay    = new Stay();
        $stay->setName('Estancia DAW')
             ->setAcademicYear($year)
             ->setProgramme($prog)
             ->setStartDate(new \DateTimeImmutable('2025-03-01'))
             ->setEndDate(new \DateTimeImmutable('2025-06-30'));

        return $stay;
    }

    private function company(Teacher ...$liaisons): Company
    {
        $company = (new Company())
            ->setName('Empresa Test SL')
            ->setVatNumber('B12345678');
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

        return (new TrainingPosition())
            ->setStay($stay)
            ->setWorkcenter($workcenter)
            ->setStartDate(new \DateTimeImmutable('2025-03-01'))
            ->setEndDate(new \DateTimeImmutable('2025-06-30'));
    }

    private function token(mixed $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
