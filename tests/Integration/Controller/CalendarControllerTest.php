<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class CalendarControllerTest extends ControllerTestCase
{
    public function testRedirectsToLoginWhenAnonymous(): void
    {
        $this->client->request('GET', '/calendario');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testRedirectsToCentreSelectionWithoutSelectedCentre(): void
    {
        $admin = $this->makeAdmin('calendar.nocentre');
        // Two centres so the tenant subscriber forces selection instead of auto-picking.
        $this->makeCentre('46000001');
        $this->makeCentre('46000002');
        $this->loginAs($admin);

        $this->client->request('GET', '/calendario');

        self::assertResponseRedirects('/centro');
    }

    public function testRendersCalendarWithSelectedCentre(): void
    {
        $centre = $this->makeCentre('46000003');
        $admin  = $this->makeAdmin('calendar.ok');
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/calendario');

        self::assertResponseIsSuccessful();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeCentre(string $code): EducationalCentre
    {
        $centre = (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $this->persist($centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        return $centre;
    }

    private function makeAdmin(string $username): Teacher
    {
        $admin = (new Teacher(new PersonName('Cal', 'Endar')))->setUsername($username)->setAdmin(true);
        $this->persist($admin);

        return $admin;
    }
}
