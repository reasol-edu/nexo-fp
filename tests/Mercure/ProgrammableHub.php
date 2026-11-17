<?php

declare(strict_types=1);

namespace App\Tests\Mercure;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * Hub de pruebas que puede fallar al publicar: sirve para comprobar qué hace la aplicación con un hub caído o que rechaza
 * la clave.
 */
final class ProgrammableHub implements HubInterface
{
    /** @var list<Update> */
    public array $published = [];

    public ?\Throwable $error = null;

    public function getPublicUrl(): string
    {
        return '/.well-known/mercure';
    }

    public function getProvider(): TokenProviderInterface
    {
        throw new \LogicException('No se usa en estos tests.');
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        if ($this->error !== null) {
            throw $this->error;
        }
        $this->published[] = $update;

        return 'id';
    }
}
