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
use App\Entity\TrainingPositionState;
use App\Entity\Workcenter;
use App\Tests\Integration\ControllerTestCase;

class StayControllerTest extends ControllerTestCase
{
    // ── index ─────────────────────────────────────────────────────────────────

    public function testIndexRedirectsWhenNoTenantSelected(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin); // no centre → no tenant

        $this->client->request('GET', '/estancias');

        self::assertResponseRedirects();
        self::assertStringContainsString('/centro', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testIndexIsAccessibleWithTenant(): void
    {
        [$admin, $centre, $year] = $this->makeAdminCentreYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/estancias');

        self::assertResponseIsSuccessful();
    }

    // ── new ───────────────────────────────────────────────────────────────────

    public function testNewRedirectsWhenNoActiveYear(): void
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $this->persist($admin, $centre); // no active year
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/estancias/nueva');

        self::assertResponseRedirects();
        self::assertStringContainsString('/estancias', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testNewGetRendersForm(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $this->persist($admin, $centre, $year, $family, $programme);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/estancias/nueva');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testNewPostCreatesStayAndRedirectsToIndex(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $this->persist($admin, $centre, $year, $family, $programme);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/estancias/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/nueva', [
            '_token'       => $token,
            'name'         => 'Estancia DAW 2025',
            'programme_id' => $programme->getId()->toRfc4122(),
            'start_date'   => '2025-03-01',
            'end_date'     => '2025-06-30',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estancias', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testNewPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $this->persist($admin, $centre, $year, $family, $programme);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('POST', '/estancias/nueva', [
            '_token'       => 'token-invalido',
            'name'         => 'Estancia DAW 2025',
            'programme_id' => $programme->getId()->toRfc4122(),
            'start_date'   => '2025-03-01',
            'end_date'     => '2025-06-30',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNewPostWithEmptyNameRendersFormAgain(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $this->persist($admin, $centre, $year, $family, $programme);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/estancias/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/nueva', [
            '_token'       => $token,
            'name'         => '',
            'programme_id' => $programme->getId()->toRfc4122(),
            'start_date'   => '2025-03-01',
            'end_date'     => '2025-06-30',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testNewPostWithDuplicateNameRendersFormAgain(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia Existente', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/estancias/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/nueva', [
            '_token'       => $token,
            'name'         => 'Estancia Existente',
            'programme_id' => $programme->getId()->toRfc4122(),
            'start_date'   => '2025-03-01',
            'end_date'     => '2025-06-30',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testNewPostWithEndDateBeforeStartRendersFormAgain(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $this->persist($admin, $centre, $year, $family, $programme);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/estancias/nueva');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/nueva', [
            '_token'       => $token,
            'name'         => 'Estancia DAW 2025',
            'programme_id' => $programme->getId()->toRfc4122(),
            'start_date'   => '2025-06-30',
            'end_date'     => '2025-03-01', // antes del inicio
        ]);

        self::assertResponseIsSuccessful();
    }

    // ── show ──────────────────────────────────────────────────────────────────

    public function testShowRendersStayPage(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/estancias/' . $stay->getId()->toRfc4122());

        self::assertResponseIsSuccessful();
    }

    public function testShowReturns404ForStayFromDifferentYear(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $centre  = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $yearOld = (new AcademicYear())->setName('2023-2024')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        // Stay belongs to yearOld, but centre's active year is year
        $stay    = $this->makeStay('Estancia Antigua', $yearOld, $prog);
        $this->persist($admin, $centre, $year, $yearOld, $family, $prog, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/estancias/' . $stay->getId()->toRfc4122());

        self::assertResponseStatusCodeSame(404);
    }

    // ── edit ──────────────────────────────────────────────────────────────────

    public function testEditGetRendersForm(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/estancias/' . $stay->getId()->toRfc4122() . '/editar');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditPostSavesChangesAndRedirectsToShow(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId  = $stay->getId()->toRfc4122();
        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/editar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/editar', [
            '_token'     => $token,
            'name'       => 'Estancia DAW Modificada',
            'start_date' => '2025-03-01',
            'end_date'   => '2025-06-30',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estancias/' . $stayId, (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        $updated = $this->em->find(Stay::class, $stay->getId());
        self::assertSame('Estancia DAW Modificada', $updated->getName());
    }

    public function testEditPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId = $stay->getId()->toRfc4122();
        $this->client->request('POST', '/estancias/' . $stayId . '/editar', [
            '_token'     => 'token-invalido',
            'name'       => 'Hack',
            'start_date' => '2025-03-01',
            'end_date'   => '2025-06-30',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testEditDeniesTeacherWithoutPermission(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay    = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $teacher);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/estancias/' . $stay->getId()->toRfc4122() . '/editar');

        self::assertResponseStatusCodeSame(403);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteStayDeletesEntityAndRedirectsToIndex(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia a Borrar', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId  = $stay->getId()->toRfc4122();
        $crawler = $this->client->request('GET', '/estancias/' . $stayId);
        $token   = $crawler->filter('form[action*="' . $stayId . '/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estancias', (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(Stay::class, $stay->getId()));
    }

    public function testDeleteStayWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId = $stay->getId()->toRfc4122();
        $this->client->request('POST', '/estancias/' . $stayId . '/eliminar', ['_token' => 'token-invalido']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteDeniesTeacherWithoutPermission(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay    = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $teacher);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($teacher, $centre);

        // Even with a (hypothetical) valid CSRF token, access is denied before the CSRF check
        $stayId = $stay->getId()->toRfc4122();
        $this->client->request('POST', '/estancias/' . $stayId . '/eliminar', ['_token' => 'any']);

        self::assertResponseStatusCodeSame(403);
    }

    // ── new position ──────────────────────────────────────────────────────────

    public function testNewPositionGetRendersForm(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/estancias/' . $stay->getId()->toRfc4122() . '/nuevo-puesto');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testNewPositionPostCreatesPositionAndRedirectsToShow(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId  = $stay->getId()->toRfc4122();
        $crawler = $this->client->request('GET', '/estancias/' . $stayId . '/nuevo-puesto');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/nuevo-puesto', [
            '_token'       => $token,
            'workcenter_id' => $workcenter->getId()->toRfc4122(),
            'count'        => '1',
            'details'      => '',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estancias/' . $stayId, (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testNewPositionPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId = $stay->getId()->toRfc4122();
        $this->client->request('POST', '/estancias/' . $stayId . '/nuevo-puesto', [
            '_token'        => 'token-invalido',
            'workcenter_id' => $workcenter->getId()->toRfc4122(),
            'count'         => '1',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNewPositionDeniesTeacherWithoutPermission(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay    = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $teacher);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/estancias/' . $stay->getId()->toRfc4122() . '/nuevo-puesto');

        self::assertResponseStatusCodeSame(403);
    }

    // ── delete position ───────────────────────────────────────────────────────

    public function testDeletePositionDeletesEntityAndRedirectsToShow(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/estancias/' . $stayId);
        $token   = $crawler->filter('form[action*="/puesto/' . $positionId . '/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estancias/' . $stayId, (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(TrainingPosition::class, $position->getId()));
    }

    public function testDeletePositionWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();
        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/eliminar', ['_token' => 'token-invalido']);

        self::assertResponseStatusCodeSame(403);
    }

    // ── edit position ─────────────────────────────────────────────────────────

    public function testEditPositionGetRendersForm(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();
        $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditPositionPostSavesDetailsAndRedirectsToShow(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();
        $crawler    = $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');
        $token      = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token'        => $token,
            'workcenter_id' => $workcenter->getId()->toRfc4122(),
            'details'       => 'Observaciones nuevas',
            'state'         => 'DRAFT',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estancias/' . $stayId, (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        $updated = $this->em->find(TrainingPosition::class, $position->getId());
        self::assertSame('Observaciones nuevas', $updated->getDetails());
    }

    public function testEditPositionPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();
        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token' => 'token-invalido',
            'state'  => 'DRAFT',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testEditPositionStateNonDraftRequiresTutors(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();
        $crawler    = $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');
        $token      = $crawler->filter('[name="_token"]')->first()->attr('value');

        // PENDING state without academic tutor or workplace mentor → error
        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token'        => $token,
            'workcenter_id' => $workcenter->getId()->toRfc4122(),
            'state'         => 'PENDING',
            // no academic_tutor_id, no workplace_mentor_id
        ]);

        self::assertResponseIsSuccessful(); // permanece en el formulario
    }

    public function testEditPositionSignedRequiresDoneState(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();
        $crawler    = $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');
        $token      = $crawler->filter('[name="_token"]')->first()->attr('value');

        // signed = true but state = DRAFT → error
        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token'        => $token,
            'workcenter_id' => $workcenter->getId()->toRfc4122(),
            'state'         => 'DRAFT',
            'signed'        => '1',
        ]);

        self::assertResponseIsSuccessful(); // permanece en el formulario
    }

    public function testEditPositionLocksAssignmentFieldsWhenNotDraft(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay        = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company     = $this->makeCompany($centre);
        $workcenter  = $this->makeWorkcenter($company);
        $workcenter2 = $this->makeWorkcenter($company, 'Delegación B');
        $position    = $this->makePosition($stay, $workcenter);
        $position->setState(TrainingPositionState::PENDING);
        $tutor  = $this->makeAdmin('admin.2');
        $position->setAcademicTutor($tutor);
        // Set a worker as workplace mentor
        $worker = new \App\Entity\Worker(new PersonName('Maria', 'Garcia'));
        $worker->setNationalIdNumber('12345678A');
        $company->addWorker($worker);
        $position->setWorkplaceMentor($worker);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $workcenter2, $tutor, $worker, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();
        $crawler    = $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');
        $token      = $crawler->filter('[name="_token"]')->first()->attr('value');

        // Attempt to change workcenter to workcenter2 — locked because state is PENDING
        $this->client->request('POST', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar', [
            '_token'               => $token,
            'workcenter_id'        => $workcenter2->getId()->toRfc4122(),
            'state'                => 'PENDING',
            'academic_tutor_id'    => $tutor->getId()->toRfc4122(),
            'workplace_mentor_id'  => $worker->getId()->toRfc4122(),
        ]);

        self::assertResponseRedirects();

        // The workcenter must NOT have changed (assignment locked)
        $this->em->clear();
        $updated = $this->em->find(TrainingPosition::class, $position->getId());
        self::assertSame($workcenter->getId()->toRfc4122(), $updated->getWorkcenter()->getId()->toRfc4122());
    }

    // ── manage students ───────────────────────────────────────────────────────

    public function testManageStudentsGetRendersForm(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        [$level, $group, $student] = $this->makeGroupWithStudent($programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $level, $group, $student);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/estancias/' . $stay->getId()->toRfc4122() . '/estudiantes');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testManageStudentsPostAddsStudentAndRedirectsToShow(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        [$level, $group, $student] = $this->makeGroupWithStudent($programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay, $level, $group, $student);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId    = $stay->getId()->toRfc4122();
        $studentId = $student->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/estancias/' . $stayId . '/estudiantes');
        $token     = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/estancias/' . $stayId . '/estudiantes', [
            '_token'      => $token,
            'student_ids' => [$studentId],
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estancias/' . $stayId, (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        $updated = $this->em->find(Stay::class, $stay->getId());
        self::assertCount(1, $updated->getStudents());
    }

    public function testManageStudentsPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $this->persist($admin, $centre, $year, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $stayId = $stay->getId()->toRfc4122();
        $this->client->request('POST', '/estancias/' . $stayId . '/estudiantes', [
            '_token' => 'token-invalido',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── canManagePositions: acceso por rol ────────────────────────────────────

    public function testCentreAdminCanAccessEditPosition(): void
    {
        $centreAdmin = $this->makeTeacher('centre.admin');
        [$globalAdmin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay       = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $company    = $this->makeCompany($centre);
        $workcenter = $this->makeWorkcenter($company);
        $position   = $this->makePosition($stay, $workcenter);
        $this->persist($globalAdmin, $centreAdmin, $centre, $year, $family, $programme, $stay, $company, $workcenter, $position);
        $centre->setActiveAcademicYear($year);
        $centre->addAdmin($centreAdmin);
        $this->flush();
        $this->loginAs($centreAdmin, $centre);

        $stayId     = $stay->getId()->toRfc4122();
        $positionId = $position->getId()->toRfc4122();
        $this->client->request('GET', '/estancias/' . $stayId . '/puesto/' . $positionId . '/editar');

        self::assertResponseIsSuccessful();
    }

    public function testProgrammeCoordinatorCanAccessEditStay(): void
    {
        $coordinator = $this->makeTeacher('coordinator.1');
        [$globalAdmin, $centre, $year, $family, $programme] = $this->makeFullContext();
        $stay = $this->makeStay('Estancia DAW 2025', $year, $programme);
        $this->persist($globalAdmin, $coordinator, $centre, $year, $family, $programme, $stay);
        $programme->addCoordinator($coordinator);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($coordinator, $centre);

        $this->client->request('GET', '/estancias/' . $stay->getId()->toRfc4122() . '/editar');

        self::assertResponseIsSuccessful();
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

    /** @return array{0: Teacher, 1: EducationalCentre, 2: AcademicYear} */
    private function makeAdminCentreYear(): array
    {
        $admin  = $this->makeAdmin('admin.1');
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        return [$admin, $centre, $year];
    }

    /** @return array{0: Teacher, 1: EducationalCentre, 2: AcademicYear, 3: ProfessionalFamily, 4: Programme} */
    private function makeFullContext(): array
    {
        [$admin, $centre, $year] = $this->makeAdminCentreYear();
        $family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);

        return [$admin, $centre, $year, $family, $programme];
    }

    private function makeStay(string $name, AcademicYear $year, Programme $programme): Stay
    {
        $stay = new Stay();
        $stay->setName($name)
             ->setAcademicYear($year)
             ->setProgramme($programme)
             ->setStartDate(new \DateTimeImmutable('2025-03-01'))
             ->setEndDate(new \DateTimeImmutable('2025-06-30'));

        return $stay;
    }

    private function makeCompany(EducationalCentre $centre): Company
    {
        return (new Company())->setName('Empresa Test S.L.')->setVatNumber('B12345678')->setCity('Sevilla')->setEducationalCentre($centre);
    }

    private function makeWorkcenter(Company $company, string $name = 'Centro de Trabajo Principal'): Workcenter
    {
        return (new Workcenter())->setName($name)->setCity('Sevilla')->setCompany($company);
    }

    private function makePosition(Stay $stay, Workcenter $workcenter): TrainingPosition
    {
        $position = new TrainingPosition();
        $position->setStay($stay)->setWorkcenter($workcenter);

        return $position;
    }

    /** @return array{0: ProgrammeYear, 1: Group, 2: Student} */
    private function makeGroupWithStudent(Programme $programme): array
    {
        $level   = (new ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $group   = (new Group())->setName('DAW1A')->setProgrammeYear($level);
        $student = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $group->addStudent($student);

        return [$level, $group, $student];
    }
}
