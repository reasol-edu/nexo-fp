<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\Workcenter;
use App\Repository\CompanyRepository;
use App\Tests\Integration\RepositoryTestCase;

class CompanyRepositoryTest extends RepositoryTestCase
{
    private CompanyRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CompanyRepository $repo */
        $repo       = self::getContainer()->get(CompanyRepository::class);
        $this->repo = $repo;
    }

    // ── findByVatNumberAndCentre ─────────────────────────────────────────────

    public function testFindByVatNumberAndCentreReturnsMatchingCompany(): void
    {
        $centre  = $this->makeCentre('41000001');
        $company = $this->makeCompany('ACME S.L.', 'B12345678', $centre);
        $this->persist($centre, $company);

        $result = $this->repo->findByVatNumberAndCentre('B12345678', $centre);

        self::assertNotNull($result);
        self::assertSame($company->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByVatNumberAndCentreReturnsNullForDifferentCentre(): void
    {
        $centreA = $this->makeCentre('41000002');
        $centreB = $this->makeCentre('41000003');
        $company = $this->makeCompany('ACME S.L.', 'B12345678', $centreA);
        $this->persist($centreA, $centreB, $company);

        // El VAT existe, pero pertenece a centreA — no debe aparecer en centreB
        $result = $this->repo->findByVatNumberAndCentre('B12345678', $centreB);

        self::assertNull($result);
    }

    public function testFindByVatNumberAndCentreReturnsNullWhenVatDoesNotExist(): void
    {
        $centre  = $this->makeCentre('41000004');
        $company = $this->makeCompany('ACME S.L.', 'B12345678', $centre);
        $this->persist($centre, $company);

        $result = $this->repo->findByVatNumberAndCentre('X99999999', $centre);

        self::assertNull($result);
    }

    // ── hasLiaisonInCentre ───────────────────────────────────────────────────

    public function testHasLiaisonInCentreReturnsTrueWhenTeacherIsLiaison(): void
    {
        $centre  = $this->makeCentre('41000005');
        $teacher = $this->makeTeacher('teacher.one');
        $company = $this->makeCompany('ACME S.L.', 'B11111111', $centre);
        $this->persist($centre, $teacher, $company);

        // Las entidades ya son gestionadas → addLiaison crea la entrada en company_liaisons
        $company->addLiaison($teacher);
        $this->flush();

        self::assertTrue($this->repo->hasLiaisonInCentre($teacher, $centre));
    }

    public function testHasLiaisonInCentreReturnsFalseWhenTeacherIsNotLiaison(): void
    {
        $centre  = $this->makeCentre('41000006');
        $teacher = $this->makeTeacher('teacher.two');
        $company = $this->makeCompany('ACME S.L.', 'B22222222', $centre);
        $this->persist($centre, $teacher, $company);

        self::assertFalse($this->repo->hasLiaisonInCentre($teacher, $centre));
    }

    public function testHasLiaisonInCentreReturnsFalseWhenLiaisonBelongsToAnotherCentre(): void
    {
        $centreA = $this->makeCentre('41000007');
        $centreB = $this->makeCentre('41000008');
        $teacher = $this->makeTeacher('teacher.three');

        // El docente es enlace en centreA, pero se pregunta por centreB
        $companyA = $this->makeCompany('ACME S.L.', 'B33333333', $centreA);
        $this->persist($centreA, $centreB, $teacher, $companyA);
        $companyA->addLiaison($teacher);
        $this->flush();

        self::assertFalse($this->repo->hasLiaisonInCentre($teacher, $centreB));
    }

    public function testHasLiaisonInCentreReturnsTrueIfLiaisonInAnyCompanyOfCentre(): void
    {
        $centre   = $this->makeCentre('41000009');
        $teacher  = $this->makeTeacher('teacher.four');
        $company1 = $this->makeCompany('Empresa Uno', 'B44444444', $centre);
        $company2 = $this->makeCompany('Empresa Dos', 'B55555555', $centre);
        $this->persist($centre, $teacher, $company1, $company2);

        // El docente solo es enlace en company2
        $company2->addLiaison($teacher);
        $this->flush();

        self::assertTrue($this->repo->hasLiaisonInCentre($teacher, $centre));
    }

    // ── hasLiaisonPositionInStay ─────────────────────────────────────────────

    public function testHasLiaisonPositionInStayReturnsTrueWhenLiaisonHasPosition(): void
    {
        [$centre, $stay, $company] = $this->makeStayChain('41000013');
        $teacher    = $this->makeTeacher('liaison.stay');
        $workcenter = $this->makeWorkcenter($company, 'Sede');
        $this->persist($teacher, $workcenter);
        $company->addLiaison($teacher);
        $position = (new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter);
        $this->persist($position);
        $this->flush();

        self::assertTrue($this->repo->hasLiaisonPositionInStay($teacher, $stay));
    }

    public function testHasLiaisonPositionInStayReturnsFalseWhenNotLiaison(): void
    {
        [$centre, $stay, $company] = $this->makeStayChain('41000014');
        $teacher    = $this->makeTeacher('not.liaison');
        $workcenter = $this->makeWorkcenter($company, 'Sede');
        $this->persist($teacher, $workcenter);
        $position = (new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter);
        $this->persist($position);
        $this->flush();

        self::assertFalse($this->repo->hasLiaisonPositionInStay($teacher, $stay));
    }

    public function testHasLiaisonPositionInStayReturnsFalseForDifferentStay(): void
    {
        [$centre, $stayA, $company] = $this->makeStayChain('41000015');
        $year    = $stayA->getAcademicYear();
        $prog    = $stayA->getProgramme();
        $stayB   = $this->makeStay($year, $prog, 'FCT DAM B');
        $teacher = $this->makeTeacher('liaison.other.stay');
        $workcenter = $this->makeWorkcenter($company, 'Sede');
        $this->persist($stayB, $teacher, $workcenter);
        $company->addLiaison($teacher);
        $posB = (new TrainingPosition())->setStay($stayB)->setWorkcenter($workcenter);
        $this->persist($posB);
        $this->flush();

        // Teacher has a position in stayB but not in stayA
        self::assertFalse($this->repo->hasLiaisonPositionInStay($teacher, $stayA));
    }

    // ── findByIdAndCentre ────────────────────────────────────────────────────

    public function testFindByIdAndCentreReturnsCompanyInSameCentre(): void
    {
        $centre  = $this->makeCentre('41000010');
        $company = $this->makeCompany('ACME S.L.', 'B66666666', $centre);
        $this->persist($centre, $company);

        $result = $this->repo->findByIdAndCentre($company->getId()->toRfc4122(), $centre);

        self::assertNotNull($result);
        self::assertSame($company->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByIdAndCentreReturnsNullForDifferentCentre(): void
    {
        $centreA = $this->makeCentre('41000011');
        $centreB = $this->makeCentre('41000012');
        $company = $this->makeCompany('ACME S.L.', 'B77777777', $centreA);
        $this->persist($centreA, $centreB, $company);

        $result = $this->repo->findByIdAndCentre($company->getId()->toRfc4122(), $centreB);

        self::assertNull($result);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())
            ->setCode($code)
            ->setName('IES ' . $code)
            ->setCity('Sevilla');
    }

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Ana', 'García')))
            ->setUsername($username);
    }

    private function makeCompany(string $name, string $vatNumber, EducationalCentre $centre): Company
    {
        return (new Company())
            ->setName($name)
            ->setVatNumber($vatNumber)
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }

    private function makeWorkcenter(Company $company, string $name): Workcenter
    {
        return (new Workcenter())->setName($name)->setCity('Sevilla')->setCompany($company);
    }

    private function makeStay(AcademicYear $year, Programme $programme, string $name): Stay
    {
        return (new Stay())
            ->setName($name)
            ->setAcademicYear($year)
            ->setProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('2026-03-01'))
            ->setEndDate(new \DateTimeImmutable('2026-06-30'));
    }

    /**
     * @return array{EducationalCentre, Stay, Company}
     */
    private function makeStayChain(string $centreCode): array
    {
        $centre  = $this->makeCentre($centreCode);
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $stay    = $this->makeStay($year, $prog, 'FCT DAM ' . $centreCode);
        $company = $this->makeCompany('Empresa S.L.', 'B' . substr(md5($centreCode), 0, 8), $centre);
        $this->persist($centre, $year, $family, $prog, $stay, $company);
        return [$centre, $stay, $company];
    }
}
