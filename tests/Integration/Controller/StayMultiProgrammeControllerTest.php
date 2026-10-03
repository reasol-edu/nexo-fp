<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\Workcenter;
use App\Tests\Integration\ControllerTestCase;

/**
 * Flujos de estancia con varias enseñanzas: creación y edición, matrícula acotada por coordinación,
 * puestos compartidos con niveles de varias enseñanzas y preferencia temporal.
 */
class StayMultiProgrammeControllerTest extends ControllerTestCase
{
    // ── Crear y editar ────────────────────────────────────────────────────────

    public function testCoordinatorCreatesSharedStayWithSeveralProgrammes(): void
    {
        $s = $this->makeScenario(withStay: false);
        $this->loginAs($s['coordDaw'], $s['centre']);

        $crawler = $this->client->request('GET', '/estancias/nueva');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/nueva', [
            '_token'        => $token,
            'name'          => 'FFEOE compartida',
            'programme_ids' => [$s['daw']->getId()->toRfc4122(), $s['dam']->getId()->toRfc4122()],
            'start_date'    => '2025-03-01',
            'end_date'      => '2025-06-30',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $stay = $this->em->getRepository(Stay::class)->findOneBy(['name' => 'FFEOE compartida']);
        self::assertNotNull($stay);
        self::assertSame('DAM · DAW', $stay->getProgrammeNames());
    }

