<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class EducationalCentreControllerTest extends ControllerTestCase
{
    // ── index ─────────────────────────────────────────────────────────────────

    public function testIndexIsAccessibleToAdmin(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros');

        self::assertResponseIsSuccessful();
    }

    public function testIndexDeniesNonAdmin(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/admin/centros');

        self::assertResponseStatusCodeSame(403);
    }

    // ── new ───────────────────────────────────────────────────────────────────

    public function testNewGetRendersForm(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/nuevo');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testNewPostCreatesCentreAndRedirectsToIndex(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin/centros/nuevo');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/nuevo', [
            '_token' => $token,
            'code'   => '41000001',
            'name'   => 'IES Test',
            'city'   => 'Sevilla',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/centros', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testNewPostWithInvalidCsrfIsDenied(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $this->client->request('POST', '/admin/centros/nuevo', [
            '_token' => 'token-invalido',
            'code'   => '41000001',
            'name'   => 'IES Test',
            'city'   => 'Sevilla',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNewPostWithEmptyNameRendersFormAgain(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin/centros/nuevo');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/nuevo', [
            '_token' => $token,
            'code'   => '41000001',
            'name'   => '',
            'city'   => 'Sevilla',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testNewPostWithDuplicateCodeRendersFormAgain(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $this->persist($admin, $centre);
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin/centros/nuevo');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/nuevo', [
            '_token' => $token,
            'code'   => '41000001',
            'name'   => 'IES Duplicado',
            'city'   => 'Malaga',
        ]);

        self::assertResponseIsSuccessful();
    }

    // ── edit ──────────────────────────────────────────────────────────────────

    public function testEditGetRendersForm(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $this->persist($admin, $centre);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditPostSavesChangesAndRedirects(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $this->persist($admin, $centre);
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId);
        $token    = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId, [
            '_token' => $token,
            'code'   => '41000001',
            'name'   => 'IES Modificado',
            'city'   => 'Malaga',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        $updated = $this->em->find(EducationalCentre::class, $centre->getId());
        self::assertSame('IES Modificado', $updated->getName());
        self::assertSame('Malaga', $updated->getCity());
    }

    public function testEditPostWithInvalidCsrfIsDenied(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $this->persist($admin, $centre);
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId, [
            '_token' => 'token-invalido',
            'code'   => '41000001',
            'name'   => 'Hack',
            'city'   => 'Sevilla',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteCentreDeletesEntityAndRedirectsToIndex(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $this->persist($admin, $centre);
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId);
        $token    = $crawler->filter('form[action$="/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/centros', (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(EducationalCentre::class, $centre->getId()));
    }

    public function testDeleteCentreWithInvalidCsrfIsDenied(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $this->persist($admin, $centre);
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/eliminar', ['_token' => 'token-invalido']);

        self::assertResponseStatusCodeSame(403);
    }

    // ── academic year ─────────────────────────────────────────────────────────

    public function testAddYearCreatesYearAndRedirectsToEdit(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $this->persist($admin, $centre);
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId);
        $token    = $crawler->filter('form[action$="/cursos"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/cursos', [
            '_token' => $token,
            'name'   => '2025-2026',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/centros/' . $centreId, (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testAddYearWithInvalidCsrfIsDenied(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $this->persist($admin, $centre);
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/cursos', [
            '_token' => 'token-invalido',
            'name'   => '2025-2026',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testEditYearGetRendersForm(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $year   = $this->makeYear($centre, '2024-2025');
        $this->persist($admin, $centre, $year);
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $yearId   = $year->getId()->toRfc4122();

        $this->client->request('GET', '/admin/centros/' . $centreId . '/cursos/' . $yearId);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditYearPostSavesChanges(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $year   = $this->makeYear($centre, '2024-2025');
        $this->persist($admin, $centre, $year);
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $yearId   = $year->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/admin/centros/' . $centreId . '/cursos/' . $yearId);
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/cursos/' . $yearId, [
            '_token' => $token,
            'name'   => '2025-2026',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        $updated = $this->em->find(AcademicYear::class, $year->getId());
        self::assertSame('2025-2026', $updated->getName());
    }

    public function testDeleteYearDeletesNonActiveYear(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $active = $this->makeYear($centre, '2024-2025');
        $extra  = $this->makeYear($centre, '2023-2024');
        $this->persist($admin, $centre, $active, $extra);
        $centre->setActiveAcademicYear($active);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $yearId   = $extra->getId()->toRfc4122();
        // Delete and activate forms for years live on the centre edit page
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId);
        $token    = $crawler->filter('form[action*="/cursos/' . $yearId . '/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/cursos/' . $yearId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();

        $this->em->clear();
        self::assertNull($this->em->find(AcademicYear::class, $extra->getId()));
    }

    public function testDeleteYearWithDataNeedsExplicitConfirmationAndThenRemovesEverything(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $centre  = $this->makeCentre('41000001');
        $active  = $this->makeYear($centre, '2024-2025');
        $old     = $this->makeYear($centre, '2023-2024');
        $teacher = $this->makeTeacher('docente.1');
        $teacher->addAcademicYear($old);

        $family    = (new \App\Entity\ProfessionalFamily())->setName('Informática')->setAcademicYear($old);
        $programme = (new \App\Entity\Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($old);
        $level     = (new \App\Entity\ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $programme->addCoordinator($teacher);
        $group     = (new \App\Entity\Group())->setName('DAW1A')->setProgrammeYear($level)->addTutor($teacher);
        $student   = (new \App\Entity\Student(new PersonName('Ana', 'Martinez')))->setStudentId('2023-001');
        $group->addStudent($student);
        $stay      = (new \App\Entity\Stay())->setName('Estancia antigua')->setAcademicYear($old)->addProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('2024-03-01'))->setEndDate(new \DateTimeImmutable('2024-06-30'));
        $stay->addStudent($student);
        $company = (new \App\Entity\Company())->setName('Empresa S.L.')->setVatNumber('B12345678')->setCity('Sevilla')->setEducationalCentre($centre);
        $wc      = (new \App\Entity\Workcenter())->setName('Sede')->setCity('Sevilla')->setCompany($company);
        $pos     = (new \App\Entity\TrainingPosition())->setStay($stay)->setWorkcenter($wc)->setStudent($student);
        $pos->addProgrammeYear($level);
        $this->persist($admin, $centre, $active, $old, $teacher, $family, $programme, $level, $group, $student, $stay, $company, $wc, $pos);
        $centre->setActiveAcademicYear($active);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $yearId   = $old->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId);
        $token    = $crawler->filter('form[action*="/cursos/' . $yearId . '/eliminar"] [name="_token"]')->first()->attr('value');

        // El borrado normal no elimina nada: lleva a la pantalla de confirmación.
        $this->client->request('POST', '/admin/centros/' . $centreId . '/cursos/' . $yearId . '/eliminar', ['_token' => $token]);
        self::assertResponseRedirects('/admin/centros/' . $centreId . '/cursos/' . $yearId . '/eliminar-con-datos');
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('1 familia(s) profesional(es), 1 enseñanza(s), 1 nivel(es) y 1 grupo(s)', $crawler->text());
        self::assertStringContainsString('1 estancia(s) con 1 puesto(s) formativo(s)', $crawler->text());
        $confirmToken = $crawler->filter('[name="_token"]')->attr('value');
        $url          = '/admin/centros/' . $centreId . '/cursos/' . $yearId . '/eliminar-con-datos';

        // Con un nombre distinto no se borra nada.
        $crawler = $this->client->request('POST', $url, ['_token' => $confirmToken, 'confirm_name' => 'otro']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('El nombre no coincide', $crawler->text());
        $this->em->clear();
        self::assertNotNull($this->em->find(AcademicYear::class, $old->getId()));

        // Escribiendo el nombre del curso se elimina con todo lo suyo.
        $this->client->request('POST', $url, ['_token' => $confirmToken, 'confirm_name' => '2023-2024']);
        self::assertResponseRedirects('/admin/centros/' . $centreId);

        $this->em->clear();
        self::assertNull($this->em->find(AcademicYear::class, $old->getId()));
        self::assertNull($this->em->find(\App\Entity\Stay::class, $stay->getId()));
        self::assertNull($this->em->find(\App\Entity\TrainingPosition::class, $pos->getId()));
        self::assertNull($this->em->find(\App\Entity\Group::class, $group->getId()));
        self::assertNull($this->em->find(\App\Entity\ProfessionalFamily::class, $family->getId()));
        // Docentes, alumnado y empresas se conservan; el curso activo sigue intacto.
        self::assertNotNull($this->em->find(Teacher::class, $teacher->getId()));
        self::assertNotNull($this->em->find(\App\Entity\Student::class, $student->getId()));
        self::assertNotNull($this->em->find(\App\Entity\Company::class, $company->getId()));
        self::assertNotNull($this->em->find(AcademicYear::class, $active->getId()));
    }

    public function testDeletingAYearLeavesATraceInTheActivityLog(): void
    {
        $_ENV['APP_LOG'] = $_SERVER['APP_LOG'] = 'true';
        try {
            $admin  = $this->makeAdmin('admin.1');
            $centre = $this->makeCentre('41000001');
            $active = $this->makeYear($centre, '2024-2025');
            $old    = $this->makeYear($centre, '2023-2024');
            $this->persist($admin, $centre, $active, $old);
            $centre->setActiveAcademicYear($active);
            $this->flush();
            $this->loginAs($admin);

            $centreId = $centre->getId()->toRfc4122();
            $yearId   = $old->getId()->toRfc4122();
            $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId);
            $token    = $crawler->filter('form[action*="/cursos/' . $yearId . '/eliminar"] [name="_token"]')->first()->attr('value');
            $this->client->request('POST', '/admin/centros/' . $centreId . '/cursos/' . $yearId . '/eliminar', ['_token' => $token]);

            $this->em->clear();
            $entries = $this->em->getRepository(\App\Entity\ActivityLog::class)->findBy(['actionType' => 'year.deleted']);
            self::assertCount(1, $entries);
            self::assertSame('2023-2024', $entries[0]->getData()['year']);
            self::assertSame($centre->getName(), $entries[0]->getData()['centre']);
        } finally {
            $_ENV['APP_LOG'] = $_SERVER['APP_LOG'] = 'false';
        }
    }

    public function testDeleteConfirmationIsNotAvailableForTheActiveYear(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $active = $this->makeYear($centre, '2024-2025');
        $this->persist($admin, $centre, $active);
        $centre->setActiveAcademicYear($active);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/cursos/' . $active->getId()->toRfc4122() . '/eliminar-con-datos');

        self::assertResponseRedirects('/admin/centros/' . $centre->getId()->toRfc4122());
    }

    public function testDeleteYearThatOnlyHasTeacherMembershipsRemovesThem(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $centre  = $this->makeCentre('41000001');
        $active  = $this->makeYear($centre, '2024-2025');
        $extra   = $this->makeYear($centre, '2025-2026');
        $teacher = $this->makeTeacher('docente.1');
        $teacher->addAcademicYear($extra);
        $this->persist($admin, $centre, $active, $extra, $teacher);
        $centre->setActiveAcademicYear($active);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $yearId   = $extra->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId);
        $token    = $crawler->filter('form[action*="/cursos/' . $yearId . '/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/cursos/' . $yearId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();
        $this->em->clear();
        self::assertNull($this->em->find(AcademicYear::class, $extra->getId()));
        self::assertNotNull($this->em->find(Teacher::class, $teacher->getId()));
    }

    public function testActivateYearSetsActiveYear(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $active = $this->makeYear($centre, '2024-2025');
        $other  = $this->makeYear($centre, '2023-2024');
        $this->persist($admin, $centre, $active, $other);
        $centre->setActiveAcademicYear($active);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $otherId  = $other->getId()->toRfc4122();
        // Delete and activate forms for years live on the centre edit page
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId);
        $token    = $crawler->filter('form[action*="/cursos/' . $otherId . '/activar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/cursos/' . $otherId . '/activar', ['_token' => $token]);

        self::assertResponseRedirects();

        $this->em->clear();
        $updated = $this->em->find(EducationalCentre::class, $centre->getId());
        self::assertSame($otherId, $updated->getActiveAcademicYear()->getId()->toRfc4122());
    }

    // ── Preparar curso siguiente ──────────────────────────────────────────────

    public function testRolloverProposesTheNextNameAndShowsWhatWillBeCopied(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $year   = $this->makeYear($centre, '2024-2025');
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/siguiente-curso');

        self::assertResponseIsSuccessful();
        self::assertSame('2025-2026', $crawler->filter('input[name="name"]')->attr('value'));
    }

    public function testRolloverCreatesTheYearWithTheOfferAndTheTeachersWithoutActivatingIt(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $centre  = $this->makeCentre('41000001');
        $year    = $this->makeYear($centre, '2024-2025');
        $teacher = $this->makeTeacher('docente.1');
        $teacher->addAcademicYear($year);
        $family    = (new \App\Entity\ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new \App\Entity\Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level     = (new \App\Entity\ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $group     = (new \App\Entity\Group())->setName('DAW1A')->setProgrammeYear($level)->addTutor($teacher);
        $this->persist($admin, $centre, $year, $teacher, $family, $programme, $level, $group);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/siguiente-curso');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/siguiente-curso', [
            '_token'        => $token,
            'name'          => '2025-2026',
            'copy_offer'    => '1',
            'copy_tutors'   => '1',
            'copy_members'  => '1',
        ]);

        self::assertResponseRedirects('/admin/centros/' . $centreId);
        $this->em->clear();
        $years = $this->em->getRepository(AcademicYear::class)->findBy(['name' => '2025-2026']);
        self::assertCount(1, $years);
        $new = $years[0];
        self::assertSame($year->getId()->toRfc4122(), $this->em->find(EducationalCentre::class, $centre->getId())->getActiveAcademicYear()->getId()->toRfc4122());
        $groups = $this->em->getRepository(\App\Entity\Group::class)->findBy(['name' => 'DAW1A']);
        self::assertCount(2, $groups, 'El grupo existe en el curso anterior y en el nuevo');
        foreach ($groups as $copied) {
            self::assertCount(1, $copied->getTutors(), 'La tutoría de grupo se copia con la oferta');
        }
        self::assertTrue($this->em->find(Teacher::class, $teacher->getId())->getAcademicYears()->contains($new));
    }

    public function testRolloverRejectsADuplicatedYearName(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = $this->makeCentre('41000001');
        $year   = $this->makeYear($centre, '2024-2025');
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/siguiente-curso');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/siguiente-curso', ['_token' => $token, 'name' => '2024-2025']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ya existe un curso con ese nombre');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function makeYear(EducationalCentre $centre, string $name): AcademicYear
    {
        return (new AcademicYear())->setName($name)->setEducationalCentre($centre);
    }
}
