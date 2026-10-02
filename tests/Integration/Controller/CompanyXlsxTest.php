<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Entity\Worker;
use App\Entity\Workcenter;
use App\Tests\Integration\ControllerTestCase;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Exportación e importación de empresas desde un libro .xlsx.
 */
class CompanyXlsxTest extends ControllerTestCase
{
    private const COMPANY_HEADERS = ['Nombre *', 'CIF/NIF *', 'Localidad *', 'Nombre del representante', 'Apellidos del representante', 'DNI/NIE del representante', 'Cargo del representante', 'Información de contacto', 'Observaciones'];
    private const WORKCENTER_HEADERS = ['CIF/NIF de la empresa *', 'Nombre del centro *', 'Localidad'];
    private const WORKER_HEADERS = ['CIF/NIF de la empresa *', 'Nombre *', 'Apellidos *', 'DNI/NIE *', 'Correo electrónico', 'Teléfono'];

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    // ── export ───────────────────────────────────────────────────────────────

    public function testExportHasEditableSheetsAndKeepsTextAsText(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $company = $this->makeCompany($centre, '=1+1 S.L.', 'B12345678')
            ->setRepresentativeFirstName('Carmen')
            ->setRepresentativeLastName('Serrano')
            ->setContactInformation('<p>Llamar por la mañana</p><p>Tel. 955000000</p>');
        $worker = (new Worker(new PersonName('Ana', 'López')))->setNationalIdNumber('12345678A')->setWorkPhoneNumber('600123456');
        $company->addWorker($worker);
        $workcenter = (new Workcenter())->setName('Sede Norte')->setCity('Dos Hermanas')->setCompany($company);
        $this->persist($worker, $company, $workcenter);
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/empresas/exportar');

        self::assertResponseIsSuccessful();
        $sheets = $this->readSheets($this->saveResponse());

        self::assertSame(['Empresas', 'Centros de trabajo', 'Empleados', 'Instrucciones'], array_keys($sheets));
        self::assertSame('Nombre *', $sheets['Empresas'][0][0]);

        $row = $sheets['Empresas'][1];
        self::assertSame('=1+1 S.L.', $row[0], 'Un texto que empieza por = no debe convertirse en fórmula');
        self::assertSame('Carmen', $row[3]);
        self::assertSame('Serrano', $row[4]);
        self::assertSame("Llamar por la mañana\nTel. 955000000", $row[7]);
        self::assertSame(['B12345678', 'Sede Norte', 'Dos Hermanas'], $sheets['Centros de trabajo'][1]);
        self::assertSame('12345678A', $sheets['Empleados'][1][3]);
        self::assertSame('600123456', $sheets['Empleados'][1][5]);
    }

    public function testExportAppliesSearchFilter(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->persist($this->makeCompany($centre, 'Alfa Tecnología', 'B11111111'), $this->makeCompany($centre, 'Beta Logística', 'B22222222'));
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/empresas/exportar?search=alfa');

        $names = array_column($this->readSheets($this->saveResponse())['Empresas'], 0);
        self::assertContains('Alfa Tecnología', $names);
        self::assertNotContains('Beta Logística', $names);
    }

    public function testTemplateHasHeadersAndNoData(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->persist($this->makeCompany($centre, 'Alfa', 'B11111111'));
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/empresas/plantilla');

        self::assertResponseIsSuccessful();
        $sheets = $this->readSheets($this->saveResponse());
        self::assertCount(1, $sheets['Empresas']);
        self::assertSame('Nombre *', $sheets['Empresas'][0][0]);
    }

