<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
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
}
