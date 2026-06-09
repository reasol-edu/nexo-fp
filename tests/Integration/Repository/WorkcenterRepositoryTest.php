<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Entity\Workcenter;
use App\Repository\WorkcenterRepository;
use App\Tests\Integration\RepositoryTestCase;

class WorkcenterRepositoryTest extends RepositoryTestCase
{
    private WorkcenterRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var WorkcenterRepository $repo */
        $repo       = self::getContainer()->get(WorkcenterRepository::class);
        $this->repo = $repo;
    }

    // ── findByCompanyOrderedByName ────────────────────────────────────────────

    public function testFindByCompanyOrderedByNameReturnsSortedWorkcenters(): void
    {
        $centre  = $this->makeCentre('41000001');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        $wc1 = $this->makeWorkcenter($company, 'Zona Norte');
        $wc2 = $this->makeWorkcenter($company, 'Almacen');
        $wc3 = $this->makeWorkcenter($company, 'Oficina Central');
        $this->persist($wc1, $wc2, $wc3);

        $results = $this->repo->findByCompanyOrderedByName($company);

        self::assertCount(3, $results);
        self::assertSame('Almacen', $results[0]->getName());
        self::assertSame('Oficina Central', $results[1]->getName());
        self::assertSame('Zona Norte', $results[2]->getName());
    }

    public function testFindByCompanyOrderedByNameExcludesOtherCompanies(): void
    {
        $centre  = $this->makeCentre('41000002');
        $compA   = $this->makeCompany($centre, 'Empresa A');
        $compB   = $this->makeCompany($centre, 'Empresa B');
        $this->persist($centre, $compA, $compB);

        $wcA = $this->makeWorkcenter($compA, 'Sede A');
        $wcB = $this->makeWorkcenter($compB, 'Sede B');
        $this->persist($wcA, $wcB);

        $results = $this->repo->findByCompanyOrderedByName($compA);

        self::assertCount(1, $results);
        self::assertSame('Sede A', $results[0]->getName());
    }

    // ── findByCompanyAndId ────────────────────────────────────────────────────

    public function testFindByCompanyAndIdReturnsWorkcenter(): void
    {
        $centre  = $this->makeCentre('41000003');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $wc      = $this->makeWorkcenter($company, 'Oficina');
        $this->persist($centre, $company, $wc);

        $result = $this->repo->findByCompanyAndId($company, $wc->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($wc->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByCompanyAndIdReturnsNullForDifferentCompany(): void
    {
        $centre  = $this->makeCentre('41000004');
        $compA   = $this->makeCompany($centre, 'Empresa A');
        $compB   = $this->makeCompany($centre, 'Empresa B');
        $wc      = $this->makeWorkcenter($compA, 'Oficina');
        $this->persist($centre, $compA, $compB, $wc);

        self::assertNull($this->repo->findByCompanyAndId($compB, $wc->getId()->toRfc4122()));
    }

    // ── findByCentreOrdered ───────────────────────────────────────────────────

    public function testFindByCentreOrderedReturnsSortedByCompanyThenName(): void
    {
        $centre  = $this->makeCentre('41000005');
        $compA   = $this->makeCompany($centre, 'Alfa S.L.');
        $compB   = $this->makeCompany($centre, 'Beta S.L.');
        $this->persist($centre, $compA, $compB);

        $wcA2 = $this->makeWorkcenter($compA, 'Zona Norte');
        $wcA1 = $this->makeWorkcenter($compA, 'Almacen');
        $wcB1 = $this->makeWorkcenter($compB, 'Oficina');
        $this->persist($wcA2, $wcA1, $wcB1);

        $results = $this->repo->findByCentreOrdered($centre);

        self::assertCount(3, $results);
        self::assertSame('Almacen', $results[0]->getName());    // Alfa / Almacen
        self::assertSame('Zona Norte', $results[1]->getName()); // Alfa / Zona Norte
        self::assertSame('Oficina', $results[2]->getName());    // Beta / Oficina
    }

    public function testFindByCentreOrderedExcludesOtherCentres(): void
    {
        $centreA = $this->makeCentre('41000006');
        $centreB = $this->makeCentre('41000007');
        $compA   = $this->makeCompany($centreA, 'Empresa A');
        $compB   = $this->makeCompany($centreB, 'Empresa B');
        $this->persist($centreA, $centreB, $compA, $compB);

        $wcA = $this->makeWorkcenter($compA, 'Sede A');
        $wcB = $this->makeWorkcenter($compB, 'Sede B');
        $this->persist($wcA, $wcB);

        $results = $this->repo->findByCentreOrdered($centreA);

        self::assertCount(1, $results);
        self::assertSame('Sede A', $results[0]->getName());
    }

    // ── findByCentreAndLiaisonOrdered ─────────────────────────────────────────

    public function testFindByCentreAndLiaisonOrderedReturnsOnlyLiaisonWorkcenters(): void
    {
        $centre  = $this->makeCentre('41000011');
        $liaison = $this->makeTeacher('liaison.wc');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $liaison, $company);
        $company->addLiaison($liaison);
        $wc1 = $this->makeWorkcenter($company, 'Zona B');
        $wc2 = $this->makeWorkcenter($company, 'Almacen');
        $this->persist($wc1, $wc2);
        $this->flush();

        $results = $this->repo->findByCentreAndLiaisonOrdered($centre, $liaison);

        self::assertCount(2, $results);
        self::assertSame('Almacen', $results[0]->getName());
        self::assertSame('Zona B',  $results[1]->getName());
    }

    public function testFindByCentreAndLiaisonOrderedExcludesOtherLiaisons(): void
    {
        $centre   = $this->makeCentre('41000012');
        $liaisonA = $this->makeTeacher('liaison.a');
        $liaisonB = $this->makeTeacher('liaison.b');
        $company  = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $liaisonA, $liaisonB, $company);
        $company->addLiaison($liaisonA);
        $wc = $this->makeWorkcenter($company, 'Sede A');
        $this->persist($wc);
        $this->flush();

        $results = $this->repo->findByCentreAndLiaisonOrdered($centre, $liaisonB);

        self::assertCount(0, $results);
    }

    public function testFindByCentreAndLiaisonOrderedReturnsEmptyWhenNoWorkcenters(): void
    {
        $centre  = $this->makeCentre('41000013');
        $liaison = $this->makeTeacher('liaison.none');
        $company = $this->makeCompany($centre, 'Empresa Vacía S.L.');
        $this->persist($centre, $liaison, $company);
        $company->addLiaison($liaison);
        $this->flush();

        $results = $this->repo->findByCentreAndLiaisonOrdered($centre, $liaison);

        self::assertCount(0, $results);
    }

    // ── findByCentreAndId ─────────────────────────────────────────────────────

    public function testFindByCentreAndIdReturnsWorkcenter(): void
    {
        $centre  = $this->makeCentre('41000008');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $wc      = $this->makeWorkcenter($company, 'Oficina');
        $this->persist($centre, $company, $wc);

        $result = $this->repo->findByCentreAndId($centre, $wc->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($wc->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByCentreAndIdReturnsNullForDifferentCentre(): void
    {
        $centreA = $this->makeCentre('41000009');
        $centreB = $this->makeCentre('41000010');
        $company = $this->makeCompany($centreA, 'Empresa A');
        $wc      = $this->makeWorkcenter($company, 'Oficina');
        $this->persist($centreA, $centreB, $company, $wc);

        self::assertNull($this->repo->findByCentreAndId($centreB, $wc->getId()->toRfc4122()));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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

    private function makeWorkcenter(Company $company, string $name): Workcenter
    {
        return (new Workcenter())
            ->setName($name)
            ->setCity('Sevilla')
            ->setCompany($company);
    }

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }
}
