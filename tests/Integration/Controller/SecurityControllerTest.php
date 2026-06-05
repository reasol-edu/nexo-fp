<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class SecurityControllerTest extends ControllerTestCase
{
    // ── página de login ───────────────────────────────────────────────────────

    public function testLoginPageIsAccessibleAnonymously(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
    }

    public function testLoginPageRendersUsernameAndPasswordFields(): void
    {
        $this->client->request('GET', '/login');

        self::assertSelectorExists('[name="_username"]');
        self::assertSelectorExists('[name="_password"]');
    }

    // ── redirección cuando ya está autenticado ────────────────────────────────

    public function testLoginRedirectsAlreadyAuthenticatedUserToDashboard(): void
    {
        $teacher = (new Teacher(new PersonName('Ana', 'Lopez')))->setUsername('ana.lopez');
        $this->persist($teacher);
        $this->client->loginUser($teacher);

        $this->client->request('GET', '/login');

        self::assertResponseRedirects();
        self::assertStringContainsString('/', (string) $this->client->getResponse()->headers->get('Location'));
    }
}
