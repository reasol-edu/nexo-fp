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
use App\Service\GroupAssignmentImporter;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Importación de asignaciones de docentes a grupos (CSV «Materia y grupos» de Séneca) con vista previa. */
class GroupAssignmentImportTest extends ControllerTestCase
{
    private const HEADER = "\"Unidad\",\"Profesor/a\"\n";

    private string $centreId;
    private Group $daw;
    private Group $smr;
    private Teacher $ana;
    private Teacher $beto;

    protected function setUp(): void
    {
        parent::setUp();

        $admin     = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.asg')->setAdmin(true);
        $centre    = (new EducationalCentre())->setCode('41000004')->setName('IES Test')->setCity('Sevilla');
        $year      = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level     = (new ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $this->daw = (new Group())->setName('DAW1A')->setProgrammeYear($level);
        $this->smr = (new Group())->setName('SMR1A')->setProgrammeYear($level);
        $this->ana  = (new Teacher(new PersonName('Ana', 'Alonso Gil')))->setUsername('ana.alonso');
        $this->beto = (new Teacher(new PersonName('Beto', 'Bravo Sanz')))->setUsername('beto.bravo');

        $this->persist($admin, $centre, $year, $family, $programme, $level, $this->daw, $this->smr, $this->ana, $this->beto);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $this->centreId = $centre->getId()->toRfc4122();
        $this->loginAs($admin);
    }

    public function testThePreviewClassifiesEveryRowAndNothingIsAppliedYet(): void
    {
        $this->daw->addTeacher($this->beto);   // Beto ya está en DAW1A
        $this->flush();

        $crawler = $this->upload(self::HEADER
            . "\"DAW1A\",\"Alonso Gil, Ana\"\n"        // nueva
            . "\"DAW1A\",\"Bravo Sanz, Beto\"\n"       // ya existente
            . "\"DAW1A\",\"Nadie Nada, Pepe\"\n"       // docente no encontrado
            . "\"XXX9Z\",\"Alonso Gil, Ana\"\n"        // grupo no encontrado
            . "\"\",\"Alonso Gil, Ana\"\n");           // omitida (sin unidad)

        self::assertResponseIsSuccessful();
        self::assertCount(4, $crawler->filter('tbody tr'));
        self::assertCount(1, $crawler->filter('input[name="assignments[]"][checked]'), 'solo la nueva, marcada por defecto');
        self::assertCount(3, $crawler->filter('input[name="assignments[]"][disabled]'));
        $body = (string) $this->client->getResponse()->getContent();
        foreach (['Asignar al grupo', 'Ya está en el grupo', 'Docente no encontrado', 'Grupo no encontrado', '1 fila omitida'] as $text) {
            self::assertStringContainsString($text, $body);
        }
        self::assertCount(1, $crawler->filter('button.js-select-all'));
        self::assertCount(1, $crawler->filter('button.js-select-none'));

        $this->em->clear();
        self::assertSame(['beto.bravo'], $this->usernames($this->daw), 'la vista previa no aplica nada');
    }

    public function testConfirmingAssignsTheSelectedTeachers(): void
    {
        $crawler = $this->upload(self::HEADER . "\"DAW1A\",\"Alonso Gil, Ana\"\n\"SMR1A\",\"Bravo Sanz, Beto\"\n");

        $this->confirm($crawler, $this->keys($crawler));

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame(['ana.alonso'], $this->usernames($this->daw));
        self::assertSame(['beto.bravo'], $this->usernames($this->smr));
    }

    public function testOnlyTheSelectedRowsAreApplied(): void
    {
        $crawler = $this->upload(self::HEADER . "\"DAW1A\",\"Alonso Gil, Ana\"\n\"SMR1A\",\"Bravo Sanz, Beto\"\n");
        $keys    = $this->keys($crawler);

        $this->confirm($crawler, [$keys[0]]);

        $this->em->clear();
        self::assertSame(['ana.alonso'], $this->usernames($this->daw));
        self::assertSame([], $this->usernames($this->smr));
    }

    public function testSelectingNothingAppliesNothing(): void
    {
        $crawler = $this->upload(self::HEADER . "\"DAW1A\",\"Alonso Gil, Ana\"\n");

        $this->confirm($crawler, []);

        $this->em->clear();
        self::assertSame([], $this->usernames($this->daw));
    }

    public function testAKeyThatIsNotInTheFileOrNotActionableIsIgnored(): void
    {
        $this->daw->addTeacher($this->beto);
        $this->flush();
        $crawler = $this->upload(self::HEADER . "\"DAW1A\",\"Bravo Sanz, Beto\"\n");

        // Se fuerzan una clave inventada y la de una fila ya existente: no deben cambiar nada.
        $this->confirm($crawler, ['clave-inventada', GroupAssignmentImporter::key('DAW1A', 'Bravo Sanz, Beto')]);

        $this->em->clear();
        self::assertSame(['beto.bravo'], $this->usernames($this->daw));
    }

    public function testDuplicateRowsAreMergedIntoOne(): void
    {
        $crawler = $this->upload(self::HEADER . "\"DAW1A\",\"Alonso Gil, Ana\"\n\"daw1a\",\"alonso gil, ana\"\n");

        self::assertCount(1, $crawler->filter('tbody tr'));
    }

    public function testAnExpiredPreviewIsRejected(): void
    {
        $crawler = $this->upload(self::HEADER . "\"DAW1A\",\"Alonso Gil, Ana\"\n");
        $form    = $crawler->filter('form#assignments-import-form');

        $this->client->request('POST', $this->url(), [
            'import_confirmed' => '1',
            'import_id'        => '00000000-0000-4000-8000-000000000000',
            '_token'           => $form->filter('input[name="_token"]')->attr('value'),
            'assignments'      => $this->keys($crawler),
        ]);

        self::assertResponseRedirects($this->url());
        $this->em->clear();
        self::assertSame([], $this->usernames($this->daw));
    }

    public function testAFileWithoutTheRequiredColumnsIsRejected(): void
    {
        $this->upload("\"Grupo\",\"Docente\"\n\"DAW1A\",\"Alonso Gil, Ana\"\n");

        self::assertResponseRedirects($this->url());
    }

    public function testConfirmationWithAnInvalidCsrfTokenIsDenied(): void
    {
        $crawler = $this->upload(self::HEADER . "\"DAW1A\",\"Alonso Gil, Ana\"\n");

        $this->client->request('POST', $this->url(), [
            'import_confirmed' => '1',
            'import_id'        => $crawler->filter('input[name="import_id"]')->attr('value'),
            '_token'           => 'token-invalido',
            'assignments'      => $this->keys($crawler),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function url(): string
    {
        return "/admin/centros/{$this->centreId}/docentes-curso/importar-asignaciones";
    }

    private function upload(string $csv): Crawler
    {
        $crawler = $this->client->request('GET', $this->url());
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $tmp = tempnam(sys_get_temp_dir(), 'nexo_asg_');
        file_put_contents($tmp, $csv);
        $result = $this->client->request('POST', $this->url(), ['_token' => $token], ['csv' => new UploadedFile($tmp, 'asignaciones.csv', 'text/csv', null, true)]);
        @unlink($tmp);

        return $result;
    }

    /** @param list<string> $keys */
    private function confirm(Crawler $preview, array $keys): void
    {
        $form = $preview->filter('form#assignments-import-form');
        $this->client->request('POST', $this->url(), [
            'import_confirmed' => '1',
            'import_id'        => $form->filter('input[name="import_id"]')->attr('value'),
            '_token'           => $form->filter('input[name="_token"]')->attr('value'),
            'assignments'      => $keys,
        ]);
    }

    /** @return list<string> claves de las filas con casilla activa */
    private function keys(Crawler $preview): array
    {
        return $preview->filter('input[name="assignments[]"]:not([disabled])')->each(static fn (Crawler $n): string => (string) $n->attr('value'));
    }

    /** @return list<string> */
    private function usernames(Group $group): array
    {
        $loaded = $this->em->getRepository(Group::class)->find($group->getId());
        $names  = array_map(static fn (Teacher $t): string => $t->getUsername(), $loaded->getTeachers()->toArray());
        sort($names);

        return $names;
    }
}
