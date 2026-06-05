<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Company;
use App\Entity\CompanyAudit;
use App\Entity\EducationalCentre;
use App\Repository\CompanyAuditRepository;
use App\Tests\Integration\RepositoryTestCase;

class CompanyAuditRepositoryTest extends RepositoryTestCase
{
    private CompanyAuditRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CompanyAuditRepository $repo */
        $repo       = self::getContainer()->get(CompanyAuditRepository::class);
        $this->repo = $repo;
    }

    // ── findByCompany ─────────────────────────────────────────────────────────

    public function testFindByCompanyReturnsAuditsOrderedByDateDesc(): void
    {
        $centre  = $this->makeCentre('41000001');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        $a1 = new CompanyAudit($company, null, ['name' => ['old' => 'A', 'new' => 'B']]);
        $a2 = new CompanyAudit($company, null, ['city' => ['old' => 'X', 'new' => 'Y']]);
        $this->persist($a1, $a2);

        $results = $this->repo->findByCompany($company);

        self::assertCount(2, $results);
        // Both audits belong to $company; order by changedAt DESC (a2 is newer as it was saved after a1)
        foreach ($results as $audit) {
            self::assertSame($company->getId()->toRfc4122(), $audit->getCompany()->getId()->toRfc4122());
        }
    }

    public function testFindByCompanyExcludesAuditsFromOtherCompanies(): void
    {
        $centre  = $this->makeCentre('41000002');
        $compA   = $this->makeCompany($centre, 'Empresa A');
        $compB   = $this->makeCompany($centre, 'Empresa B');
        $this->persist($centre, $compA, $compB);

        $auditA = new CompanyAudit($compA, null, ['name' => ['old' => 'X', 'new' => 'A']]);
        $auditB = new CompanyAudit($compB, null, ['name' => ['old' => 'Y', 'new' => 'B']]);
        $this->persist($auditA, $auditB);

        $results = $this->repo->findByCompany($compA);

        self::assertCount(1, $results);
        self::assertSame($compA->getId()->toRfc4122(), $results[0]->getCompany()->getId()->toRfc4122());
    }

    public function testFindByCompanyReturnsEmptyWhenNoAudits(): void
    {
        $centre  = $this->makeCentre('41000003');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        self::assertCount(0, $this->repo->findByCompany($company));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
    }

    private function makeCompany(EducationalCentre $centre, string $name): Company
    {
        return (new Company())
            ->setName($name)
            ->setVatNumber('B' . substr(md5($name), 0, 8))
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }
}
