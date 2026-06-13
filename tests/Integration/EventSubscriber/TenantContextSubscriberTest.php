<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class TenantContextSubscriberTest extends ControllerTestCase
{
    public function testSingleAccessibleCentreIsAutoSelected(): void
    {
        $centre = $this->makeCentre('45000001');
        $admin  = $this->makeAdmin('subscriber.single');
        $this->loginAs($admin);

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            $centre->getId()->toRfc4122(),
            $this->client->getRequest()->getSession()->get('tenant.centre_id'),
        );
    }

    public function testMultipleAccessibleCentresRedirectToSelection(): void
    {
        $this->makeCentre('45000002');
        $this->makeCentre('45000003');
        $admin = $this->makeAdmin('subscriber.multi');
        $this->loginAs($admin);

        $this->client->request('GET', '/');

        self::assertResponseRedirects('/centro');
    }

    public function testExcludedRouteSkipsCentreEnforcement(): void
    {
        // Two centres → would normally force selection, but /perfil is excluded.
        $this->makeCentre('45000004');
        $this->makeCentre('45000005');
        $admin = $this->makeAdmin('subscriber.excluded');
        $this->loginAs($admin);

        $this->client->request('GET', '/perfil');

        self::assertResponseIsSuccessful();
    }

    public function testAdminRoutesSkipCentreEnforcement(): void
    {
        $this->makeCentre('45000006');
        $this->makeCentre('45000007');
        $admin = $this->makeAdmin('subscriber.adminroute');
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros');

        // The admin section must not bounce to /centro even without a selection.
        self::assertResponseIsSuccessful();
    }

    public function testStaleSessionCentreIsClearedAndReselected(): void
    {
        $centre = $this->makeCentre('45000008');
        $admin  = $this->makeAdmin('subscriber.stale');
        $this->loginAs($admin);

        // Poison the session with a UUID that no longer resolves to a centre.
        $session = $this->client->getRequest()->getSession();
        $session->set('tenant.centre_id', '00000000-0000-0000-0000-000000000000');
        $session->save();

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(
            $centre->getId()->toRfc4122(),
            $this->client->getRequest()->getSession()->get('tenant.centre_id'),
        );
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
        $admin = (new Teacher(new PersonName('Sub', 'Scriber')))->setUsername($username)->setAdmin(true);
        $this->persist($admin);

        return $admin;
    }
}
