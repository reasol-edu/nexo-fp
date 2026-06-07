<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security\Voter;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Teacher;
use App\Security\Voter\CompanyVoter;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Tests de integración del CompanyVoter con entidades reales en la base de datos.
 *
 * Complementan los tests unitarios (mocks) verificando que las consultas de
 * repositorio (hasLiaisonInCentre, isFamilyHeadInCentre) funcionan correctamente
 * dentro del contexto completo del voter.
 */
class CompanyVoterTest extends RepositoryTestCase
{
    private CompanyVoter $voter;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CompanyVoter $voter */
        $voter       = self::getContainer()->get(CompanyVoter::class);
        $this->voter = $voter;
    }

    // ── supports() ──────────────────────────────────────────────────────────

    public function testAbstainsOnUnknownAttribute(): void
    {
        $teacher = $this->makeTeacher('t1');
        $centre  = $this->makeCentre('41000001');
        $this->persist($centre, $teacher);

        $result = $this->voter->vote($this->token($teacher), $centre, ['unknown.attr']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsWhenSubjectTypeDoesNotMatchAttribute(): void
    {
        $teacher = $this->makeTeacher('t2');
        $centre  = $this->makeCentre('41000002');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $teacher);

        // SECTION requiere EducationalCentre, no Company
        $result = $this->voter->vote($this->token($teacher), $company, [CompanyVoter::SECTION]);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // ── Administrador global ─────────────────────────────────────────────────

    public function testGlobalAdminIsGrantedSection(): void
    {
        $admin  = $this->makeTeacher('admin', true);
        $centre = $this->makeCentre('41000003');
        $this->persist($centre, $admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($admin), $centre, [CompanyVoter::SECTION])
        );
    }

    public function testGlobalAdminIsGrantedEdit(): void
    {
        $admin   = $this->makeTeacher('admin', true);
        $centre  = $this->makeCentre('41000004');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($admin), $company, [CompanyVoter::EDIT])
        );
    }

    public function testGlobalAdminIsGrantedDelete(): void
    {
        $admin   = $this->makeTeacher('admin', true);
        $centre  = $this->makeCentre('41000005');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $admin);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($admin), $company, [CompanyVoter::DELETE])
        );
    }

    // ── SECTION ──────────────────────────────────────────────────────────────

    public function testSectionGrantedToCentreAdmin(): void
    {
        $teacher = $this->makeTeacher('t3');
        $centre  = $this->makeCentre('41000006');
        $this->persist($centre, $teacher);

        $centre->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION])
        );
    }

    public function testSectionGrantedToLiaisonViaRepository(): void
    {
        $teacher = $this->makeTeacher('t4');
        $centre  = $this->makeCentre('41000007');
        $company = $this->makeCompany($centre, 'Empresa Liaison S.L.');
        $this->persist($centre, $company, $teacher);

        // Relación ManyToMany: dos pasos (persist + addLiaison + flush)
        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION])
        );
    }

    public function testSectionGrantedToFamilyHeadViaRepository(): void
    {
        $teacher = $this->makeTeacher('t5');
        $centre  = $this->makeCentre('41000008');
        $year    = $this->makeYear($centre);
        $family  = $this->makeFamily($year, $teacher);
        $this->persist($centre, $year, $family, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION])
        );
    }

    public function testSectionDeniedToUnrelatedTeacher(): void
    {
        $teacher = $this->makeTeacher('t6');
        $centre  = $this->makeCentre('41000009');
        $this->persist($centre, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION])
        );
    }

    public function testSectionDeniedWhenLiaisonBelongsToAnotherCentre(): void
    {
        $teacher  = $this->makeTeacher('t7');
        $centreA  = $this->makeCentre('41000010');
        $centreB  = $this->makeCentre('41000011');
        $companyA = $this->makeCompany($centreA, 'Empresa en A S.L.');
        $this->persist($centreA, $centreB, $companyA, $teacher);

        $companyA->addLiaison($teacher);
        $this->flush();

        // El docente es liaison en centreA pero accede a centreB
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centreB, [CompanyVoter::SECTION])
        );
    }

    // ── EDIT ─────────────────────────────────────────────────────────────────

    public function testEditGrantedToCentreAdmin(): void
    {
        $teacher = $this->makeTeacher('t8');
        $centre  = $this->makeCentre('41000012');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $teacher);

        $centre->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT])
        );
    }

    public function testEditGrantedToCompanyLiaison(): void
    {
        $teacher = $this->makeTeacher('t9');
        $centre  = $this->makeCentre('41000013');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $teacher);

        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT])
        );
    }

    public function testEditGrantedToFamilyHeadViaRepository(): void
    {
        $teacher = $this->makeTeacher('t10');
        $centre  = $this->makeCentre('41000014');
        $year    = $this->makeYear($centre);
        $family  = $this->makeFamily($year, $teacher);
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $year, $family, $teacher, $company);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT])
        );
    }

    public function testEditDeniedToUnrelatedTeacher(): void
    {
        $teacher = $this->makeTeacher('t11');
        $centre  = $this->makeCentre('41000015');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT])
        );
    }

    public function testEditDeniedWhenLiaisonBelongsToAnotherCompany(): void
    {
        $teacher  = $this->makeTeacher('t12');
        $centre   = $this->makeCentre('41000016');
        $companyA = $this->makeCompany($centre, 'Empresa A S.L.');
        $companyB = $this->makeCompany($centre, 'Empresa B S.L.');
        $this->persist($centre, $companyA, $companyB, $teacher);

        $companyA->addLiaison($teacher);
        $this->flush();

        // El docente es liaison de companyA pero intenta editar companyB
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $companyB, [CompanyVoter::EDIT])
        );
    }

    // ── DELETE ───────────────────────────────────────────────────────────────

    public function testDeleteGrantedToCentreAdmin(): void
    {
        $teacher = $this->makeTeacher('t13');
        $centre  = $this->makeCentre('41000017');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $teacher);

        $centre->addAdmin($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE])
        );
    }

    public function testDeleteDeniedToCompanyLiaison(): void
    {
        $teacher = $this->makeTeacher('t14');
        $centre  = $this->makeCentre('41000018');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $teacher);

        $company->addLiaison($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE])
        );
    }

    public function testDeleteDeniedToFamilyHead(): void
    {
        $teacher = $this->makeTeacher('t15');
        $centre  = $this->makeCentre('41000019');
        $year    = $this->makeYear($centre);
        $family  = $this->makeFamily($year, $teacher);
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $year, $family, $teacher, $company);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE])
        );
    }

    public function testDeleteDeniedToUnrelatedTeacher(): void
    {
        $teacher = $this->makeTeacher('t16');
        $centre  = $this->makeCentre('41000020');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company, $teacher);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE])
        );
    }

    // ── Usuario no autenticado ────────────────────────────────────────────────

    public function testAnonymousUserIsDeniedSection(): void
    {
        $centre = $this->makeCentre('41000021');
        $this->persist($centre);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->anonymousToken(), $centre, [CompanyVoter::SECTION])
        );
    }

    public function testAnonymousUserIsDeniedEdit(): void
    {
        $centre  = $this->makeCentre('41000022');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->anonymousToken(), $company, [CompanyVoter::EDIT])
        );
    }

    public function testAnonymousUserIsDeniedDelete(): void
    {
        $centre  = $this->makeCentre('41000023');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->anonymousToken(), $company, [CompanyVoter::DELETE])
        );
    }

    // ── Aislamiento de tenant (cross-centre) ─────────────────────────────────

    public function testSectionDeniedToCentreAdminOfDifferentCentre(): void
    {
        $teacher  = $this->makeTeacher('t17');
        $centreA  = $this->makeCentre('41000024');
        $centreB  = $this->makeCentre('41000025');
        $this->persist($centreA, $centreB, $teacher);

        $centreA->addAdmin($teacher);
        $this->flush();

        // Admin de centreA intenta acceder a la sección de centreB
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centreB, [CompanyVoter::SECTION])
        );
    }

    public function testEditDeniedToCentreAdminOfDifferentCentre(): void
    {
        $teacher  = $this->makeTeacher('t18');
        $centreA  = $this->makeCentre('41000026');
        $centreB  = $this->makeCentre('41000027');
        $companyB = $this->makeCompany($centreB, 'Empresa en B S.L.');
        $this->persist($centreA, $centreB, $companyB, $teacher);

        $centreA->addAdmin($teacher);
        $this->flush();

        // Admin de centreA intenta editar una empresa de centreB
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $companyB, [CompanyVoter::EDIT])
        );
    }

    public function testDeleteDeniedToCentreAdminOfDifferentCentre(): void
    {
        $teacher  = $this->makeTeacher('t19');
        $centreA  = $this->makeCentre('41000028');
        $centreB  = $this->makeCentre('41000029');
        $companyB = $this->makeCompany($centreB, 'Empresa en B S.L.');
        $this->persist($centreA, $centreB, $companyB, $teacher);

        $centreA->addAdmin($teacher);
        $this->flush();

        // Admin de centreA intenta eliminar una empresa de centreB
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $companyB, [CompanyVoter::DELETE])
        );
    }

    public function testSectionDeniedToFamilyHeadOfDifferentCentre(): void
    {
        $teacher  = $this->makeTeacher('t20');
        $centreA  = $this->makeCentre('41000030');
        $centreB  = $this->makeCentre('41000031');
        $yearA    = $this->makeYear($centreA);
        $familyA  = $this->makeFamily($yearA, $teacher);
        $this->persist($centreA, $centreB, $yearA, $familyA, $teacher);

        // Jefe de familia en centreA intenta acceder a la sección de centreB
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centreB, [CompanyVoter::SECTION])
        );
    }

    // ── Coordinador de FP Dual ───────────────────────────────────────────────

    public function testSectionGrantedToCoordinatorInCentre(): void
    {
        $teacher = $this->makeTeacher('coord.section');
        $centre  = $this->makeCentre('41000034');
        $year    = $this->makeYear($centre);
        $family  = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($family);
        $this->persist($centre, $year, $family, $prog, $teacher);
        $prog->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $centre, [CompanyVoter::SECTION])
        );
    }

    public function testSectionDeniedToCoordinatorOfDifferentCentre(): void
    {
        $teacher  = $this->makeTeacher('coord.other.centre');
        $centreA  = $this->makeCentre('41000035');
        $centreB  = $this->makeCentre('41000036');
        $yearA    = $this->makeYear($centreA);
        $family   = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($yearA);
        $prog     = (new Programme())->setName('DAW')->setAcademicYear($yearA)->setProfessionalFamily($family);
        $this->persist($centreA, $centreB, $yearA, $family, $prog, $teacher);
        $prog->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $centreB, [CompanyVoter::SECTION])
        );
    }

    public function testEditGrantedToCoordinatorInCentre(): void
    {
        $teacher = $this->makeTeacher('coord.edit');
        $centre  = $this->makeCentre('41000037');
        $company = $this->makeCompany($centre, 'Empresa Coord S.L.');
        $year    = $this->makeYear($centre);
        $family  = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($family);
        $this->persist($centre, $company, $year, $family, $prog, $teacher);
        $prog->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::EDIT])
        );
    }

    public function testEditDeniedToCoordinatorOfDifferentCentre(): void
    {
        $teacher  = $this->makeTeacher('coord.edit.other');
        $centreA  = $this->makeCentre('41000038');
        $centreB  = $this->makeCentre('41000039');
        $companyB = $this->makeCompany($centreB, 'Empresa en B S.L.');
        $yearA    = $this->makeYear($centreA);
        $family   = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($yearA);
        $prog     = (new Programme())->setName('DAW')->setAcademicYear($yearA)->setProfessionalFamily($family);
        $this->persist($centreA, $centreB, $companyB, $yearA, $family, $prog, $teacher);
        $prog->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $companyB, [CompanyVoter::EDIT])
        );
    }

    public function testDeleteDeniedToCoordinator(): void
    {
        $teacher = $this->makeTeacher('coord.delete');
        $centre  = $this->makeCentre('41000040');
        $company = $this->makeCompany($centre, 'Empresa Coord S.L.');
        $year    = $this->makeYear($centre);
        $family  = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($family);
        $this->persist($centre, $company, $year, $family, $prog, $teacher);
        $prog->addCoordinator($teacher);
        $this->flush();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $company, [CompanyVoter::DELETE])
        );
    }

    public function testEditDeniedToFamilyHeadOfDifferentCentre(): void
    {
        $teacher  = $this->makeTeacher('t21');
        $centreA  = $this->makeCentre('41000032');
        $centreB  = $this->makeCentre('41000033');
        $yearA    = $this->makeYear($centreA);
        $familyA  = $this->makeFamily($yearA, $teacher);
        $companyB = $this->makeCompany($centreB, 'Empresa en B S.L.');
        $this->persist($centreA, $centreB, $yearA, $familyA, $teacher, $companyB);

        // Jefe de familia en centreA intenta editar empresa de centreB
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($this->token($teacher), $companyB, [CompanyVoter::EDIT])
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeTeacher(string $username, bool $admin = false): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))
            ->setUsername($username)
            ->setAdmin($admin);
    }

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())
            ->setCode($code)
            ->setName('IES ' . $code)
            ->setCity('Sevilla');
    }

    private function makeCompany(EducationalCentre $centre, string $name): Company
    {
        return (new Company())
            ->setName($name)
            ->setVatNumber('B' . substr(md5($name), 0, 8))
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }

    private function makeYear(EducationalCentre $centre): AcademicYear
    {
        return (new AcademicYear())
            ->setName('2024-2025')
            ->setEducationalCentre($centre);
    }

    private function makeFamily(AcademicYear $year, Teacher $head): ProfessionalFamily
    {
        return (new ProfessionalFamily())
            ->setName('Informatica')
            ->setAcademicYear($year)
            ->setHead($head);
    }

    private function token(Teacher $teacher): TokenInterface
    {
        $stub = $this->createStub(TokenInterface::class);
        $stub->method('getUser')->willReturn($teacher);
        return $stub;
    }

    private function anonymousToken(): TokenInterface
    {
        $stub = $this->createStub(TokenInterface::class);
        $stub->method('getUser')->willReturn(null);
        return $stub;
    }
}
