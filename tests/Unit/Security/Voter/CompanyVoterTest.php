<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Repository\CompanyRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Security\Voter\CompanyVoter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[AllowMockObjectsWithoutExpectations]
class CompanyVoterTest extends TestCase
{
    private CompanyRepository&MockObject $companies;
    private ProfessionalFamilyRepository&MockObject $families;
    private CompanyVoter $voter;

    protected function setUp(): void
    {
        $this->companies = $this->createMock(CompanyRepository::class);
        $this->families  = $this->createMock(ProfessionalFamilyRepository::class);
        $this->voter     = new CompanyVoter($this->companies, $this->families);
    }

    // ── supports() ──────────────────────────────────────────────────────────

    public function testSupportsSectionAttributeWithCentre(): void
    {
        $token = $this->token($this->teacher());
        $result = $this->voter->vote($token, $this->centre(), [CompanyVoter::SECTION]);

        self::assertNotSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testSupportsEditAndDeleteWithCompany(): void
    {
        $centre  = $this->centre();
        $company = $this->company($centre);
        $teacher = $this->teacher(admin: true);
        $token   = $this->token($teacher);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $company, [CompanyVoter::EDIT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $company, [CompanyVoter::DELETE]));
    }

    public function testAbstainsOnUnknownAttribute(): void
    {
        $result = $this->voter->vote($this->token($this->teacher()), $this->centre(), ['unknown']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsOnWrongSubjectType(): void
    {
        // SECTION requires EducationalCentre, not Company
        $centre  = $this->centre();
        $company = $this->company($centre);
        $result  = $this->voter->vote($this->token($this->teacher()), $company, [CompanyVoter::SECTION]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // ── Acceso de administrador global ──────────────────────────────────────

    public function testGlobalAdminIsGrantedSection(): void
    {
        $result = $this->voter->vote($this->token($this->teacher(admin: true)), $this->centre(), [CompanyVoter::SECTION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGlobalAdminIsGrantedEdit(): void
    {
        $centre  = $this->centre();
        $company = $this->company($centre);
        $result  = $this->voter->vote($this->token($this->teacher(admin: true)), $company, [CompanyVoter::EDIT]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGlobalAdminIsGrantedDelete(): void
    {
        $centre  = $this->centre();
        $company = $this->company($centre);
        $result  = $this->voter->vote($this->token($this->teacher(admin: true)), $company, [CompanyVoter::DELETE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ── Usuarios que no son Teacher ──────────────────────────────────────────

    public function testAnonymousUserIsDenied(): void
    {
        $result = $this->voter->vote($this->token(null), $this->centre(), [CompanyVoter::SECTION]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── SECTION ──────────────────────────────────────────────────────────────

    public function testSectionGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $centre->addAdmin($teacher);

        $this->companiesNeverCalled();
        $this->familiesNeverCalled();

        $result = $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testSectionGrantedToLiaison(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();

        $this->companies->method('hasLiaisonInCentre')->willReturn(true);
        $this->families->expects(self::never())->method('isFamilyHeadInCentre');

        $result = $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testSectionGrantedToFamilyHead(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();

        $this->companies->method('hasLiaisonInCentre')->willReturn(false);
        $this->families->method('isFamilyHeadInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testSectionDeniedToUnrelatedTeacher(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();

        $this->companies->method('hasLiaisonInCentre')->willReturn(false);
        $this->families->method('isFamilyHeadInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── EDIT ─────────────────────────────────────────────────────────────────

    public function testEditGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $centre->addAdmin($teacher);
        $company = $this->company($centre);

        $this->companiesNeverCalled();
        $this->familiesNeverCalled();

        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditGrantedToCompanyLiaison(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $company = $this->company($centre);
        $company->addLiaison($teacher);

        $this->familiesNeverCalled();

        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditGrantedToFamilyHead(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $company = $this->company($centre);

        $this->families->method('isFamilyHeadInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditDeniedToUnrelatedTeacher(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $company = $this->company($centre);

        $this->families->method('isFamilyHeadInCentre')->willReturn(false);

        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── DELETE ───────────────────────────────────────────────────────────────

    public function testDeleteGrantedToCentreAdmin(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $centre->addAdmin($teacher);
        $company = $this->company($centre);

        $this->companiesNeverCalled();
        $this->familiesNeverCalled();

        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE]);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeleteDeniedToLiaison(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $company = $this->company($centre);
        $company->addLiaison($teacher);

        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeleteDeniedToFamilyHead(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $company = $this->company($centre);

        $this->families->method('isFamilyHeadInCentre')->willReturn(true);

        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeleteDeniedToUnrelatedTeacher(): void
    {
        $teacher = $this->teacher();
        $centre  = $this->centre();
        $company = $this->company($centre);

        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE]);

        self::assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function teacher(bool $admin = false): Teacher
    {
        $teacher = new Teacher(new PersonName('Ana', 'García'));
        $teacher->setUsername('ana.garcia')->setAdmin($admin);

        return $teacher;
    }

    private function centre(): EducationalCentre
    {
        return (new EducationalCentre())
            ->setCode('41012345')
            ->setName('IES Test')
            ->setCity('Sevilla');
    }

    private function company(EducationalCentre $centre): Company
    {
        return (new Company())
            ->setName('ACME S.L.')
            ->setVatNumber('B12345678')
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }

    private function token(mixed $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function companiesNeverCalled(): void
    {
        $this->companies->expects(self::never())->method('hasLiaisonInCentre');
    }

    private function familiesNeverCalled(): void
    {
        $this->families->expects(self::never())->method('isFamilyHeadInCentre');
    }
}
