<?php

declare(strict_types=1);

namespace App\Tests\Mercure;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * Hub de pruebas: registra los updates en memoria en lugar de enviarlos por HTTP.
 * Se enlaza en el entorno de test (config/services_test.yaml) como hub de
 * StayRealtimeNotifier para que las publicaciones no requieran un hub real y
 * puedan comprobarse en los tests.
 */
final class CollectingHub implements HubInterface
{
    /** @var Update[] */
    public array $updates = [];

    public function __construct(
        private readonly string $url,
        private readonly TokenProviderInterface $jwtProvider,
        private readonly ?TokenFactoryInterface $jwtFactory = null,
        private readonly ?string $publicUrl = null,
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getPublicUrl(): string
    {
        return $this->publicUrl ?? $this->url;
    }

    public function getProvider(): TokenProviderInterface
    {
        return $this->jwtProvider;
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->jwtFactory;
    }

    public function publish(Update $update): string
    {
        $this->updates[] = $update;

        return 'collected';
    }
}
