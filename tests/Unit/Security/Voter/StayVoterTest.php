<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Programme;
use App\Entity\ProfessionalFamily;
use App\Entity\Stay;
use App\Entity\Teacher;
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

    // ── VIEW: acceso por rol ─────────────────────────────────────────────────

    public function testViewGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($teacher);

        $this->programmes->expects(self::never())->method('isCoordinatorOf');

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedToCoordinator(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(true);
        $this->families->expects(self::never())->method('isFamilyHeadOfProgramme');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedToFamilyHead(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(true);
        $this->groups->expects(self::never())->method('isTeacherInProgramme');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedToTeacherInProgramme(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->groups->method('isTeacherInProgramme')->willReturn(true);
        $this->companies->expects(self::never())->method('hasLiaisonInCentre');

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewGrantedToLiaison(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->groups->method('isTeacherInProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($this->teacher()), $this->stay(), [StayVoter::VIEW]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewDeniedToUnrelatedTeacher(): void
    {
        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->groups->method('isTeacherInProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(false);

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

        $this->programmes->expects(self::never())->method('isCoordinatorOf');
        $this->companies->expects(self::never())->method('hasLiaisonInCentre');

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManageGrantedToCoordinator(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();

        $this->programmes->method('isCoordinatorOf')->willReturn(true);
        $this->families->expects(self::never())->method('isFamilyHeadOfProgramme');
        $this->companies->expects(self::never())->method('hasLiaisonInCentre');

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManageGrantedToFamilyHead(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();

        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(true);
        $this->companies->expects(self::never())->method('hasLiaisonInCentre');

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManageGrantedToLiaison(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();

        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testManageDeniedToUnrelatedTeacher(): void
    {
        $teacher = $this->teacher();
        $stay    = $this->stay();

        $this->programmes->method('isCoordinatorOf')->willReturn(false);
        $this->families->method('isFamilyHeadOfProgramme')->willReturn(false);
        $this->companies->method('hasLiaisonInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($teacher), $stay, [StayVoter::MANAGE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── CREATE: acceso por rol ───────────────────────────────────────────────

    public function testCreateGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $centre->addAdmin($teacher);

        $this->programmes->expects(self::never())->method('isCoordinatorInCentre');

        $result = $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateGrantedToCoordinator(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();

        $this->programmes->method('isCoordinatorInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateDeniedToLiaison(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();

        $this->programmes->method('isCoordinatorInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testCreateDeniedToUnrelatedTeacher(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();

        $this->programmes->method('isCoordinatorInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($teacher), $centre, [StayVoter::CREATE]);

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

    private function token(mixed $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