    public function testNewWithoutProgrammesShowsValidationError(): void
    {
        $s = $this->makeScenario(withStay: false);
        $this->loginAs($s['admin'], $s['centre']);

        $crawler = $this->client->request('GET', '/estancias/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/nueva', [
            '_token'     => $token,
            'name'       => 'Sin enseñanzas',
            'start_date' => '2025-03-01',
            'end_date'   => '2025-06-30',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Debes seleccionar al menos una enseñanza.');
    }

    public function testEditAddsAnotherProgramme(): void
    {
        $s = $this->makeScenario();
        $s['stay']->removeProgramme($s['dam']);
        $this->flush();
        $this->loginAs($s['admin'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/editar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/editar', [
            '_token'        => $token,
            'name'          => 'FFEOE 2025',
            'programme_ids' => [$s['daw']->getId()->toRfc4122(), $s['dam']->getId()->toRfc4122()],
            'start_date'    => '2025-03-01',
            'end_date'      => '2025-06-30',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertCount(2, $this->em->find(Stay::class, $s['stay']->getId())->getProgrammes());
    }

    public function testEditCannotRemoveAProgrammeWithEnrolledStudents(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['admin'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/editar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        // El alumno de DAM está matriculado: DAM no se puede quitar.
        $this->client->request('POST', '/estancias/' . $stayId . '/editar', [
            '_token'        => $token,
            'name'          => 'FFEOE 2025',
            'programme_ids' => [$s['daw']->getId()->toRfc4122()],
            'start_date'    => '2025-03-01',
            'end_date'      => '2025-06-30',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No se puede quitar una enseñanza que ya tiene alumnado matriculado');
        $this->em->clear();
        self::assertCount(2, $this->em->find(Stay::class, $s['stay']->getId())->getProgrammes());
    }

    public function testEditCoordinatorMustKeepAProgrammeTheyManage(): void
    {
        $s = $this->makeScenario(withStudentsEnrolled: false);
        // Sin puestos ni alumnado matriculado ninguna enseñanza está «en uso».
        $this->em->remove($s['shared']);
        $this->em->remove($s['damOnly']);
        $this->flush();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/editar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/editar', [
            '_token'        => $token,
            'name'          => 'FFEOE 2025',
            'programme_ids' => [$s['dam']->getId()->toRfc4122()],
            'start_date'    => '2025-03-01',
            'end_date'      => '2025-06-30',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Debes conservar al menos una enseñanza que tú gestiones.');
    }

    public function testCoordinatorOfOneProgrammeCannotDeleteASharedStay(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $this->client->request('POST', '/estancias/' . $stayId . '/eliminar', ['_token' => 'x']);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNotNull($this->em->find(Stay::class, $s['stay']->getId()));
    }

    // ── Matrícula del alumnado ────────────────────────────────────────────────

    public function testManageStudentsOnlyTouchesTheCoordinatorsOwnStudents(): void
    {
        $s = $this->makeScenario(withStudentsEnrolled: false);
        $s['stay']->addStudent($s['damStudent']); // matriculado por la otra coordinación
        $this->flush();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/estudiantes');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('[name="_token"]')->first()->attr('value');

        // Intenta matricular a su alumno… y de paso a uno de DAM que ya estaba, que no debe tocar.
        $this->client->request('POST', '/estancias/' . $stayId . '/estudiantes', [
            '_token'      => $token,
            'student_ids' => [$s['dawStudent']->getId()->toRfc4122()],
        ]);
        self::assertResponseRedirects();

        $this->em->clear();
        $ids = array_map(
            static fn (Student $st): string => $st->getId()->toRfc4122(),
            $this->em->find(Stay::class, $s['stay']->getId())->getStudents()->toArray(),
        );
        self::assertContains($s['dawStudent']->getId()->toRfc4122(), $ids, 'Matricula a su alumno');
        self::assertContains($s['damStudent']->getId()->toRfc4122(), $ids, 'No quita al alumno de otra enseñanza');
    }

    public function testManageStudentsIgnoresForeignStudentIds(): void
    {
        $s = $this->makeScenario(withStudentsEnrolled: false);
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/estudiantes');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/estudiantes', [
            '_token'      => $token,
            'student_ids' => [$s['dawStudent']->getId()->toRfc4122(), $s['damStudent']->getId()->toRfc4122()],
        ]);

        $this->em->clear();
        $ids = array_map(
            static fn (Student $st): string => $st->getId()->toRfc4122(),
            $this->em->find(Stay::class, $s['stay']->getId())->getStudents()->toArray(),
        );
        self::assertSame([$s['dawStudent']->getId()->toRfc4122()], $ids, 'Un id de otra enseñanza no se puede matricular');
    }

    public function testManageStudentsPageHidesTheNieOfForeignStudents(): void
    {
        $s = $this->makeScenario(withStudentsEnrolled: false);
        $this->loginAs($s['coordDaw'], $s['centre']);

        $this->client->request('GET', '/estancias/' . $s['stay']->getId()->toRfc4122() . '/estudiantes');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Luis Dam', $html);
        self::assertStringContainsString('NIE-DAW', $html);
        self::assertStringNotContainsString('NIE-DAM', $html);
    }

    // ── Puestos ───────────────────────────────────────────────────────────────

    public function testNewPositionOffersLevelsOfEveryProgrammeAndStoresThem(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/nuevo-puesto');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('input[value="' . $s['dawLevel']->getId()->toRfc4122() . '"]'));
        self::assertCount(1, $crawler->filter('input[value="' . $s['damLevel']->getId()->toRfc4122() . '"]'));
        self::assertSelectorExists('select[name="priority_programme_id"]');
        $token = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/nuevo-puesto', [
            '_token'             => $token,
            'workcenter_id'      => $s['workcenter']->getId()->toRfc4122(),
            'programme_year_ids' => [$s['dawLevel']->getId()->toRfc4122(), $s['damLevel']->getId()->toRfc4122()],
            'details'            => 'Puesto compartido',
            'count'              => '1',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $created = $this->em->getRepository(TrainingPosition::class)->findOneBy(['details' => 'Puesto compartido']);
        self::assertNotNull($created);
        self::assertCount(2, $created->getProgrammeYears());
        self::assertNull($created->getPriorityProgramme());
    }

    public function testNewPositionStoresPriorityForOneOfTheOfferedProgrammes(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/nuevo-puesto');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/nuevo-puesto', [
            '_token'                => $token,
            'workcenter_id'         => $s['workcenter']->getId()->toRfc4122(),
            'programme_year_ids'    => [$s['dawLevel']->getId()->toRfc4122(), $s['damLevel']->getId()->toRfc4122()],
            'priority_programme_id' => $s['daw']->getId()->toRfc4122(),
            'priority_until'        => '2025-04-15',
            'details'               => 'Con preferencia',
            'count'                 => '1',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $created = $this->em->getRepository(TrainingPosition::class)->findOneBy(['details' => 'Con preferencia']);
        self::assertSame('DAW', $created->getPriorityProgramme()?->getName());
        self::assertSame('2025-04-15', $created->getPriorityUntil()?->format('Y-m-d'));
    }

    public function testNewPositionRejectsPriorityForAProgrammeNotOffered(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/nuevo-puesto');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        // Oferta solo a DAW pero pide preferencia para DAM.
        $this->client->request('POST', '/estancias/' . $stayId . '/nuevo-puesto', [
            '_token'                => $token,
            'workcenter_id'         => $s['workcenter']->getId()->toRfc4122(),
            'programme_year_ids'    => [$s['dawLevel']->getId()->toRfc4122()],
            'priority_programme_id' => $s['dam']->getId()->toRfc4122(),
            'priority_until'        => '2025-04-15',
            'count'                 => '1',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'La enseñanza preferente debe ser una de las enseñanzas a las que se oferta el puesto.');
    }

    public function testNewPositionRequiresADateWhenAPriorityProgrammeIsChosen(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId = $s['stay']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/nuevo-puesto');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/nuevo-puesto', [
            '_token'                => $token,
            'workcenter_id'         => $s['workcenter']->getId()->toRfc4122(),
            'programme_year_ids'    => [$s['dawLevel']->getId()->toRfc4122(), $s['damLevel']->getId()->toRfc4122()],
            'priority_programme_id' => $s['daw']->getId()->toRfc4122(),
            'count'                 => '1',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Indica hasta qué fecha (inclusive) tiene preferencia esa enseñanza.');
    }

    public function testEditPositionOffersOnlyOwnStudentsToCoordinator(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);

        $crawler = $this->client->request('GET', '/estancias/' . $s['stay']->getId()->toRfc4122() . '/puesto/' . $s['shared']->getId()->toRfc4122() . '/editar');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#student_id option[value="' . $s['dawStudent']->getId()->toRfc4122() . '"]'));
        self::assertCount(0, $crawler->filter('#student_id option[value="' . $s['damStudent']->getId()->toRfc4122() . '"]'));
    }

    public function testEditPositionRejectsAssigningAForeignStudent(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId     = $s['stay']->getId()->toRfc4122();
        $positionId = $s['shared']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token'             => $token,
            'version'            => (string) $s['shared']->getVersion(),
            'workcenter_id'      => $s['workcenter']->getId()->toRfc4122(),
            'programme_year_ids' => [$s['dawLevel']->getId()->toRfc4122(), $s['damLevel']->getId()->toRfc4122()],
            'student_id'         => $s['damStudent']->getId()->toRfc4122(),
            'state'              => 'DRAFT',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ese estudiante pertenece a otra coordinación');
        $this->em->clear();
        self::assertNull($this->em->find(TrainingPosition::class, $s['shared']->getId())->getStudent());
    }

    public function testEditPositionRejectsAStudentWhoseLevelIsNotOffered(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId     = $s['stay']->getId()->toRfc4122();
        $positionId = $s['damOnly']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token'             => $token,
            'version'            => (string) $s['damOnly']->getVersion(),
            'workcenter_id'      => $s['workcenter']->getId()->toRfc4122(),
            'programme_year_ids' => [$s['damLevel']->getId()->toRfc4122()],
            'student_id'         => $s['dawStudent']->getId()->toRfc4122(),
            'state'              => 'DRAFT',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ese puesto no está ofertado al nivel de este estudiante.');
    }

    public function testEditPositionAssignsOwnStudent(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId     = $s['stay']->getId()->toRfc4122();
        $positionId = $s['shared']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token'             => $token,
            'version'            => (string) $s['shared']->getVersion(),
            'workcenter_id'      => $s['workcenter']->getId()->toRfc4122(),
            'programme_year_ids' => [$s['dawLevel']->getId()->toRfc4122(), $s['damLevel']->getId()->toRfc4122()],
            'student_id'         => $s['dawStudent']->getId()->toRfc4122(),
            'state'              => 'DRAFT',
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame(
            $s['dawStudent']->getId()->toRfc4122(),
            $this->em->find(TrainingPosition::class, $s['shared']->getId())->getStudent()?->getId()->toRfc4122(),
        );
    }

    public function testEditPositionRejectsAStudentOfAnotherProgrammeWhileItHasPriority(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setPriority($s['dam'], new \DateTimeImmutable('+10 days'));
        $this->flush();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId     = $s['stay']->getId()->toRfc4122();
        $positionId = $s['shared']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token'                => $token,
            'version'               => (string) $s['shared']->getVersion(),
            'workcenter_id'         => $s['workcenter']->getId()->toRfc4122(),
            'programme_year_ids'    => [$s['dawLevel']->getId()->toRfc4122(), $s['damLevel']->getId()->toRfc4122()],
            'priority_programme_id' => $s['dam']->getId()->toRfc4122(),
            'priority_until'        => (new \DateTimeImmutable('+10 days'))->format('Y-m-d'),
            'student_id'            => $s['dawStudent']->getId()->toRfc4122(),
            'state'                 => 'DRAFT',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ese puesto tiene preferencia para otra enseñanza hasta la fecha indicada.');
    }

    public function testCoordinatorCannotEditAnotherProgrammesAssignedPosition(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setStudent($s['damStudent']);
        $this->flush();
        $this->loginAs($s['coordDaw'], $s['centre']);

        $this->client->request('GET', '/estancias/' . $s['stay']->getId()->toRfc4122() . '/puesto/' . $s['shared']->getId()->toRfc4122() . '/editar');

        self::assertResponseStatusCodeSame(403);
    }

    public function testCoordinatorCanEditAndDeleteAFreeSharedPosition(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDam'], $s['centre']);
        $stayId     = $s['stay']->getId()->toRfc4122();
        $positionId = $s['shared']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form[action*="' . $positionId . '/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->find(TrainingPosition::class, $s['shared']->getId()));
    }

    public function testDuplicatePositionKeepsPriority(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setPriority($s['daw'], new \DateTimeImmutable('2025-04-15'));
        $this->flush();
        $this->loginAs($s['coordDaw'], $s['centre']);
        $stayId     = $s['stay']->getId()->toRfc4122();
        $positionId = $s['shared']->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId);
        $token   = $crawler->filter('form[action*="' . $positionId . '/duplicar"] [name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/duplicar', ['_token' => $token]);

        self::assertResponseRedirects();
        $this->em->clear();
        $copies = array_filter(
            $this->em->getRepository(TrainingPosition::class)->findAll(),
            static fn (TrainingPosition $p): bool => $p->getPriorityProgramme() !== null,
        );
        self::assertCount(2, $copies);
    }

    // ── Pantallas y exportaciones ─────────────────────────────────────────────

    public function testShowRendersForCoordinatorOfEitherProgramme(): void
    {
        $s = $this->makeScenario();

        foreach (['coordDaw', 'coordDam'] as $who) {
            $this->loginAs($s[$who], $s['centre']);
            $this->client->request('GET', '/estancias/' . $s['stay']->getId()->toRfc4122());
            self::assertResponseIsSuccessful();
        }
    }

    public function testExportIncludesProgrammeColumnAndHidesForeignNie(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setStudent($s['damStudent']);
        $s['damOnly']->setStudent($s['dawStudent']);
        $this->flush();
        $this->loginAs($s['coordDaw'], $s['centre']);

        $this->client->request('GET', '/estancias/' . $s['stay']->getId()->toRfc4122() . '/exportar');

        self::assertResponseIsSuccessful();
        $values = $this->parseXlsxResponse();
        self::assertContains('Enseñanza', $values);
        self::assertContains('DAW', $values);
        self::assertContains('DAM', $values);
        self::assertContains('NIE-DAW', $values);
        self::assertNotContains('NIE-DAM', $values);
    }

    public function testPdfReportRendersForSharedStay(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['coordDaw'], $s['centre']);

        $this->client->request('GET', '/estancias/' . $s['stay']->getId()->toRfc4122() . '/informe');

        self::assertResponseIsSuccessful();
    }

    public function testIndexFiltersByEitherProgrammeOfASharedStay(): void
    {
        $s = $this->makeScenario();
        $this->loginAs($s['admin'], $s['centre']);

        foreach ([$s['daw'], $s['dam']] as $programme) {
            $this->client->request('GET', '/estancias?programmeId=' . $programme->getId()->toRfc4122());
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('FFEOE 2025', (string) $this->client->getResponse()->getContent());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function parseXlsxResponse(): array
    {
        $tmpFile = sys_get_temp_dir() . '/' . uniqid('nexo_test_xlsx_', true) . '.xlsx';
        file_put_contents($tmpFile, $this->getStreamedContent());

        try {
            $reader = new \OpenSpout\Reader\XLSX\Reader();
            $reader->open($tmpFile);
            $values = [];
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    foreach ($row->cells as $cell) {
                        $v = $cell->getValue();
                        if ($v !== '' && $v !== null) {
                            $values[] = (string) $v;
                        }
                    }
                }
                break;
            }
            $reader->close();
        } finally {
            @unlink($tmpFile);
        }

        return $values;
    }

    /**
     * @return array{
     *     admin: Teacher, coordDaw: Teacher, coordDam: Teacher, centre: EducationalCentre, daw: Programme, dam: Programme,
     *     dawLevel: ProgrammeYear, damLevel: ProgrammeYear, dawStudent: Student, damStudent: Student, workcenter: Workcenter,
     *     stay: Stay, shared: TrainingPosition, damOnly: TrainingPosition
     * }
     */
    private function makeScenario(bool $withStay = true, bool $withStudentsEnrolled = true): array
    {
        $admin    = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.mpc')->setAdmin(true);
        $coordDaw = (new Teacher(new PersonName('Coord', 'Daw')))->setUsername('coord.daw');
        $coordDam = (new Teacher(new PersonName('Coord', 'Dam')))->setUsername('coord.dam');
        $centre   = (new EducationalCentre())->setCode('41000078')->setName('IES Test')->setCity('Sevilla');
        $year     = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family   = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);

        $daw = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year)->addCoordinator($coordDaw);
        $dam = (new Programme())->setName('DAM')->setProfessionalFamily($family)->setAcademicYear($year)->addCoordinator($coordDam);

        $dawLevel = (new ProgrammeYear())->setName('2.º DAW')->setProgramme($daw);
        $damLevel = (new ProgrammeYear())->setName('2.º DAM')->setProgramme($dam);
        $dawGroup = (new Group())->setName('DAW2A')->setProgrammeYear($dawLevel);
        $damGroup = (new Group())->setName('DAM2A')->setProgrammeYear($damLevel);

        $dawStudent = (new Student(new PersonName('Ana', 'Daw')))->setStudentId('NIE-DAW');
        $damStudent = (new Student(new PersonName('Luis', 'Dam')))->setStudentId('NIE-DAM');
        $dawGroup->addStudent($dawStudent);
        $damGroup->addStudent($damStudent);

        $stay = (new Stay())
            ->setName('FFEOE 2025')
            ->setAcademicYear($year)
            ->addProgramme($daw)
            ->addProgramme($dam)
            ->setStartDate(new \DateTimeImmutable('2025-03-01'))
            ->setEndDate(new \DateTimeImmutable('2025-06-30'));
        if ($withStudentsEnrolled) {
            $stay->addStudent($dawStudent)->addStudent($damStudent);
        }

        $company    = (new Company())->setName('Empresa Test S.L.')->setVatNumber('B12345678')->setCity('Sevilla')->setEducationalCentre($centre);
        $workcenter = (new Workcenter())->setName('Centro Principal')->setCity('Sevilla')->setCompany($company);

        $shared  = (new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter)->addProgrammeYear($dawLevel)->addProgrammeYear($damLevel)->setDetails('Compartido');
        $damOnly = (new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter)->addProgrammeYear($damLevel)->setDetails('Solo DAM');

        $entities = [$admin, $coordDaw, $coordDam, $centre, $year, $family, $daw, $dam, $dawLevel, $damLevel, $dawGroup, $damGroup, $dawStudent, $damStudent, $company, $workcenter];
        if ($withStay) {
            array_push($entities, $stay, $shared, $damOnly);
        }
        $this->persist(...$entities);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        return compact('admin', 'coordDaw', 'coordDam', 'centre', 'daw', 'dam', 'dawLevel', 'damLevel', 'dawStudent', 'damStudent', 'workcenter', 'stay', 'shared', 'damOnly');
    }
}
