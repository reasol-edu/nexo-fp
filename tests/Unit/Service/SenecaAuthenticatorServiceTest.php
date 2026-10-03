<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SenecaAuthenticatorService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class SenecaAuthenticatorServiceTest extends TestCase
{
    private SenecaAuthenticatorService $service;

    protected function setUp(): void
    {
        $this->service = new SenecaAuthenticatorService('https://example.invalid/', true, true, new NullLogger());
    }

    public function testAcceptsACorrectResponse(): void
    {
        self::assertTrue($this->service->parseCredentialsResponse('<resultado><correcto>SI</correcto></resultado>'));
    }

    public function testRejectsAWrongOrAmbiguousResponse(): void
    {
        self::assertFalse($this->service->parseCredentialsResponse('<resultado><correcto>NO</correcto></resultado>'));
        self::assertFalse($this->service->parseCredentialsResponse('<resultado/>'));
        self::assertFalse($this->service->parseCredentialsResponse('<r><correcto>SI</correcto><correcto>SI</correcto></r>'));
    }

    public function testRejectsAMalformedResponse(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->service->parseCredentialsResponse('esto no es XML');
    }

    public function testDoesNotResolveExternalEntities(): void
    {
        $secret = tempnam(sys_get_temp_dir(), 'nexo_xxe_');
        file_put_contents($secret, 'SI');

        try {
            // Con sustitución de entidades, «&xxe;» valdría «SI» (contenido del fichero local) y daría true.
            $xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY xxe SYSTEM "file://' . $secret . '">]><r><correcto>&xxe;</correcto></r>';

            self::assertFalse($this->service->parseCredentialsResponse($xml));
        } finally {
            @unlink($secret);
        }
    }
}
