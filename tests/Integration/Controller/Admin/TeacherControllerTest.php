<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class TeacherControllerTest extends ControllerTestCase
{
    // ── index ─────────────────────────────────────────────────────────────────

    public function testIndexIsAccessibleToAdmin(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/docentes');

        self::assertResponseIsSuccessful();
    }

    public function testIndexDeniesNonAdmin(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/admin/docentes');

        self::assertResponseStatusCodeSame(403);
    }

    // ── new ───────────────────────────────────────────────────────────────────

    public function testNewGetRendersForm(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/docentes/nuevo');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testNewPostCreatesTeacherAndRedirectsToIndex(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin/docentes/nuevo');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/docentes/nuevo', [
            '_token'      => $token,
            'first_name'  => 'Juan',
            'last_name'   => 'Garcia',
            'username'    => 'juan.garcia',
            'email'       => '',
            'password'    => 'secret123',
            'auth_method' => 'local',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/docentes', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testNewPostWithInvalidCsrfIsDenied(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $this->client->request('POST', '/admin/docentes/nuevo', [
            '_token'     => 'token-invalido',
            'first_name' => 'Juan',
            'last_name'  => 'Garcia',
            'username'   => 'juan.garcia',
            'password'   => 'secret123',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNewPostWithEmptyFirstNameRendersFormAgain(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin/docentes/nuevo');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/docentes/nuevo', [
            '_token'      => $token,
            'first_name'  => '',
            'last_name'   => 'Garcia',
            'username'    => 'juan.garcia',
            'password'    => 'secret123',
            'auth_method' => 'local',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testNewPostWithDuplicateUsernameRendersFormAgain(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $teacher = $this->makeTeacher('juan.garcia');
        $this->persist($admin, $teacher);
        $this->loginAs($admin);

        $crawler = $this->client->request('GET', '/admin/docentes/nuevo');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/docentes/nuevo', [
            '_token'      => $token,
            'first_name'  => 'Juan',
            'last_name'   => 'Garcia',
            'username'    => 'juan.garcia',
            'password'    => 'secret123',
            'auth_method' => 'local',
        ]);

        self::assertResponseIsSuccessful();
    }

    // ── edit ──────────────────────────────────────────────────────────────────

    public function testEditGetRendersForm(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $teacher);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/docentes/' . $teacher->getId()->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditPostSavesChangesAndRedirects(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $teacher);
        $this->loginAs($admin);

        $teacherId = $teacher->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/admin/docentes/' . $teacherId);
        $token     = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/docentes/' . $teacherId, [
            '_token'      => $token,
            'first_name'  => 'Modificado',
            'last_name'   => 'Apellido',
            'username'    => 'teacher.1',
            'email'       => '',
            'password'    => '',
            'auth_method' => 'local',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        $updated = $this->em->find(Teacher::class, $teacher->getId());
        self::assertSame('Modificado', $updated->getName()->getFirstName());
    }

    public function testEditPostWithInvalidCsrfIsDenied(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $teacher);
        $this->loginAs($admin);

        $teacherId = $teacher->getId()->toRfc4122();
        $this->client->request('POST', '/admin/docentes/' . $teacherId, [
            '_token'     => 'token-invalido',
            'first_name' => 'Hack',
            'last_name'  => 'Attack',
            'username'   => 'hacked',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── self-protection ───────────────────────────────────────────────────────

    public function testAdminCannotDemoteSelf(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $adminId = $admin->getId()->toRfc4122();
        $crawler = $this->client->request('GET', '/admin/docentes/' . $adminId);
        $token   = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        // Submit without the 'admin' checkbox → tries to demote self
        $this->client->request('POST', '/admin/docentes/' . $adminId, [
            '_token'      => $token,
            'first_name'  => 'Admin',
            'last_name'   => 'User',
            'username'    => 'admin.1',
            'email'       => '',
            'password'    => '',
            'auth_method' => 'local',
            'active'      => '1', // keep active, no 'admin' → demote
        ]);

        // Redirects back to edit page without saving the demotion
        self::assertResponseRedirects();

        $this->em->clear();
        $unchanged = $this->em->find(Teacher::class, $admin->getId());
        self::assertTrue($unchanged->isAdmin());
    }

    public function testAdminCannotDeactivateSelf(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $adminId = $admin->getId()->toRfc4122();
        $crawler = $this->client->request('GET', '/admin/docentes/' . $adminId);
        $token   = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        // Submit without 'active' checkbox → tries to deactivate self
        $this->client->request('POST', '/admin/docentes/' . $adminId, [
            '_token'      => $token,
            'first_name'  => 'Admin',
            'last_name'   => 'User',
            'username'    => 'admin.1',
            'email'       => '',
            'password'    => '',
            'auth_method' => 'local',
            'admin'       => '1', // keep admin, no 'active' → deactivate
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        $unchanged = $this->em->find(Teacher::class, $admin->getId());
        self::assertTrue($unchanged->isActive());
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteTeacherDeletesEntityAndRedirectsToIndex(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $teacher);
        $this->loginAs($admin);

        $teacherId = $teacher->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/admin/docentes/' . $teacherId);
        $token     = $crawler->filter('form[action$="/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/docentes/' . $teacherId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/docentes', (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(Teacher::class, $teacher->getId()));
    }

    public function testDeleteWithInvalidCsrfIsDenied(): void
    {
        $admin   = $this->makeAdmin('admin.1');
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $teacher);
        $this->loginAs($admin);

        $teacherId = $teacher->getId()->toRfc4122();
        $this->client->request('POST', '/admin/docentes/' . $teacherId . '/eliminar', ['_token' => 'token-invalido']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCannotDeleteSelf(): void
    {
        $admin = $this->makeAdmin('admin.1');
        $this->persist($admin);
        $this->loginAs($admin);

        $adminId = $admin->getId()->toRfc4122();
        $this->client->request('GET', '/admin/docentes/' . $adminId);

        // The template hides the delete form when the admin views their own profile
        self::assertSelectorNotExists('form[action*="/eliminar"]');
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
