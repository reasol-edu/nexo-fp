<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class SettingsControllerTest extends ControllerTestCase
{
    public function testSettingsRedirectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/ajustes');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testSettingsDeniesNonAdminTeacher(): void
    {
        $teacher = $this->makeTeacher('teacher.settings');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/admin/ajustes');

        self::assertResponseStatusCodeSame(403);
    }

    public function testSettingsIsAccessibleToAdmin(): void
    {
        $admin = $this->makeAdmin('admin.settings');
        $this->persist($admin);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/ajustes');

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
}
