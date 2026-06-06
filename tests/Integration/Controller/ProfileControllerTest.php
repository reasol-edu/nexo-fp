<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProfileControllerTest extends ControllerTestCase
{
    // ── GET /perfil ───────────────────────────────────────────────────────────

    public function testGetRendersFormWhenAuthenticated(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/perfil');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/perfil');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    // ── datos personales ──────────────────────────────────────────────────────

    public function testPostSavesPersonalDataAndRedirects(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $crawler = $this->client->request('GET', '/perfil');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/perfil', [
            '_token'     => $token,
            'first_name' => 'Nuevo',
            'last_name'  => 'Apellido',
            'email'      => 'nuevo@ejemplo.com',
        ]);

        self::assertResponseRedirects('/perfil');

        $this->em->clear();
        $updated = $this->em->find(Teacher::class, $teacher->getId());
        self::assertSame('Nuevo', $updated->getName()->getFirstName());
        self::assertSame('Apellido', $updated->getName()->getLastName());
        self::assertSame('nuevo@ejemplo.com', $updated->getEmail());
    }

    public function testPostWithInvalidCsrfIsDenied(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('POST', '/perfil', [
            '_token'     => 'token-invalido',
            'first_name' => 'Nuevo',
            'last_name'  => 'Apellido',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testPostWithEmptyFirstNameRendersFormAgain(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $crawler = $this->client->request('GET', '/perfil');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/perfil', [
            '_token'     => $token,
            'first_name' => '',
            'last_name'  => 'Apellido',
            'email'      => '',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testPostWithInvalidEmailRendersFormAgain(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $crawler = $this->client->request('GET', '/perfil');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/perfil', [
            '_token'     => $token,
            'first_name' => 'Nombre',
            'last_name'  => 'Apellido',
            'email'      => 'no-es-un-email',
        ]);

        self::assertResponseIsSuccessful();
    }

    // ── cambio de contraseña (usuario local) ──────────────────────────────────

    public function testPasswordFieldsAreRenderedForLocalUser(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/perfil');

        self::assertSelectorExists('[name="current_password"]');
        self::assertSelectorExists('[name="new_password"]');
        self::assertSelectorExists('[name="new_password_confirm"]');
    }

    public function testPostChangesPasswordForLocalUser(): void
    {
        $teacher = $this->makeTeacherWithPassword('teacher.1', 'old-pass');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $crawler = $this->client->request('GET', '/perfil');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/perfil', [
            '_token'               => $token,
            'first_name'           => $teacher->getName()->getFirstName(),
            'last_name'            => $teacher->getName()->getLastName(),
            'email'                => '',
            'current_password'     => 'old-pass',
            'new_password'         => 'new-pass-123',
            'new_password_confirm' => 'new-pass-123',
        ]);

        self::assertResponseRedirects('/perfil');

        $this->em->clear();
        $updated = $this->em->find(Teacher::class, $teacher->getId());
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($updated, 'new-pass-123'));
    }

    public function testPostWithWrongCurrentPasswordRendersFormAgain(): void
    {
        $teacher = $this->makeTeacherWithPassword('teacher.1', 'correct-pass');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $crawler = $this->client->request('GET', '/perfil');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/perfil', [
            '_token'               => $token,
            'first_name'           => $teacher->getName()->getFirstName(),
            'last_name'            => $teacher->getName()->getLastName(),
            'email'                => '',
            'current_password'     => 'wrong-pass',
            'new_password'         => 'nueva-pass-123',
            'new_password_confirm' => 'nueva-pass-123',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testPostWithPasswordMismatchRendersFormAgain(): void
    {
        $teacher = $this->makeTeacherWithPassword('teacher.1', 'old-pass');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $crawler = $this->client->request('GET', '/perfil');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/perfil', [
            '_token'               => $token,
            'first_name'           => $teacher->getName()->getFirstName(),
            'last_name'            => $teacher->getName()->getLastName(),
            'email'                => '',
            'current_password'     => 'old-pass',
            'new_password'         => 'nueva-pass-123',
            'new_password_confirm' => 'diferente-pass',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testPostWithEmptyNewPasswordDoesNotRequireCurrentPassword(): void
    {
        $teacher = $this->makeTeacherWithPassword('teacher.1', 'old-pass');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $crawler = $this->client->request('GET', '/perfil');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/perfil', [
            '_token'               => $token,
            'first_name'           => 'Nombre',
            'last_name'            => 'Apellido',
            'email'                => '',
            'current_password'     => '',
            'new_password'         => '',
            'new_password_confirm' => '',
        ]);

        self::assertResponseRedirects('/perfil');
    }

    // ── usuario externo (Séneca) ──────────────────────────────────────────────

    public function testPasswordFieldsAreNotRenderedForExternalUser(): void
    {
        $teacher = $this->makeExternalTeacher('external.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/perfil');

        self::assertSelectorNotExists('[name="current_password"]');
        self::assertSelectorNotExists('[name="new_password"]');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }

    private function makeTeacherWithPassword(string $username, string $plainPassword): Teacher
    {
        $teacher = $this->makeTeacher($username);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $teacher->setPassword($hasher->hashPassword($teacher, $plainPassword));

        return $teacher;
    }

    private function makeExternalTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('External', 'User')))->setUsername($username)->setExternal(true);
    }
}
