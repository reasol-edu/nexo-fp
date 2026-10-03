<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El limitador de intentos de login (por usuario+IP y por IP) guarda su estado en la caché del
        // sistema de ficheros, que persiste entre ejecuciones: sin vaciarlo, repetir la suite agota el
        // límite por IP y los tests de login fallan de forma intermitente.
        self::getContainer()->get('cache.rate_limiter')->clear();
    }

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

    // ── login correcto (control para el test de throttling) ───────────────────

    public function testCorrectCredentialsLogInSuccessfully(): void
    {
        $hasher  = self::getContainer()->get(UserPasswordHasherInterface::class);
        $teacher = (new Teacher(new PersonName('Login', 'Bueno')))->setUsername('login.ok');
        $teacher->setPassword($hasher->hashPassword($teacher, 'correct-horse'));
        $this->persist($teacher);

        $this->submitLogin('login.ok', 'correct-horse');

        // Authenticated: the protected profile page renders instead of bouncing.
        $this->client->request('GET', '/perfil');
        self::assertResponseIsSuccessful();
    }

    // ── cuenta desactivada ────────────────────────────────────────────────────

    public function testDeactivatedAccountIsOnlyRevealedWithTheCorrectPassword(): void
    {
        $hasher  = self::getContainer()->get(UserPasswordHasherInterface::class);
        $teacher = (new Teacher(new PersonName('Baja', 'Docente')))->setUsername('login.inactive')->setActive(false);
        $teacher->setPassword($hasher->hashPassword($teacher, 'correct-horse'));
        $this->persist($teacher);

        // Con una contraseña errónea no se debe distinguir de un usuario que no existe.
        $this->submitLogin('login.inactive', 'wrong-password');
        $this->client->followRedirect();
        self::assertStringNotContainsString('desactivada', (string) $this->client->getResponse()->getContent());

        // Con la contraseña correcta sí se explica por qué no puede entrar.
        $this->submitLogin('login.inactive', 'correct-horse');
        $this->client->followRedirect();
        self::assertStringContainsString('desactivada', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/perfil');
        self::assertResponseRedirects();
    }

    // ── throttling de login (fuerza bruta) ────────────────────────────────────

    public function testLoginThrottlingBlocksAfterTooManyAttempts(): void
    {
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $teacher = (new Teacher(new PersonName('Bruta', 'Fuerza')))->setUsername('throttle.user');
        $teacher->setPassword($hasher->hashPassword($teacher, 'correct-horse'));
        $this->persist($teacher);

        // Exhaust the throttling budget (max_attempts: 5) with wrong passwords.
        for ($i = 0; $i < 5; ++$i) {
            $this->submitLogin('throttle.user', 'wrong-password');
        }

        // The 6th attempt uses the CORRECT password but must still be rejected.
        $this->submitLogin('throttle.user', 'correct-horse');

        // Throttled → no authenticated session: a protected page bounces to login.
        $this->client->request('GET', '/perfil');
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    private function submitLogin(string $username, string $password): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form    = $crawler->filter('form')->form();
        $form['_username'] = $username;
        $form['_password'] = $password;
        $this->client->submit($form);
    }
}
