<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Pequeños datos del sistema en marcha guardados **en ficheros** bajo `var/state` (no en la base de datos): la última copia
 * automática y la señal de vida del worker. Ficheros, porque la web, la consola y el worker son procesos distintos que
 * comparten el volumen `var/` pero no la memoria ni una caché, y porque la base de datos es justo lo que sustituye una
 * restauración.
 *
 * Usa marcas de tiempo de `time()` a propósito (sin reloj simulado): se comparan con el tiempo real transcurrido.
 */
class SystemState
{
    public function __construct(
        #[Autowire('%app.state_dir%')]
        private readonly string $dir,
    ) {}

    /** @param array<string, mixed> $data */
    public function write(string $name, array $data): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0o775, true) && !is_dir($this->dir)) {
            return;
        }

        $path = $this->path($name);
        $tmp  = $path . '.tmp';
        if (@file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR)) !== false) {
            @rename($tmp, $path);
        }
    }

    /** @return array<string, mixed>|null */
    public function read(string $name): ?array
    {
        $raw = @file_get_contents($this->path($name));
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!\is_array($data)) {
            return null;
        }

        $state = [];
        foreach ($data as $key => $value) {
            $state[(string) $key] = $value;
        }

        return $state === [] ? null : $state;
    }

    private function path(string $name): string
    {
        return $this->dir . '/' . preg_replace('/[^a-z0-9._-]/i', '', $name) . '.json';
    }
}
