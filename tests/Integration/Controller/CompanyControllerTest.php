<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Entity\Worker;
use App\Tests\Integration\ControllerTestCase;

class CompanyControllerTest extends ControllerTestCase
{
    // ── index ─────────────────────────────────────────────────────────────────

    public function testIndexRedirectsToSelectCentreWhenNoTenantSelected(): void
    {
        $teacher = $this->makeAdmin('admin.1');
        $this->persist($teacher);
        $this->loginAs($teacher); // no centre → tenant not set

        $this->client->request('GET', '/empresas');

        self::assertResponseRedirects();
        self::assertStringContainsString('/centro', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testIndexRequiresSectionPermission(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeTeacher('teacher.1'); // no special access
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/empresas');

        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexIsAccessibleWithSectionPermission(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/empresas');

        self::assertResponseIsSuccessful();
    }

    // ── new ───────────────────────────────────────────────────────────────────

    public function testNewCompanyGetRendersForm(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/empresas/nueva');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testNewCompanyPostCreatesCompanyAndRedirectsToEdit(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $crawler = $this->client->request('GET', '/empresas/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/empresas/nueva', [
            '_token'     => $token,
            'name'       => 'Empresa Test S.L.',
            'vat_number' => 'B12345678',
            'city'       => 'Sevilla',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/empresas/', (string) $this->client->getResponse()->headers->get('Location'));

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testNewCompanyPostSavesRepresentative(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $crawler = $this->client->request('GET', '/empresas/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/empresas/nueva', [
            '_token'                     => $token,
            'name'                       => 'Empresa Test S.L.',
            'vat_number'                 => 'B12345678',
            'city'                       => 'Sevilla',
            'representative_first_name'  => 'Carmen',
            'representative_last_name'   => 'Serrano',
            'representative_national_id' => '12345678Z',
            'representative_role'        => 'Administradora',
        ]);

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertSame(1, preg_match('#/empresas/([0-9a-f-]{36})#', $location, $m));

        $this->em->clear();
        /** @var Company $created */
        $created = $this->em->find(Company::class, \Symfony\Component\Uid\Uuid::fromString($m[1]));
        self::assertSame('Carmen', $created->getRepresentativeFirstName());
        self::assertSame('Serrano', $created->getRepresentativeLastName());
        self::assertSame('12345678Z', $created->getRepresentativeNationalId());
        self::assertSame('Administradora', $created->getRepresentativeRole());
    }

    public function testNewCompanyPostWithInvalidCsrfIsDenied(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $this->client->request('POST', '/empresas/nueva', [
            '_token'     => 'token-invalido',
            'name'       => 'Empresa Test',
            'vat_number' => 'B12345678',
            'city'       => 'Sevilla',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNewCompanyPostWithEmptyNameRendersFormAgain(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $crawler = $this->client->request('GET', '/empresas/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/empresas/nueva', [
            '_token'     => $token,
            'name'       => '',          // vacío → error de validación
            'vat_number' => 'B12345678',
            'city'       => 'Sevilla',
        ]);

        // La validación falla → 200 en lugar de redirección
        self::assertResponseIsSuccessful();
    }

    public function testNewCompanyPostWithDuplicateVatRendersFormAgain(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa Existente', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $crawler = $this->client->request('GET', '/empresas/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/empresas/nueva', [
            '_token'     => $token,
            'name'       => 'Empresa Nueva',
            'vat_number' => 'B12345678', // ya existe
            'city'       => 'Malaga',
        ]);

        self::assertResponseIsSuccessful(); // permanece en el formulario
    }

    // ── edit ──────────────────────────────────────────────────────────────────

    public function testEditCompanyGetRendersForm(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/empresas/' . $company->getId()->toRfc4122());

        self::assertResponseIsSuccessful();
    }

    public function testEditCompanyRequiresEditPermission(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeTeacher('teacher.1'); // sin acceso EDIT
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/empresas/' . $company->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(403);
    }

    public function testEditCompanyPostSavesChanges(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa Original', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);

        // El token del formulario de edición es el primero de la página
        $token = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId, [
            '_token'     => $token,
            'name'       => 'Empresa Modificada',
            'vat_number' => 'B12345678',
            'city'       => 'Malaga',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        /** @var Company $updated */
        $updated = $this->em->find(Company::class, $company->getId());
        self::assertSame('Empresa Modificada', $updated->getName());
        self::assertSame('Malaga', $updated->getCity());
    }

    public function testEditCompanyPostSavesRepresentative(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa Original', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);
        $token     = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId, [
            '_token'                     => $token,
            'name'                       => 'Empresa Original',
            'vat_number'                 => 'B12345678',
            'city'                       => 'Sevilla',
            'representative_first_name'  => 'Carmen',
            'representative_last_name'   => 'Serrano',
            'representative_national_id' => '12345678Z',
            'representative_role'        => 'Administradora',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        /** @var Company $updated */
        $updated = $this->em->find(Company::class, $company->getId());
        self::assertSame('Carmen', $updated->getRepresentativeFirstName());
        self::assertSame('Serrano', $updated->getRepresentativeLastName());
        self::assertSame('12345678Z', $updated->getRepresentativeNationalId());
        self::assertSame('Administradora', $updated->getRepresentativeRole());
    }

    public function testEditCompanyPostWithEmptyRepresentativeStoresNull(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa Original', 'B12345678')
            ->setRepresentativeFirstName('Carmen')
            ->setRepresentativeRole('Gerente');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);
        $token     = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId, [
            '_token'                     => $token,
            'name'                       => 'Empresa Original',
            'vat_number'                 => 'B12345678',
            'city'                       => 'Sevilla',
            'representative_first_name'  => '',
            'representative_last_name'   => '',
            'representative_national_id' => '',
            'representative_role'        => '',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        /** @var Company $updated */
        $updated = $this->em->find(Company::class, $company->getId());
        self::assertNull($updated->getRepresentativeFirstName());
        self::assertNull($updated->getRepresentativeRole());
    }

    public function testEditCompanyPostWithInvalidCsrfIsDenied(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $this->client->request('POST', '/empresas/' . $companyId, [
            '_token' => 'token-invalido',
            'name'   => 'Empresa Hackeada',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteCompanyDeletesEntityAndRedirectsToIndex(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa a Borrar', 'B99999999');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);

        // El formulario de borrado es el último de la página
        $token = $crawler->filter('form')->last()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/empresas', (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(Company::class, $company->getId()));
    }

    public function testDeleteCompanyWithInvalidCsrfIsDenied(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $this->client->request('POST', '/empresas/' . $companyId . '/eliminar', ['_token' => 'token-invalido']);

        self::assertResponseStatusCodeSame(403);
    }

    // ── add workcenter ────────────────────────────────────────────────────────

    public function testAddWorkcenterCreatesWorkcenterAndRedirectsToEdit(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);

        // form cuya action termina en /centros-trabajo (añadir, no eliminar)
        $token = $crawler->filter('form[action$="/centros-trabajo"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId . '/centros-trabajo', [
            '_token' => $token,
            'name'   => 'Centro Nuevo',
            'city'   => 'Granada',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/empresas/' . $companyId, (string) $this->client->getResponse()->headers->get('Location'));
    }

    // ── add worker ────────────────────────────────────────────────────────────

    public function testAddWorkerCreatesWorkerAndRedirectsToEdit(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);

        $token = $crawler->filter('form[action$="/empleados"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId . '/empleados', [
            '_token'      => $token,
            'first_name'  => 'Juan',
            'last_name'   => 'Garcia',
            'national_id' => '12345678A',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/empresas/' . $companyId, (string) $this->client->getResponse()->headers->get('Location'));
    }

    // ── export ───────────────────────────────────────────────────────────────

    public function testExportReturnsCsvWithCompanyData(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678')
            ->setRepresentativeFirstName('Carmen')
            ->setRepresentativeLastName('Serrano')
            ->setRepresentativeNationalId('99999999R')
            ->setRepresentativeRole('Administradora');
        $worker  = $this->makeWorker('12345678A', 'Ana', 'López');
        $company->addWorker($worker);
        $this->persist($centre, $teacher, $worker, $company);
        $this->loginAs($teacher, $centre);

        // También verifica que /empresas/exportar no cae en la ruta /{id} de edit()
        $this->client->request('GET', '/empresas/exportar');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $content = $this->getStreamedContent();
        self::assertStringContainsString('Empresa S.L.', $content);
        self::assertStringContainsString('B12345678', $content);
        self::assertStringContainsString('Serrano, Carmen', $content);
        self::assertStringContainsString('99999999R', $content);
        self::assertStringContainsString('Administradora', $content);
    }

    public function testExportAppliesSearchFilter(): void
    {
        $centre   = $this->makeCentre('41000001');
        $teacher  = $this->makeAdmin('admin.1');
        $company1 = $this->makeCompany($centre, 'Alfa Tecnología', 'B11111111');
        $company2 = $this->makeCompany($centre, 'Beta Logística', 'B22222222');
        $this->persist($centre, $teacher, $company1, $company2);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/empresas/exportar?search=alfa');

        self::assertResponseIsSuccessful();

        $content = $this->getStreamedContent();
        self::assertStringContainsString('Alfa Tecnología', $content);
        self::assertStringNotContainsString('Beta Logística', $content);
    }

    public function testExportRequiresSectionPermission(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/empresas/exportar');

        self::assertResponseStatusCodeSame(403);
    }

    // ── edit worker ──────────────────────────────────────────────────────────

    public function testEditWorkerPageRendersWithoutError(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $worker  = $this->makeWorker('12345678A', 'Ana', 'López');
        $company->addWorker($worker);
        $this->persist($centre, $teacher, $worker, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $workerId  = $worker->getId()->toRfc4122();

        $this->client->request('GET', '/empresas/' . $companyId . '/empleados/' . $workerId);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[data-model="on(change)|firstName"]');
    }

    // ── remove worker ──────────────────────────────────────────────────────────

    public function testRemoveWorkerDeletesLinkAndShowsSuccess(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $worker  = $this->makeWorker('12345678A', 'Ana', 'López');
        $company->addWorker($worker);
        $this->persist($centre, $teacher, $worker, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $workerId  = $worker->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);

        $token = $crawler->filter('form[action$="/empleados/' . $workerId . '/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId . '/empleados/' . $workerId . '/eliminar', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/empresas/' . $companyId);
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('desvinculado correctamente', $crawler->html());

        $this->em->clear();
        $reloaded = $this->em->find(Company::class, $companyId);
        self::assertCount(0, $reloaded->getWorkers());
    }

    public function testRemoveWorkerNotLinkedShowsWarningAndRemovesNothing(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $other   = $this->makeCompany($centre, 'Otra S.L.', 'B87654321');
        $worker  = $this->makeWorker('12345678A', 'Ana', 'López');
        $other->addWorker($worker);
        $this->persist($centre, $teacher, $worker, $company, $other);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $otherId   = $other->getId()->toRfc4122();
        $workerId  = $worker->getId()->toRfc4122();

        // El token CSRF se indexa por el id del trabajador, así que lo tomamos de la
        // página de la empresa que sí lo tiene vinculado y lo enviamos a la otra.
        $crawler = $this->client->request('GET', '/empresas/' . $otherId);
        $token   = $crawler->filter('form[action$="/empleados/' . $workerId . '/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId . '/empleados/' . $workerId . '/eliminar', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/empresas/' . $companyId);
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('no estaba vinculado', $crawler->html());
        self::assertStringNotContainsString('desvinculado correctamente', $crawler->html());

        $this->em->clear();
        $reloaded = $this->em->find(Company::class, $otherId);
        self::assertCount(1, $reloaded->getWorkers());
    }

    // ── contact information ───────────────────────────────────────────────────

    public function testEditCompanyPostSanitizesContactInformation(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);
        $token     = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId, [
            '_token'              => $token,
            'name'                => 'Empresa S.L.',
            'vat_number'          => 'B12345678',
            'city'                => 'Sevilla',
            'contact_information' => '<p><strong>Contacto</strong></p><script>alert(1)</script><p onclick="evil()">párrafo</p>',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        /** @var Company $updated */
        $updated = $this->em->find(Company::class, $company->getId());
        $stored  = $updated->getContactInformation();

        self::assertNotNull($stored);
        self::assertStringContainsString('<strong>Contacto</strong>', $stored);
        self::assertStringNotContainsString('<script>', $stored);
        self::assertStringNotContainsString('onclick', $stored);
    }

    public function testEditCompanyPostEmptyContactInformationStoresNull(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678')
            ->setContactInformation('<p>Anterior</p>');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/empresas/' . $companyId);
        $token     = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId, [
            '_token'              => $token,
            'name'                => 'Empresa S.L.',
            'vat_number'          => 'B12345678',
            'city'                => 'Sevilla',
            'contact_information' => '',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        /** @var Company $updated */
        $updated = $this->em->find(Company::class, $company->getId());
        self::assertNull($updated->getContactInformation());
    }

    // ── history ───────────────────────────────────────────────────────────────

    public function testHistoryPageRendersAndShowsAuditAfterEdit(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa Original', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $companyId = $company->getId()->toRfc4122();

        // Editar la empresa para generar una entrada de auditoría
        $crawler = $this->client->request('GET', '/empresas/' . $companyId);
        $token   = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/empresas/' . $companyId, [
            '_token'     => $token,
            'name'       => 'Empresa Modificada',
            'vat_number' => 'B12345678',
            'city'       => 'Sevilla',
        ]);
        self::assertResponseRedirects();

        // Verificar que el historial responde 200 y contiene la entrada
        $crawler = $this->client->request('GET', '/empresas/' . $companyId . '/historial');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Empresa Modificada', $crawler->html());
        self::assertStringContainsString('Empresa Original', $crawler->html());
    }

    public function testHistoryPageRendersEmptyStateWhenNoAudits(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = $this->makeAdmin('admin.1');
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678');
        $this->persist($centre, $teacher, $company);
        $this->loginAs($teacher, $centre);

        $crawler = $this->client->request('GET', '/empresas/' . $company->getId()->toRfc4122() . '/historial');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('No hay cambios registrados', $crawler->html());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }

    private function makeAdmin(string $username): Teacher
    {
        return (new Teacher(new PersonName('Admin', 'User')))->setUsername($username)->setAdmin(true);
    }

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
    }

    private function makeCompany(EducationalCentre $centre, string $name, string $vat): Company
    {
        return (new Company())
            ->setName($name)
            ->setVatNumber($vat)
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }

    private function makeWorker(string $nationalId, string $firstName, string $lastName): Worker
    {
        return (new Worker(new PersonName($firstName, $lastName)))
            ->setNationalIdNumber($nationalId);
    }
}
