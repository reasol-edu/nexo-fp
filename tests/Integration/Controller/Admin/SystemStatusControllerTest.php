<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Message\HeartbeatMessage;
use App\MessageHandler\HeartbeatHandler;
use App\Service\MercureStatus;
use App\Service\SystemState;
use App\Service\SystemStatus;
use App\Tests\Integration\ControllerTestCase;
use App\Tests\Mercure\CollectingHub;
use App\Tests\Mercure\ProgrammableHub;
use App\Tests\Support\CleansSystemFiles;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/** Administración → Estado del sistema: señal de vida del worker, mensajes fallidos, correo de prueba y espacio. */
final class SystemStatusControllerTest extends ControllerTestCase
{
    use CleansSystemFiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanSystemFiles();

        /** @var AdapterInterface $limiters */
        $limiters = self::getContainer()->get('cache.rate_limiter');
        $limiters->clear();
    }

    protected function tearDown(): void
    {
        $this->cleanSystemFiles();

        parent::tearDown();
    }

    private function admin(?string $email = 'admin@example.com'): Teacher
    {
        $admin = (new Teacher(new PersonName('Admin', 'Sys')))->setUsername('admin.status')->setAdmin(true)->setEmail($email);
        $this->persist($admin);
        $this->loginAs($admin);

        return $admin;
    }

    private function transport(string $name): TransportInterface
    {
        /** @var TransportInterface $transport */
        $transport = self::getContainer()->get('messenger.transport.' . $name);

        return $transport;
    }

    private function state(): SystemState
    {
        /** @var SystemState $state */
        $state = self::getContainer()->get(SystemState::class);

        return $state;
    }

    private function aFailedMessage(): void
    {
        $this->transport('failed')->send(new Envelope(new HeartbeatMessage(), [new ErrorDetailsStamp(\RuntimeException::class, 0, 'SMTP caído')]));
    }

    /** El token CSRF del formulario de la página cuya acción termina así: el que usaría el botón. */
    private function formToken(string $actionSuffix): string
    {
        $crawler = $this->client->request('GET', '/admin/estado');
        $field   = $crawler->filter(\sprintf('form[action$="%s"] input[name="_token"]', $actionSuffix));
        self::assertCount(1, $field, $actionSuffix);

        return (string) $field->attr('value');
    }

    /** El token del formulario del primer mensaje fallido (sus dos botones lo comparten). */
    private function failedToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/estado');

        return (string) $crawler->filter('form input[name="_token"]')->first()->attr('value');
    }

    private function failedId(): string
    {
        $crawler = $this->client->request('GET', '/admin/estado');
        $action  = (string) $crawler->filter('button[formaction*="/reintentar"]')->first()->attr('formaction');
        preg_match('#/fallidos/(\d+)/#', $action, $m);

        return $m[1];
    }

    public function testOnlyGlobalAdminsSeeIt(): void
    {
        $teacher = (new Teacher(new PersonName('Ana', 'Docente')))->setUsername('docente.status');
        $this->persist($teacher);
        $this->loginAs($teacher);

        foreach (['GET /admin/estado', 'POST /admin/estado/correo-prueba', 'POST /admin/estado/mercure', 'POST /admin/estado/fallidos/1/reintentar', 'POST /admin/estado/fallidos/1/descartar'] as $route) {
            [$method, $path] = explode(' ', $route);
            $this->client->request($method, $path, ['_token' => 'x']);
            self::assertResponseStatusCodeSame(403, $route);
        }
    }

    public function testTheWorkerIsNeverStaleOrOk(): void
    {
        $this->admin();

        $this->client->request('GET', '/admin/estado');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Todavía no hay señal');

        $this->state()->write(SystemStatus::HEARTBEAT, ['at' => time() - SystemStatus::HEARTBEAT_STALE_AFTER - 60]);
        $this->client->request('GET', '/admin/estado');
        self::assertSelectorTextContains('body', 'no responde desde el');

        $this->state()->write(SystemStatus::HEARTBEAT, ['at' => time()]);
        $this->client->request('GET', '/admin/estado');
        self::assertSelectorTextContains('body', 'funciona: última señal');
    }

    public function testTheHeartbeatHandlerWritesTheTimestamp(): void
    {
        /** @var HeartbeatHandler $handler */
        $handler = self::getContainer()->get(HeartbeatHandler::class);
        $handler(new HeartbeatMessage());

        $beat = $this->state()->read(SystemStatus::HEARTBEAT);
        self::assertNotNull($beat);
        self::assertEqualsWithDelta(time(), $beat['at'], 5);
    }

    public function testTheDashboardCardShowsTheWorkerAndFailedMessages(): void
    {
        $this->admin();
        $this->aFailedMessage();

        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/estado"]');
        self::assertSelectorTextContains('body', 'Hay un mensaje fallido');
    }

    public function testAFailedMessageIsListedAndCanBeRetried(): void
    {
        $this->admin();
        $this->aFailedMessage();

        $this->client->request('GET', '/admin/estado');
        self::assertSelectorTextContains('body', 'HeartbeatMessage');
        self::assertSelectorTextContains('body', 'SMTP caído');

        $id    = $this->failedId();
        $token = $this->failedToken();
        $this->client->request('POST', '/admin/estado/fallidos/' . $id . '/reintentar', ['_token' => $token]);
        self::assertResponseRedirects('/admin/estado');

        // Salió de `failed` y espera al worker en `async`: no se ejecuta dentro de esta petición.
        $this->client->request('GET', '/admin/estado');
        self::assertSelectorTextContains('body', 'No hay mensajes fallidos');
        $queued = iterator_to_array($this->transport('async')->get());
        self::assertCount(1, $queued);
        self::assertInstanceOf(HeartbeatMessage::class, $queued[0]->getMessage());
    }

    public function testAFailedMessageCanBeDiscarded(): void
    {
        $this->admin();
        $this->aFailedMessage();

        $id    = $this->failedId();
        $token = $this->failedToken();
        $this->client->request('POST', '/admin/estado/fallidos/' . $id . '/descartar', ['_token' => $token]);
        self::assertResponseRedirects('/admin/estado');

        $this->client->request('GET', '/admin/estado');
        self::assertSelectorTextContains('body', 'No hay mensajes fallidos');
        self::assertSame([], iterator_to_array($this->transport('async')->get()));
    }

    public function testRetryNeedsAValidToken(): void
    {
        $this->admin();
        $this->aFailedMessage();
        $id = $this->failedId();

        $this->client->request('POST', '/admin/estado/fallidos/' . $id . '/reintentar', ['_token' => 'nope']);
        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, iterator_to_array($this->transport('failed')->get()));
    }

    public function testTheTestEmailGoesOnlyToTheAdminsOwnAddress(): void
    {
        $this->admin('yo@example.com');
        $token = $this->formToken('/correo-prueba');

        // Una dirección en la petición se ignora.
        $this->client->request('POST', '/admin/estado/correo-prueba', ['_token' => $token, 'to' => 'otro@example.com', 'email' => 'otro@example.com']);
        self::assertResponseRedirects('/admin/estado');

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'yo@example.com');
        self::assertEmailAddressNotContains($email, 'to', 'otro@example.com');
    }

    public function testTheTestEmailIsRateLimited(): void
    {
        $this->admin();
        $token = $this->formToken('/correo-prueba');

        // El colector de correo es por petición: se cuentan los enviados en cada respuesta.
        $sent = 0;
        foreach (range(1, 5) as $i) {
            $this->client->request('POST', '/admin/estado/correo-prueba', ['_token' => $token]);
            $sent += \count(self::getMailerMessages());
        }
        self::assertSame(3, $sent);

        $this->client->request('GET', '/admin/estado');
        self::assertSelectorTextContains('body', 'demasiados correos de prueba');
    }

    public function testWithoutAnAddressThereIsNothingToSendTo(): void
    {
        $this->admin(null);
        $token = $this->formToken('/correo-prueba');
        self::assertSelectorNotExists('form[action$="/correo-prueba"] button');

        $this->client->request('POST', '/admin/estado/correo-prueba', ['_token' => $token]);
        self::assertEmailCount(0);
    }

    public function testTheTestEmailAndRetriesAreInTheActivityLog(): void
    {
        putenv('APP_LOG=true');
        $_ENV['APP_LOG']    = 'true';
        $_SERVER['APP_LOG'] = 'true';

        try {
            $this->admin();
            $this->aFailedMessage();
            $id       = $this->failedId();
            $retryTok = $this->failedToken();
            $emailTok = $this->formToken('/correo-prueba');

            $this->client->request('POST', '/admin/estado/correo-prueba', ['_token' => $emailTok]);
            $this->client->request('POST', '/admin/estado/fallidos/' . $id . '/reintentar', ['_token' => $retryTok]);

            $this->em->clear();
            $actions = array_map(static fn (ActivityLog $log): string => $log->getActionType(), $this->em->getRepository(ActivityLog::class)->findAll());
            self::assertContains('system.test_email', $actions);
            self::assertContains('system.failed_retried', $actions);
        } finally {
            putenv('APP_LOG=false');
            $_ENV['APP_LOG']    = 'false';
            $_SERVER['APP_LOG'] = 'false';
        }
    }

    public function testTheMercureCheckPublishesATestUpdateAndReportsSuccess(): void
    {
        $this->admin();
        $token = $this->formToken('/admin/estado/mercure');

        /** @var CollectingHub $hub */
        $hub = self::getContainer()->get(CollectingHub::class);
        $hub->updates = [];

        $this->client->request('POST', '/admin/estado/mercure', ['_token' => $token]);
        self::assertResponseRedirects('/admin/estado');

        self::assertCount(1, $hub->updates);
        self::assertSame([MercureStatus::TOPIC], $hub->updates[0]->getTopics());

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Mercure responde');
    }

    public function testTheMercureCheckReportsAHubThatFails(): void
    {
        $this->admin();

        // Antes de la primera visita a la pantalla: el servicio se sustituye mientras aún no se ha creado.
        $hub        = new ProgrammableHub();
        $hub->error = new \RuntimeException('Failed to send an update. Status code "401"');
        self::getContainer()->set(MercureStatus::class, new MercureStatus($hub, 'http://hub.test/.well-known/mercure'));
        $token = $this->formToken('/admin/estado/mercure');

        $this->client->request('POST', '/admin/estado/mercure', ['_token' => $token]);
        self::assertResponseRedirects('/admin/estado');

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Mercure no responde');
        self::assertSelectorTextContains('body', 'Status code "401"');
    }

    public function testTheMercureCheckReportsAMissingHubConfiguration(): void
    {
        $this->admin();

        self::getContainer()->set(MercureStatus::class, new MercureStatus(new ProgrammableHub(), ''));
        $token = $this->formToken('/admin/estado/mercure');

        $this->client->request('POST', '/admin/estado/mercure', ['_token' => $token]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'Mercure no está configurado');
    }

    public function testTheMercureCheckNeedsAValidToken(): void
    {
        $this->admin();

        /** @var CollectingHub $hub */
        $hub = self::getContainer()->get(CollectingHub::class);
        $hub->updates = [];

        $this->client->request('POST', '/admin/estado/mercure', ['_token' => 'nope']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $hub->updates, 'sin token válido no se publica nada');
    }

    public function testStorageFiguresArePresent(): void
    {
        /** @var SystemStatus $status */
        $status  = self::getContainer()->get(SystemStatus::class);
        $storage = $status->storage();

        self::assertArrayHasKey('database', $storage);
        self::assertGreaterThanOrEqual(0, $storage['backups']);
        self::assertGreaterThanOrEqual(0, $storage['backup_count']);

        $this->admin();
        $this->client->request('GET', '/admin/estado');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Espacio libre');
    }
}
