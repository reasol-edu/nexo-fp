<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class ProfileSettingsControllerTest extends ControllerTestCase
{
    public function testSettingsRedirectsAnonymousUser(): void
    {
        $this->client->request('GET', '/perfil/ajustes');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testSettingsIsAccessibleToAuthenticatedTeacher(): void
    {
        $teacher = (new Teacher(new PersonName('Test', 'User')))->setUsername('teacher.profile.settings');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/perfil/ajustes');

        self::assertResponseIsSuccessful();
    }
}
