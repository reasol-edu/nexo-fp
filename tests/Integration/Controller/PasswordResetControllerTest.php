<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class PasswordResetControllerTest extends ControllerTestCase
{
    // ── Solicitud de reset ────────────────────────────────────────────────────

    public function testRequestPageIsAccessibleWithoutLogin(): void
    {
        $this->client->request('GET', '/contrasena/recuperar');

        self::assertResponseIsSuccessful();
    }

    public function testRequestRedirectsAuthenticatedUser(): void
    {
        $teacher = $this->makeTeacher('redirect.test');
        $this->loginAs($teacher);

        $this->client->request('GET', '/contrasena/recuperar');

        self::assertResponseRedirects();
    }

    public function testRequestShowsSentStateForUnknownUsername(): void
    {
        $csrfToken = $this->getCsrfTokenFromPage('/contrasena/recuperar');

        $this->client->request('POST', '/contrasena/recuperar', [
            'username'    => 'nonexistent.user',
            '_csrf_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Revisa tu correo', $this->client->getResponse()->getContent());
    }

    public function testRequestGeneratesTokenForLocalTeacherWithEmail(): void
    {
        $teacher   = $this->makeTeacher('has.email', email: 'hasmail@example.com');
        $teacherId = $teacher->getId();
        $csrfToken = $this->getCsrfTokenFromPage('/contrasena/recuperar');

        $this->client->request('POST', '/contrasena/recuperar', [
            'username'    => 'has.email',
            '_csrf_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $fresh = $this->em->find(Teacher::class, $teacherId);
        self::assertNotNull($fresh?->getPasswordResetToken());
        self::assertNotNull($fresh?->getPasswordResetTokenExpiresAt());
    }

    public function testRequestDoesNotGenerateTokenForExternalTeacher(): void
    {
        $teacher   = $this->makeTeacher('external.user', email: 'ext@example.com', external: true);
        $teacherId = $teacher->getId();
        $csrfToken = $this->getCsrfTokenFromPage('/contrasena/recuperar');

        $this->client->request('POST', '/contrasena/recuperar', [
            'username'    => 'external.user',
            '_csrf_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $fresh = $this->em->find(Teacher::class, $teacherId);
        self::assertNull($fresh?->getPasswordResetToken());
    }

    public function testRequestDoesNotGenerateTokenForTeacherWithoutEmail(): void
    {
        $teacher   = $this->makeTeacher('no.email');
        $teacherId = $teacher->getId();
        $csrfToken = $this->getCsrfTokenFromPage('/contrasena/recuperar');

        $this->client->request('POST', '/contrasena/recuperar', [
            'username'    => 'no.email',
            '_csrf_token' => $csrfToken,
        ]);

        self::assertResponseRedirects();
        $this->em->clear();
        $fresh = $this->em->find(Teacher::class, $teacherId);
        self::assertNull($fresh?->getPasswordResetToken());
    }

    // ── Formulario de nueva contraseña ────────────────────────────────────────

    public function testResetPageShowsFormForValidToken(): void
    {
        $this->makeTeacherWithToken('reset.valid', 'validtoken123abc');

        $this->client->request('GET', '/contrasena/restablecer/validtoken123abc');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Nueva contraseña', $this->client->getResponse()->getContent());
    }

    public function testResetShowsErrorForInvalidToken(): void
    {
        $this->client->request('GET', '/contrasena/restablecer/tokenquenoexi');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('válido o ha caducado', $this->client->getResponse()->getContent());
    }

    public function testResetShowsErrorForExpiredToken(): void
    {
        $teacher = $this->makeTeacherWithToken('reset.expired', 'expiredtoken999', expiredAt: '-2 hours');

        $this->client->request('GET', '/contrasena/restablecer/expiredtoken999');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('válido o ha caducado', $this->client->getResponse()->getContent());

        $this->em->refresh($teacher);
        self::assertNull($teacher->getPasswordResetToken());
    }

    public function testResetUpdatesPasswordAndClearsToken(): void
    {
        $teacher   = $this->makeTeacherWithToken('reset.success', 'goodtoken456def');
        $teacherId = $teacher->getId();
        $csrfToken = $this->getCsrfTokenFromPage('/contrasena/restablecer/goodtoken456def');

        $this->client->request('POST', '/contrasena/restablecer/goodtoken456def', [
            'password'         => 'NuevaContrasena123!',
            'password_confirm' => 'NuevaContrasena123!',
            '_csrf_token'      => $csrfToken,
        ]);

        self::assertResponseRedirects('/login?password_reset=success');
        $this->em->clear();
        $fresh = $this->em->find(Teacher::class, $teacherId);
        self::assertNull($fresh?->getPasswordResetToken());
        self::assertNotNull($fresh?->getPassword());
    }

    public function testResetShowsErrorOnPasswordMismatch(): void
    {
        $this->makeTeacherWithToken('reset.mismatch', 'mismatchtoken789');
        $csrfToken = $this->getCsrfTokenFromPage('/contrasena/restablecer/mismatchtoken789');

        $this->client->request('POST', '/contrasena/restablecer/mismatchtoken789', [
            'password'         => 'Contrasena1!',
            'password_confirm' => 'Contrasena2!',
            '_csrf_token'      => $csrfToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no coinciden', $this->client->getResponse()->getContent());
    }

    public function testResetShowsErrorOnEmptyPassword(): void
    {
        $this->makeTeacherWithToken('reset.empty', 'emptypasstoken000');
        $csrfToken = $this->getCsrfTokenFromPage('/contrasena/restablecer/emptypasstoken000');

        $this->client->request('POST', '/contrasena/restablecer/emptypasstoken000', [
            'password'         => '',
            'password_confirm' => '',
            '_csrf_token'      => $csrfToken,
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('nueva contraseña', $this->client->getResponse()->getContent());
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeTeacher(
        string $username,
        ?string $email = null,
        bool $external = false,
    ): Teacher {
        $teacher = (new Teacher(new PersonName('Test', 'User')))->setUsername($username);
        if ($email !== null) {
            $teacher->setEmail($email);
        }
        $teacher->setExternal($external);
        $this->persist($teacher);

        return $teacher;
    }

    private function makeTeacherWithToken(
        string $username,
        string $token,
        string $expiredAt = '+1 hour',
    ): Teacher {
        $teacher = (new Teacher(new PersonName('Token', 'User')))->setUsername($username)
            ->setEmail($username . '@example.com')
            ->setPasswordResetToken($token)
            ->setPasswordResetTokenExpiresAt(new \DateTimeImmutable($expiredAt));
        $this->persist($teacher);

        return $teacher;
    }

    private function getCsrfTokenFromPage(string $url): string
    {
        $crawler = $this->client->request('GET', $url);
        $input   = $crawler->filter('input[name="_csrf_token"]');

        return $input->count() > 0 ? ($input->attr('value') ?? '') : '';
    }
}
