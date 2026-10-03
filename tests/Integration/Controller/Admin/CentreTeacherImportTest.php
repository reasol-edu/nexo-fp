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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Importación de docentes desde Séneca: vista previa, selección, correo («Cuenta Google/Microsoft») y
 * retirada del curso de los docentes que no figuran en el listado y no están vinculados a nada.
 */
class CentreTeacherImportTest extends ControllerTestCase
{
    private const HEADER = "\"Empleado/a\",\"Usuario IdEA\",\"Cuenta Google/Microsoft\"\n";

    private Teacher $admin;
    private EducationalCentre $centre;
    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.imp')->setAdmin(true);
        $this->centre = (new EducationalCentre())->setCode('41000002')->setName('IES Test')->setCity('Sevilla');
        $this->year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($this->centre);
        $this->persist($this->admin, $this->centre, $this->year);
        $this->centre->setActiveAcademicYear($this->year);
        $this->flush();
        $this->loginAs($this->admin);
    }

    // ── Vista previa y selección ──────────────────────────────────────────────

    public function testPreviewListsEveryTeacherCheckedByDefaultAndOffersSelectAllAndNone(): void
    {
        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"juan@gmail.com\"\n\"Lopez, Ana\",\"ana.lopez\",\"\"\n");

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('input[name="usernames[]"]'));
        self::assertCount(2, $crawler->filter('input[name="usernames[]"][checked]'), 'Todos marcados por defecto');
        self::assertCount(1, $crawler->filter('button.js-select-all[data-target="teachers"]'));
        self::assertCount(1, $crawler->filter('button.js-select-none[data-target="teachers"]'));
        self::assertSelectorTextContains('body', 'juan@gmail.com');
        self::assertCount(1, $crawler->filter('input[name="import_email"][checked]'), 'Importar el correo, marcado por defecto');
        self::assertCount(0, $crawler->filter('input[name="remove_missing"][checked]'), 'Retirar docentes, desmarcado por defecto');
    }

    public function testConfirmCreatesAllSelectedTeachersWithTheirEmail(): void
    {
        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"juan@gmail.com\"\n\"Lopez, Ana\",\"ana.lopez\",\"ana@outlook.com\"\n");

        $this->confirm($crawler, ['usernames' => ['juan.garcia', 'ana.lopez'], 'import_email' => '1']);

        self::assertResponseRedirects();
        $this->em->clear();
        $juan = $this->teacher('juan.garcia');
        self::assertSame('juan@gmail.com', $juan->getEmail());
        self::assertTrue($juan->isExternal());
        self::assertSame('ana@outlook.com', $this->teacher('ana.lopez')->getEmail());
        self::assertCount(2, $this->reloadedYear()->getTeachers());
    }

    public function testOnlySelectedTeachersAreImported(): void
    {
        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"juan@gmail.com\"\n\"Lopez, Ana\",\"ana.lopez\",\"ana@outlook.com\"\n");

        $this->confirm($crawler, ['usernames' => ['ana.lopez'], 'import_email' => '1']);

        $this->em->clear();
        self::assertNull($this->em->getRepository(Teacher::class)->findOneBy(['username' => 'juan.garcia']));
        self::assertNotNull($this->em->getRepository(Teacher::class)->findOneBy(['username' => 'ana.lopez']));
    }

    public function testUsernameNotInTheFileCannotBeInjectedIntoTheSelection(): void
    {
        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"\"\n");

        $this->confirm($crawler, ['usernames' => ['juan.garcia', 'intruso']]);

        $this->em->clear();
        self::assertNull($this->em->getRepository(Teacher::class)->findOneBy(['username' => 'intruso']));
    }

    // ── Correo electrónico ────────────────────────────────────────────────────

    public function testEmailIsNotImportedWhenTheOptionIsOff(): void
    {
        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"juan@gmail.com\"\n");

        $this->confirm($crawler, ['usernames' => ['juan.garcia']]);   // sin import_email

        $this->em->clear();
        self::assertNull($this->teacher('juan.garcia')->getEmail());
    }

    public function testEmailIsFilledForExistingTeachersOnlyWhenTheyHadNone(): void
    {
        $sinCorreo = $this->makeTeacher('sin.correo');
        $conCorreo = $this->makeTeacher('con.correo')->setEmail('original@centro.es');
        $this->persist($sinCorreo, $conCorreo);
        $this->year->addTeacher($sinCorreo);
        $this->year->addTeacher($conCorreo);
        $this->flush();

        $crawler = $this->upload(self::HEADER
            . "\"Test, Sin\",\"sin.correo\",\"nuevo@gmail.com\"\n\"Test, Con\",\"con.correo\",\"otro@gmail.com\"\n");

        // «con.correo» ya tiene correo y está en el curso: sin cambios, su casilla está desactivada.
        self::assertCount(1, $crawler->filter('input[name="usernames[]"]:not([disabled])'));
        self::assertCount(1, $crawler->filter('input[name="usernames[]"][disabled]'));

        $this->confirm($crawler, ['usernames' => ['sin.correo', 'con.correo'], 'import_email' => '1']);

        $this->em->clear();
        self::assertSame('nuevo@gmail.com', $this->teacher('sin.correo')->getEmail());
        self::assertSame('original@centro.es', $this->teacher('con.correo')->getEmail(), 'Nunca se sobrescribe un correo');
    }

    public function testExistingTeacherOfAnotherYearIsAddedAndGetsHisEmail(): void
    {
        $other = $this->makeTeacher('otro.curso');
        $this->persist($other);

        $crawler = $this->upload(self::HEADER . "\"Test, Otro\",\"otro.curso\",\"otro@gmail.com\"\n");
        $this->confirm($crawler, ['usernames' => ['otro.curso'], 'import_email' => '1']);

        $this->em->clear();
        self::assertSame('otro@gmail.com', $this->teacher('otro.curso')->getEmail());
        self::assertContains('otro.curso', array_map(static fn (Teacher $t): string => $t->getUsername(), $this->reloadedYear()->getTeachers()->toArray()));
    }

    public function testInvalidEmailsInTheFileAreIgnored(): void
    {
        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"no es un correo\"\n");

        $this->confirm($crawler, ['usernames' => ['juan.garcia'], 'import_email' => '1']);

        $this->em->clear();
        self::assertNull($this->teacher('juan.garcia')->getEmail());
    }

    public function testFileWithoutTheEmailColumnStillWorks(): void
    {
        $crawler = $this->upload("\"Empleado/a\",\"Usuario IdEA\"\n\"Garcia, Juan\",\"juan.garcia\"\n");

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[name="import_email"][disabled]'), 'Sin correos en el fichero la opción se desactiva');
        $this->confirm($crawler, ['usernames' => ['juan.garcia']]);
        self::assertResponseRedirects();
    }

    // ── Retirar del curso a quien no está en el listado ──────────────────────

    public function testTeachersNotInTheFileAndWithoutLinksAreRemovedWhenRequested(): void
    {
        $libre = $this->makeTeacher('libre');
        $this->persist($libre);
        $this->year->addTeacher($libre);
        $this->year->addTeacher($this->admin);   // quien importa nunca se ofrece para retirarse a sí mismo
        $this->flush();

        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"\"\n");
        self::assertCount(1, $crawler->filter('input[name="remove_teachers[]"]'), 'Solo «libre»: el admin no se ofrece');

        $this->confirm($crawler, ['usernames' => ['juan.garcia'], 'remove_missing' => '1', 'remove_teachers' => [$libre->getId()->toRfc4122()]]);

        $this->em->clear();
        self::assertNotContains('libre', $this->yearUsernames());
        self::assertNotNull($this->teacher('libre'), 'El docente sigue existiendo en el sistema');
    }

    public function testNothingIsRemovedWhenTheOptionIsOff(): void
    {
        $libre = $this->makeTeacher('libre');
        $this->persist($libre);
        $this->year->addTeacher($libre);
        $this->flush();

        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"\"\n");
        $this->confirm($crawler, ['usernames' => ['juan.garcia'], 'remove_teachers' => [$libre->getId()->toRfc4122()]]);

        $this->em->clear();
        self::assertContains('libre', $this->yearUsernames());
    }

    public function testUncheckedCandidatesAreKept(): void
    {
        $a = $this->makeTeacher('cand.a');
        $b = $this->makeTeacher('cand.b');
        $this->persist($a, $b);
        $this->year->addTeacher($a);
        $this->year->addTeacher($b);
        $this->flush();

        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"\"\n");
        $this->confirm($crawler, ['usernames' => ['juan.garcia'], 'remove_missing' => '1', 'remove_teachers' => [$a->getId()->toRfc4122()]]);

        $this->em->clear();
        self::assertNotContains('cand.a', $this->yearUsernames());
        self::assertContains('cand.b', $this->yearUsernames());
    }

    public function testTeachersLinkedToSomethingThisYearAreNeverRemoved(): void
    {
        $tutor        = $this->makeTeacher('es.tutor');
        $coordinator  = $this->makeTeacher('es.coord');
        $head         = $this->makeTeacher('es.jefe');
        $centreAdmin  = $this->makeTeacher('es.admin.centro');
        $family       = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($this->year)->setHead($head);
        $programme    = (new Programme())->setName('DAW')->setAcademicYear($this->year)->setProfessionalFamily($family)->addCoordinator($coordinator);
        $level        = (new ProgrammeYear())->setName('1.º DAW')->setProgramme($programme);
        $group        = (new Group())->setName('DAW1A')->setProgrammeYear($level)->addTutor($tutor);
        $this->persist($tutor, $coordinator, $head, $centreAdmin, $family, $programme, $level, $group);
        $this->centre->addAdmin($centreAdmin);
        foreach ([$tutor, $coordinator, $head, $centreAdmin] as $t) {
            $this->year->addTeacher($t);
        }
        $this->flush();

        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"\"\n");
        self::assertCount(0, $crawler->filter('input[name="remove_teachers[]"]'), 'Ninguno se ofrece como candidato');

        // Aunque se falsifique el formulario con sus ids, el servidor los vuelve a comprobar.
        $this->confirm($crawler, ['usernames' => ['juan.garcia'], 'remove_missing' => '1',
            'remove_teachers' => array_map(static fn (Teacher $t): string => $t->getId()->toRfc4122(), [$tutor, $coordinator, $head, $centreAdmin])]);

        $this->em->clear();
        foreach (['es.tutor', 'es.coord', 'es.jefe', 'es.admin.centro'] as $username) {
            self::assertContains($username, $this->yearUsernames(), $username);
        }
    }

    public function testTeachersInTheFileAreNeverRemoved(): void
    {
        $inFile = $this->makeTeacher('en.fichero');
        $this->persist($inFile);
        $this->year->addTeacher($inFile);
        $this->flush();

        $crawler = $this->upload(self::HEADER . "\"Test, Teacher\",\"en.fichero\",\"\"\n");
        self::assertCount(0, $crawler->filter('input[name="remove_teachers[]"]'));
        $this->confirm($crawler, ['usernames' => [], 'remove_missing' => '1', 'remove_teachers' => [$inFile->getId()->toRfc4122()]]);

        $this->em->clear();
        self::assertContains('en.fichero', $this->yearUsernames());
    }

    // ── Errores y seguridad ───────────────────────────────────────────────────

    public function testMissingRequiredColumnRedirectsBackToTheForm(): void
    {
        $this->upload("\"Nombre\",\"Otra\"\n\"x\",\"y\"\n");

        self::assertResponseRedirects();
        self::assertStringContainsString('/importar', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testWindows1252FilesAreAccepted(): void
    {
        $csv = mb_convert_encoding(self::HEADER . "\"Muñoz Pérez, José\",\"jose.munoz\",\"\"\n", 'Windows-1252', 'UTF-8');
        $crawler = $this->upload($csv);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Muñoz Pérez, José');
        $this->confirm($crawler, ['usernames' => ['jose.munoz']]);
        $this->em->clear();
        self::assertSame('José', $this->teacher('jose.munoz')->getName()->getFirstName());
    }

    public function testConfirmWithUnknownImportIdIsRejected(): void
    {
        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"\"\n");
        $token   = $crawler->filter('form#teacher-import-form input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->url(), [
            'import_confirmed' => '1', 'import_id' => '11111111-1111-4111-8111-111111111111', '_token' => $token, 'usernames' => ['juan.garcia'],
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/importar', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testConfirmWithInvalidCsrfIsDenied(): void
    {
        $this->client->request('POST', $this->url(), ['import_confirmed' => '1', 'import_id' => 'x', '_token' => 'malo']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testImportIdIsNotUsedAsAPath(): void
    {
        $crawler = $this->upload(self::HEADER . "\"Garcia, Juan\",\"juan.garcia\",\"\"\n");
        $token   = $crawler->filter('form#teacher-import-form input[name="_token"]')->attr('value');

        $this->client->request('POST', $this->url(), [
            'import_confirmed' => '1', 'import_id' => '../../../etc/passwd', '_token' => $token, 'usernames' => ['juan.garcia'],
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->getRepository(Teacher::class)->findOneBy(['username' => 'juan.garcia']));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function url(): string
    {
        return '/admin/centros/' . $this->centre->getId()->toRfc4122() . '/docentes-curso/importar';
    }

    /** Sube el CSV (paso 1) y devuelve la vista previa. */
    private function upload(string $csv): Crawler
    {
        $crawler = $this->client->request('GET', $this->url());
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $tmp = tempnam(sys_get_temp_dir(), 'nexo_imp_');
        file_put_contents($tmp, $csv);
        $result = $this->client->request('POST', $this->url(), ['_token' => $token], ['csv' => new UploadedFile($tmp, 'docentes.csv', 'text/csv', null, true)]);
        @unlink($tmp);

        return $result;
    }

    /** Confirma la vista previa (paso 2) con la selección y las opciones dadas. */
    private function confirm(Crawler $preview, array $fields): void
    {
        $form = $preview->filter('form#teacher-import-form');
        $this->client->request('POST', $this->url(), $fields + [
            'import_confirmed' => '1',
            'import_id'        => $form->filter('input[name="import_id"]')->attr('value'),
            '_token'           => $form->filter('input[name="_token"]')->attr('value'),
        ]);
    }

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }

    private function teacher(string $username): Teacher
    {
        $teacher = $this->em->getRepository(Teacher::class)->findOneBy(['username' => $username]);
        self::assertNotNull($teacher, $username);

        return $teacher;
    }

    private function reloadedYear(): AcademicYear
    {
        return $this->em->find(AcademicYear::class, $this->year->getId());
    }

    /** @return list<string> */
    private function yearUsernames(): array
    {
        return array_map(static fn (Teacher $t): string => $t->getUsername(), $this->reloadedYear()->getTeachers()->toArray());
    }
}
