<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MercureStatus;
use App\Tests\Mercure\ProgrammableHub;
use PHPUnit\Framework\TestCase;

/** La comprobación de Mercure publica un aviso de prueba y cuenta lo que pase, sin lanzar nunca excepciones. */
final class MercureStatusTest extends TestCase
{
    public function testAnAcceptedUpdateMeansTheHubIsUp(): void
    {
        $hub    = new ProgrammableHub();
        $result = (new MercureStatus($hub, 'http://hub.test/.well-known/mercure'))->check();

        self::assertSame(MercureStatus::OK, $result['state']);
        self::assertSame('http://hub.test/.well-known/mercure', $result['url']);
        self::assertNull($result['error']);

        // Un aviso privado y sin datos de negocio, en un canal que ninguna pantalla escucha.
        self::assertCount(1, $hub->published);
        self::assertSame([MercureStatus::TOPIC], $hub->published[0]->getTopics());
        self::assertTrue($hub->published[0]->isPrivate());
        self::assertSame(['ts'], array_keys((array) json_decode($hub->published[0]->getData(), true)));
    }

    public function testAHubThatRefusesTheUpdateIsReportedWithItsReason(): void
    {
        $hub        = new ProgrammableHub();
        $hub->error = new \RuntimeException('Failed to send an update. Status code "401"');

        $result = (new MercureStatus($hub, 'http://hub.test/.well-known/mercure'))->check();

        self::assertSame(MercureStatus::FAILED, $result['state']);
        self::assertSame('Failed to send an update. Status code "401"', $result['error']);
        self::assertSame('http://hub.test/.well-known/mercure', $result['url']);
    }

    public function testAVeryLongReasonIsTruncated(): void
    {
        $hub        = new ProgrammableHub();
        $hub->error = new \RuntimeException(str_repeat('x', 1000));

        $result = (new MercureStatus($hub, 'http://hub.test/.well-known/mercure'))->check();

        self::assertSame(300, mb_strlen((string) $result['error']));
    }

    public function testWithoutAUrlThereIsNoHubToCheck(): void
    {
        foreach ([null, '', '  '] as $url) {
            $hub    = new ProgrammableHub();
            $result = (new MercureStatus($hub, $url))->check();

            self::assertSame(MercureStatus::NOT_CONFIG, $result['state']);
            self::assertSame([], $hub->published, 'sin hub no se publica nada');
        }
    }
}
