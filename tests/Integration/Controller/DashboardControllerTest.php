<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Tests\Integration\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    // ── acceso ────────────────────────────────────────────────────────────────

    public function testDashboardRedirectsAnonymousUserToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testDashboardIsAccessibleToAuthenticatedTeacher(): void
    {
        // TenantContextSubscriber redirects to /centro unless a centre is selected.
        $centre  = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $teacher = (new Teacher(new PersonName('Ana', 'Lopez')))->setUsername('ana.lopez');
        $this->persist($centre, $teacher);
        $centre->addAdmin($teacher);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    // ── pendientes ────────────────────────────────────────────────────────────

    public function testDashboardShowsPendingAlertsForCentreAdmin(): void
    {
        [$centre, $admin, $stay] = $this->makeCentreWithStay('41000002', 'ana.admin.2');
        $free = (new TrainingPosition())->setStay($stay);
        $this->persist($free);
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Pendientes', $crawler->html());
        self::assertStringContainsString('1 plaza sin estudiante', $crawler->html());
        self::assertStringContainsString($stay->getName(), $crawler->html());
    }

    public function testDashboardShowsAllClearWhenNoAlerts(): void
    {
        [$centre, $admin] = $this->makeCentreWithStay('41000003', 'ana.admin.3');
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Todo al día', $crawler->html());
    }

    // ── accesos rápidos ───────────────────────────────────────────────────────

    public function testDashboardShowsQuickActionsForCentreAdmin(): void
    {
        [$centre, $admin] = $this->makeCentreWithStay('41000004', 'ana.admin.4');
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Nueva estancia', $crawler->html());
        self::assertStringContainsString('Importar estudiantes', $crawler->html());
        self::assertStringContainsString('Nueva empresa', $crawler->html());
    }

    public function testDashboardHidesQuickActionsForTeacherWithoutPermissions(): void
    {
        [$centre, , ] = $this->makeCentreWithStay('41000005', 'ana.admin.5');
        $teacher = (new Teacher(new PersonName('Sin', 'Permisos')))->setUsername('teacher.noperm.5');
        $this->persist($teacher);
        $this->loginAs($teacher, $centre);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Nueva estancia', $crawler->html());
        self::assertStringNotContainsString('Importar estudiantes', $crawler->html());
        self::assertStringNotContainsString('Nueva empresa', $crawler->html());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Centre with active year, a stay in progress, and a centre admin.
     *
     * @return array{EducationalCentre, Teacher, Stay}
     */
    private function makeCentreWithStay(string $code, string $username): array
    {
        $centre = (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $fam    = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stay   = (new Stay())
            ->setName('FFEOE DAM ' . $code)
            ->setAcademicYear($year)
            ->setProgramme($prog)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable('+30 days'));
        $admin  = (new Teacher(new PersonName('Ana', 'Admin')))->setUsername($username);
        $this->persist($centre, $year, $fam, $prog, $stay, $admin);
        $centre->setActiveAcademicYear($year);
        $centre->addAdmin($admin);
        $this->flush();

        return [$centre, $admin, $stay];
    }
}
