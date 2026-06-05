<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
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
}
