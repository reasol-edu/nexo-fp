<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Repository\CompanyAuditRepository;
use App\Tests\Integration\RepositoryTestCase;

class CompanyAuditSubscriberTest extends RepositoryTestCase
{
    private CompanyAuditRepository $audits;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CompanyAuditRepository $audits */
        $audits       = self::getContainer()->get(CompanyAuditRepository::class);
        $this->audits = $audits;
    }

    // ── registro de cambios ───────────────────────────────────────────────────

    public function testUpdatingCompanyNameCreatesAuditRecord(): void
    {
        $centre  = $this->makeCentre('41000001');
        $company = $this->makeCompany($centre, 'Empresa Original');
        $this->persist($centre, $company);

        $company->setName('Empresa Renombrada');
        $this->flush();

        $records = $this->audits->findByCompany($company);

        self::assertCount(1, $records);
        self::assertArrayHasKey('name', $records[0]->getChanges());
    }

    public function testAuditRecordCapturesOldAndNewValues(): void
    {
        $centre  = $this->makeCentre('41000002');
        $company = $this->makeCompany($centre, 'Nombre Antiguo');
        $this->persist($centre, $company);

        $company->setName('Nombre Nuevo');
        $this->flush();

        $record  = $this->audits->findByCompany($company)[0];
        $changes = $record->getChanges();

        self::assertSame('Nombre Antiguo', $changes['name']['old']);
        self::assertSame('Nombre Nuevo',   $changes['name']['new']);
    }

    public function testAuditRecordHasNullChangedByWhenNoUserIsLoggedIn(): void
    {
        $centre  = $this->makeCentre('41000003');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        $company->setCity('Granada');
        $this->flush();

        self::assertNull($this->audits->findByCompany($company)[0]->getChangedBy());
    }

    public function testMultipleUpdatesCreateMultipleAuditRecords(): void
    {
        $centre  = $this->makeCentre('41000004');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        $company->setName('Empresa Uno');
        $this->flush();

        $company->setCity('Malaga');
        $this->flush();

        self::assertCount(2, $this->audits->findByCompany($company));
    }

    public function testFlushingWithoutChangesDoesNotCreateAuditRecord(): void
    {
        $centre  = $this->makeCentre('41000005');
        $company = $this->makeCompany($centre, 'Sin Cambios S.L.');
        $this->persist($centre, $company);

        // Flush adicional sin modificar ningún campo
        $this->flush();

        self::assertCount(0, $this->audits->findByCompany($company));
    }

    public function testUpdatingUnrelatedEntityDoesNotCreateAuditForCompany(): void
    {
        $centre  = $this->makeCentre('41000006');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        // Modificar el centro, no la empresa
        $centre->setCity('Cordoba');
        $this->flush();

        self::assertCount(0, $this->audits->findByCompany($company));
    }

    public function testAuditRecordContainsTimestamp(): void
    {
        $centre  = $this->makeCentre('41000007');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $this->persist($centre, $company);

        $before = new \DateTimeImmutable();
        $company->setName('Actualizada');
        $this->flush();
        $after = new \DateTimeImmutable();

        $record = $this->audits->findByCompany($company)[0];
        self::assertGreaterThanOrEqual($before, $record->getChangedAt());
        self::assertLessThanOrEqual($after,  $record->getChangedAt());
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