    public function testExportRequiresSectionPermission(): void
    {
        $centre  = $this->makeCentre('41000001');
        $teacher = (new Teacher(new PersonName('Test', 'Teacher')))->setUsername('teacher.1');
        $this->persist($centre, $teacher);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/empresas/exportar');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/empresas/plantilla');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/empresas/importar');
        self::assertResponseStatusCodeSame(403);
    }

    // ── import ───────────────────────────────────────────────────────────────

    public function testExportedFileImportsWithoutChanges(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $company = $this->makeCompany($centre, 'Empresa S.L.', 'B12345678')
            ->setRepresentativeFirstName('Carmen')
            ->setContactInformation('<p><strong>Llamar</strong> por la mañana</p><ul><li>Tel. 955000000</li></ul>')
            ->setExceptionalCircumstances('Solo turno de mañana');
        $worker = (new Worker(new PersonName('Ana', 'López')))->setNationalIdNumber('12345678A');
        $company->addWorker($worker);
        $workcenter = (new Workcenter())->setName('Sede Norte')->setCity('Dos Hermanas')->setCompany($company);
        $this->persist($worker, $company, $workcenter);
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/empresas/exportar');
        $path = $this->saveResponse();

        $crawler = $this->upload($path);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no contiene cambios', $crawler->text());

        $this->em->clear();
        $stored = $this->em->getRepository(Company::class)->findOneBy(['vatNumber' => 'B12345678']);
        self::assertStringContainsString('<strong>Llamar</strong>', (string) $stored->getContactInformation(), 'La reimportación no debe perder el formato');
    }

    public function testImportCreatesCompaniesWorkcentersAndWorkers(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->loginAs($admin, $centre);

        $path = $this->makeWorkbook([
            'Empresas' => [
                self::COMPANY_HEADERS,
                ['Alfa S.L.', 'B11111111', 'Sevilla', 'Luis', 'Pérez', '11111111H', 'Gerente', "Línea 1\nLínea 2", 'Obs'],
                ['Beta S.A.', 'B22222222', 'Dos Hermanas'],
            ],
            'Centros de trabajo' => [
                self::WORKCENTER_HEADERS,
                ['B11111111', 'Sede Sur', ''],
            ],
            'Empleados' => [
                self::WORKER_HEADERS,
                ['B11111111', 'Marta', 'Gil', '22222222J', 'marta@example.com', '600000000'],
            ],
        ]);

        $crawler = $this->upload($path);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Resumen de la importación', $crawler->text());
        self::assertStringContainsString('2 empresa(s) nueva(s)', $crawler->text());

        $this->em->clear();
        self::assertCount(0, $this->em->getRepository(Company::class)->findAll(), 'La simulación no debe guardar nada');

        $this->confirm($crawler);
        self::assertResponseRedirects('/empresas');

        $this->em->clear();
        $alfa = $this->em->getRepository(Company::class)->findOneBy(['vatNumber' => 'B11111111']);
        self::assertNotNull($alfa);
        self::assertSame('Gerente', $alfa->getRepresentativeRole());
        self::assertSame('<p>Línea 1</p><p>Línea 2</p>', $alfa->getContactInformation());
        self::assertCount(1, $alfa->getWorkers());

        $alfaCentres = $this->em->getRepository(Workcenter::class)->findBy(['company' => $alfa]);
        self::assertCount(1, $alfaCentres);
        self::assertSame('Sede Sur', $alfaCentres[0]->getName());
        self::assertSame('Sevilla', $alfaCentres[0]->getCity(), 'La localidad vacía hereda la de la empresa');

        $beta = $this->em->getRepository(Company::class)->findOneBy(['vatNumber' => 'B22222222']);
        $betaCentres = $this->em->getRepository(Workcenter::class)->findBy(['company' => $beta]);
        self::assertCount(1, $betaCentres, 'Sin hoja de centros se crea el centro por defecto');
        self::assertSame('Sede Principal', $betaCentres[0]->getName());
    }

    public function testImportUpdatesExistingCompanyKeepingValuesOfEmptyCells(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $company = $this->makeCompany($centre, 'Nombre antiguo', 'B12345678')
            ->setRepresentativeFirstName('Carmen')
            ->setRepresentativeRole('Gerente');
        $this->persist($company);
        $this->loginAs($admin, $centre);

        $path = $this->makeWorkbook([
            'Empresas' => [
                self::COMPANY_HEADERS,
                // CIF con otro formato: se reconoce igualmente; representante vacío: se conserva
                ['Nombre nuevo', 'b-12345678', 'Utrera', '', '', '', 'Directora'],
            ],
        ]);

        $crawler = $this->upload($path);
        self::assertStringContainsString('1 empresa(s) actualizada(s)', $crawler->text());
        $this->confirm($crawler);

        $this->em->clear();
        $companies = $this->em->getRepository(Company::class)->findAll();
        self::assertCount(1, $companies, 'No debe duplicarse la empresa');
        self::assertSame('Nombre nuevo', $companies[0]->getName());
        self::assertSame('Utrera', $companies[0]->getCity());
        self::assertSame('Directora', $companies[0]->getRepresentativeRole());
        self::assertSame('Carmen', $companies[0]->getRepresentativeFirstName(), 'Una celda vacía no borra el dato');
    }

    public function testImportLinksExistingWorkerInsteadOfCreatingDuplicate(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $other  = $this->makeCompany($centre, 'Otra', 'B99999999');
        $worker = (new Worker(new PersonName('Ana', 'López')))->setNationalIdNumber('12345678A');
        $other->addWorker($worker);
        $this->persist($worker, $other);
        $this->loginAs($admin, $centre);

        $path = $this->makeWorkbook([
            'Empresas' => [self::COMPANY_HEADERS, ['Alfa', 'B11111111', 'Sevilla']],
            'Empleados' => [self::WORKER_HEADERS, ['B11111111', 'Otro nombre', 'Distinto', '12345678A']],
        ]);

        $crawler = $this->upload($path);
        self::assertStringContainsString('1 empleado(s) vinculado(s)', $crawler->text());
        $this->confirm($crawler);

        $this->em->clear();
        $workers = $this->em->getRepository(Worker::class)->findAll();
        self::assertCount(1, $workers);
        self::assertSame('Ana', $workers[0]->getName()->getFirstName(), 'No se modifican los datos de un empleado existente');
        $alfa = $this->em->getRepository(Company::class)->findOneBy(['vatNumber' => 'B11111111']);
        self::assertCount(1, $alfa->getWorkers());
    }

    public function testImportWithErrorsImportsNothingAndListsSheetAndRow(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->loginAs($admin, $centre);

        $path = $this->makeWorkbook([
            'Empresas' => [
                self::COMPANY_HEADERS,
                ['Alfa', 'B11111111', 'Sevilla'],
                ['', 'B22222222', 'Sevilla'],
                ['Gamma', 'B11111111', 'Sevilla'],
            ],
            'Centros de trabajo' => [self::WORKCENTER_HEADERS, ['ZZZ', 'Sede', '']],
        ]);

        $crawler = $this->upload($path);

        $text = $crawler->text();
        self::assertStringContainsString('No se ha importado nada', $text);
        self::assertStringContainsString('Hoja «Empresas», fila 3', $text);
        self::assertStringContainsString('Hoja «Empresas», fila 4', $text);
        self::assertStringContainsString('Hoja «Centros de trabajo», fila 2', $text);

        $this->em->clear();
        self::assertCount(0, $this->em->getRepository(Company::class)->findAll());
    }

    public function testImportReportsMissingRequiredColumn(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->loginAs($admin, $centre);

        $path = $this->makeWorkbook(['Empresas' => [['Nombre *', 'Localidad *'], ['Alfa', 'Sevilla']]]);

        $crawler = $this->upload($path);

        self::assertStringContainsString('falta la columna obligatoria «CIF/NIF»', $crawler->text());
    }

    public function testLiaisonCannotUpdateCompaniesTheyDoNotManage(): void
    {
        [$centre] = $this->setUpCentreWithAdmin();
        $liaison = (new Teacher(new PersonName('Elena', 'Enlace')))->setUsername('enlace.1');
        $own     = $this->makeCompany($centre, 'Propia', 'B11111111');
        $own->addLiaison($liaison);
        $foreign = $this->makeCompany($centre, 'Ajena', 'B22222222');
        $this->persist($liaison, $own, $foreign);
        $this->loginAs($liaison, $centre);

        $path = $this->makeWorkbook([
            'Empresas' => [
                self::COMPANY_HEADERS,
                ['Propia modificada', 'B11111111', 'Sevilla'],
                ['Ajena modificada', 'B22222222', 'Sevilla'],
            ],
        ]);

        $crawler = $this->upload($path);

        self::assertStringContainsString('Hoja «Empresas», fila 3: no tiene permiso', $crawler->text());
        self::assertStringNotContainsString('fila 2', $crawler->text());

        $this->em->clear();
        self::assertSame('Propia', $this->em->getRepository(Company::class)->findOneBy(['vatNumber' => 'B11111111'])->getName());
        self::assertSame('Ajena', $this->em->getRepository(Company::class)->findOneBy(['vatNumber' => 'B22222222'])->getName());
    }

    public function testImportDoesNotTouchCompaniesOfOtherCentres(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $otherCentre = $this->makeCentre('41000002');
        $foreign     = $this->makeCompany($otherCentre, 'De otro centro', 'B11111111');
        $this->persist($otherCentre, $foreign);
        $this->loginAs($admin, $centre);

        $path = $this->makeWorkbook(['Empresas' => [self::COMPANY_HEADERS, ['Mía', 'B11111111', 'Sevilla']]]);
        $crawler = $this->upload($path);
        $this->confirm($crawler);

        $this->em->clear();
        $companies = $this->em->getRepository(Company::class)->findBy(['vatNumber' => 'B11111111']);
        self::assertCount(2, $companies, 'El mismo CIF puede existir en dos centros');
        $names = array_map(static fn (Company $c): string => $c->getName(), $companies);
        sort($names);
        self::assertSame(['De otro centro', 'Mía'], $names);
    }

    public function testInvalidFilesShowAnErrorInsteadOfFailing(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->loginAs($admin, $centre);

        $fake = sys_get_temp_dir() . '/' . uniqid('nexo_fake_', true) . '.xlsx';
        file_put_contents($fake, 'esto no es un libro de Excel');
        $this->tempFiles[] = $fake;

        $crawler = $this->upload($fake);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('No se ha podido leer el fichero', $crawler->text());

        $csv = sys_get_temp_dir() . '/' . uniqid('nexo_csv_', true) . '.csv';
        file_put_contents($csv, "a;b\n");
        $this->tempFiles[] = $csv;

        $crawler = $this->upload($csv);
        self::assertStringContainsString('formato Excel', $crawler->text());
    }

    public function testImportWithoutFileShowsError(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/empresas/importar');
        $token   = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/empresas/importar', ['_token' => $token]);

        self::assertResponseIsSuccessful();
    }

    public function testImportWithInvalidCsrfIsDenied(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->loginAs($admin, $centre);

        $this->client->request('POST', '/empresas/importar', ['_token' => 'invalido']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/empresas/importar', ['import_confirmed' => '1', '_token' => 'invalido']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testConfirmWithExpiredImportRedirectsBack(): void
    {
        [$centre, $admin] = $this->setUpCentreWithAdmin();
        $this->loginAs($admin, $centre);

        $path    = $this->makeWorkbook(['Empresas' => [self::COMPANY_HEADERS, ['Alfa', 'B11111111', 'Sevilla']]]);
        $crawler = $this->upload($path);
        $form    = $crawler->filter('form[action$="/empresas/importar"]')->form();

        $this->client->request('POST', '/empresas/importar', [
            'import_confirmed' => '1',
            'import_id'        => 'no-es-el-guardado',
            '_token'           => $form['_token']->getValue(),
        ]);

        self::assertResponseRedirects('/empresas/importar');
        $this->em->clear();
        self::assertCount(0, $this->em->getRepository(Company::class)->findAll());
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return array{0: EducationalCentre, 1: Teacher} */
    private function setUpCentreWithAdmin(): array
    {
        $centre = $this->makeCentre('41000001');
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.1')->setAdmin(true);
        $this->persist($centre, $admin);

        return [$centre, $admin];
    }

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
    }

    private function makeCompany(EducationalCentre $centre, string $name, string $vat): Company
    {
        return (new Company())->setName($name)->setVatNumber($vat)->setCity('Sevilla')->setEducationalCentre($centre);
    }

    /** Sube un fichero al formulario de importación y devuelve la página resultante. */
    private function upload(string $path): Crawler
    {
        $crawler = $this->client->request('GET', '/empresas/importar');
        $token   = $crawler->filter('input[name="_token"]')->attr('value');

        return $this->client->request(
            'POST',
            '/empresas/importar',
            ['_token' => $token],
            ['xlsx' => new UploadedFile($path, basename($path), null, null, true)],
        );
    }

    private function confirm(Crawler $preview): void
    {
        $form = $preview->filter('form[action$="/empresas/importar"]')->form();
        $this->client->submit($form);
    }

    private function saveResponse(): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('nexo_test_xlsx_', true) . '.xlsx';
        file_put_contents($path, $this->getStreamedContent());
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Crea un libro con las hojas indicadas (todas las celdas como texto).
     *
     * @param array<string, list<list<string>>> $sheets
     */
    private function makeWorkbook(array $sheets): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid('nexo_test_book_', true) . '.xlsx';
        $this->tempFiles[] = $path;

        $writer = new Writer();
        $writer->openToFile($path);
        $first = true;
        foreach ($sheets as $name => $rows) {
            $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($name);
            $first = false;
            foreach ($rows as $row) {
                $writer->addRow(new Row(array_map(static fn (string $v): StringCell => new StringCell($v), $row)));
            }
        }
        $writer->close();

        return $path;
    }

    /** @return array<string, list<list<string>>> filas por nombre de hoja */
    private function readSheets(string $path): array
    {
        $reader = new Reader();
        $reader->open($path);
        $result = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = array_map(static fn (mixed $v): string => (string) $v, $row->toArray());
            }
            $result[$sheet->getName()] = $rows;
        }
        $reader->close();

        return $result;
    }
}
