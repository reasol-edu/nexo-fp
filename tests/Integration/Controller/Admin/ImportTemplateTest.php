<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Plantillas CSV de las importaciones de estudiantes y de docentes. */
class ImportTemplateTest extends ControllerTestCase
{
    private Teacher $admin;
    private Group $group;
    private string $centreId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.tpl')->setAdmin(true);
        $centre      = (new EducationalCentre())->setCode('41000003')->setName('IES Test')->setCity('Sevilla');
        $year        = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family      = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme   = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level       = (new ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        // El grupo se llama como la «Unidad» de las filas de ejemplo de la plantilla.
        $this->group = (new Group())->setName('1º DAW A')->setProgrammeYear($level);
        $this->persist($this->admin, $centre, $year, $family, $programme, $level, $this->group);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $this->centreId = $centre->getId()->toRfc4122();
        $this->loginAs($this->admin);
    }

    public function testTheStudentImportScreenLinksToTheTemplate(): void
    {
        $this->client->request('GET', "/admin/centros/{$this->centreId}/estudiantes/importar");

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/centros/' . $this->centreId . '/estudiantes/plantilla"]');
    }

    public function testTheTeacherImportScreenLinksToTheTemplate(): void
    {
        $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/importar");

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/centros/' . $this->centreId . '/docentes-curso/plantilla"]');
    }

    public function testTheStudentTemplateIsACsvWithTheColumnsTheImportReads(): void
    {
        $this->client->request('GET', "/admin/centros/{$this->centreId}/estudiantes/plantilla");

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="plantilla-estudiantes.csv"', (string) $response->headers->get('Content-Disposition'));

        $content = (string) $response->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content, 'UTF-8 con BOM para que Excel lea bien las tildes');
        self::assertStringContainsString('"Estado Matrícula","Nº Id. Escolar","Primer apellido","Segundo apellido",Nombre,Unidad', $content);
    }

    public function testTheTeacherTemplateIsACsvWithTheColumnsTheImportReads(): void
    {
        $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/plantilla");

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="plantilla-docentes.csv"', (string) $response->headers->get('Content-Disposition'));

        $content = (string) $response->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString('Empleado/a,"Usuario IdEA","Cuenta Google/Microsoft"', $content);
    }

    /** La plantilla descargada se puede subir tal cual a su importación y se reconoce. */
    public function testTheStudentTemplateCanBeUploadedToItsOwnImport(): void
    {
        $this->client->request('GET', "/admin/centros/{$this->centreId}/estudiantes/plantilla");
        $template = (string) $this->client->getResponse()->getContent();

        $crawler = $this->client->request('GET', "/admin/centros/{$this->centreId}/estudiantes/importar");
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');
        $preview = $this->client->request('POST', "/admin/centros/{$this->centreId}/estudiantes/importar", ['_token' => $token], ['csv' => $this->csvFile($template)]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[name="import_confirmed"]');
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('1000001', $body);
        self::assertStringContainsString('1000002', $body);
        self::assertStringNotContainsString('no reconocid', mb_strtolower($body), 'el grupo de ejemplo existe: no debe haber unidades desconocidas');
        self::assertGreaterThanOrEqual(2, $preview->filter('input[type="checkbox"]')->count());
    }

    public function testTheTeacherTemplateCanBeUploadedToItsOwnImport(): void
    {
        $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/plantilla");
        $template = (string) $this->client->getResponse()->getContent();

        $crawler = $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/importar");
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');
        $preview = $this->client->request('POST', "/admin/centros/{$this->centreId}/docentes-curso/importar", ['_token' => $token], ['csv' => $this->csvFile($template)]);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $preview->filter('input[name="usernames[]"]'));
        self::assertSelectorTextContains('body', 'marta.perez@ejemplo.org');
    }

    public function testTheAssignmentsImportScreenLinksToTheTemplate(): void
    {
        $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/importar-asignaciones");

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/centros/' . $this->centreId . '/docentes-curso/importar-asignaciones/plantilla"]');
    }

    public function testTheAssignmentsTemplateIsACsvWithTheColumnsTheImportReads(): void
    {
        $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/importar-asignaciones/plantilla");

        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment; filename="plantilla-asignaciones-docentes.csv"', (string) $response->headers->get('Content-Disposition'));
        self::assertStringStartsWith("\xEF\xBB\xBFUnidad,Profesor/a\n", (string) $response->getContent());
    }

    /** Con los docentes de ejemplo dados de alta, la plantilla descargada los asigna al grupo de ejemplo. */
    public function testTheAssignmentsTemplateCanBeUploadedToItsOwnImport(): void
    {
        $marta = (new Teacher(new PersonName('Marta', 'Pérez Gómez')))->setUsername('mperez001');
        $pablo = (new Teacher(new PersonName('Pablo', 'Sánchez Ruiz')))->setUsername('psanchez002');
        $this->persist($marta, $pablo);

        $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/importar-asignaciones/plantilla");
        $template = (string) $this->client->getResponse()->getContent();

        $url     = "/admin/centros/{$this->centreId}/docentes-curso/importar-asignaciones";
        $crawler = $this->client->request('GET', $url);
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');
        $preview = $this->client->request('POST', $url, ['_token' => $token], ['csv' => $this->csvFile($template)]);

        // Vista previa con las dos asignaciones nuevas, marcadas por defecto.
        self::assertResponseIsSuccessful();
        self::assertCount(2, $preview->filter('input[name="assignments[]"][checked]'));

        $form = $preview->filter('form#assignments-import-form');
        $this->client->request('POST', $url, [
            'import_confirmed' => '1',
            'import_id'        => $form->filter('input[name="import_id"]')->attr('value'),
            '_token'           => $form->filter('input[name="_token"]')->attr('value'),
            'assignments'      => $preview->filter('input[name="assignments[]"]')->each(static fn ($n): string => (string) $n->attr('value')),
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $usernames = array_map(
            static fn (Teacher $t): string => $t->getUsername(),
            $this->em->getRepository(Group::class)->find($this->group->getId())->getTeachers()->toArray(),
        );
        sort($usernames);
        self::assertSame(['mperez001', 'psanchez002'], $usernames);
    }

    public function testSomeoneWithoutAccessCannotDownloadTheTemplates(): void
    {
        $teacher = (new Teacher(new PersonName('Sin', 'Permiso')))->setUsername('sin.permiso');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', "/admin/centros/{$this->centreId}/estudiantes/plantilla");
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/plantilla");
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', "/admin/centros/{$this->centreId}/docentes-curso/importar-asignaciones/plantilla");
        self::assertResponseStatusCodeSame(403);
    }

    private function csvFile(string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'nexo_tpl_');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, 'plantilla.csv', 'text/csv', null, true);
    }
}
