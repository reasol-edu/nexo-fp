<?php

declare(strict_types=1);

namespace App\Service\Backup;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Las copias de seguridad guardadas en el servidor (`APP_BACKUP_DIR`, por defecto `var/backups`): listarlas, guardar una nueva,
 * borrarlas y localizarlas. Quien pide una copia lo hace **por su nombre**, que debe coincidir exactamente con un fichero de la
 * carpeta: nunca se construye una ruta a partir de lo que llega de la web.
 *
 * Las que crea `app:backup` sin indicar destino caen en esta misma carpeta y también se listan.
 */
class BackupLibrary
{
    /** Nombres admitidos: solo letras, cifras, punto, guion y guion bajo, y la extensión .zip. */
    private const string NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*\.zip$/';

    /** Prefijo del nombre de las copias hechas a mano o con `app:backup` (el que éste pone por defecto). */
    public const string PREFIX = 'nexo-fp-backup';

    /** Prefijo del nombre de las copias programadas: las únicas que la política de conservación puede borrar. */
    public const string AUTO_PREFIX = 'nexo-fp-backup-auto';

    /** Más copias que estas no se listan (la pantalla no es un explorador de ficheros). */
    private const int MAX_LISTED = 50;

    public function __construct(
        private readonly DatabaseBackupService $backup,
        private readonly DatabaseRestoreService $inspector,
        private readonly ClockInterface $clock,
        #[Autowire('%app.backup_dir%')]
        private readonly string $dir,
    ) {}

    public function directory(): string
    {
        return $this->dir;
    }

    /** @return list<StoredBackup> las más recientes primero */
    public function list(): array
    {
        if (!is_dir($this->dir)) {
            return [];
        }

        $files = [];
        foreach (scandir($this->dir) ?: [] as $name) {
            if (preg_match(self::NAME_PATTERN, $name) === 1 && is_file($this->dir . '/' . $name)) {
                $files[$name] = (int) filemtime($this->dir . '/' . $name);
            }
        }
        arsort($files);

        $stored = [];
        foreach (\array_slice(array_keys($files), 0, self::MAX_LISTED) as $name) {
            $stored[] = $this->describe($name, $files[$name]);
        }

        return $stored;
    }

    /** La copia con ese nombre exacto, si está en la carpeta. */
    public function find(string $name): ?StoredBackup
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || !$this->isInside($name)) {
            return null;
        }

        return $this->describe($name, (int) filemtime($this->dir . '/' . $name));
    }

    /** Ruta completa de una copia ya localizada con find()/list(). */
    public function path(StoredBackup $backup): string
    {
        return $this->dir . '/' . $backup->name;
    }

    /**
     * Guarda en el servidor una copia de los datos actuales, sin cifrar (la carpeta es del servidor).
     *
     * @throws \RuntimeException
     */
    public function save(): StoredBackup
    {
        $name = \sprintf('%s-%s.zip', self::PREFIX, $this->clock->now()->format('Y-m-d-His'));
        $this->backup->create($this->dir, $name);

        return $this->find($name) ?? throw new \RuntimeException('La copia se creó pero no se encuentra en la carpeta de copias.');
    }

    /**
     * La copia nocturna programada. Se escribe con un nombre `.part` que el patrón del listado no reconoce y se renombra al
     * terminar, de modo que un fallo (o una ejecución que aún no ha acabado) nunca deja un fichero a medias que parezca una copia.
     *
     * @throws \RuntimeException
     */
    public function saveAutomatic(): StoredBackup
    {
        $name = \sprintf('%s-%s.zip', self::AUTO_PREFIX, $this->clock->now()->format('Y-m-d-His'));
        $part = $name . '.part';

        try {
            $this->backup->create($this->dir, $part);
            if (!@rename($this->dir . '/' . $part, $this->dir . '/' . $name)) {
                throw new \RuntimeException('No se pudo renombrar la copia terminada.');
            }
        } catch (\Throwable $e) {
            @unlink($this->dir . '/' . $part);

            throw $e instanceof \RuntimeException ? $e : new \RuntimeException($e->getMessage(), previous: $e);
        }

        return $this->find($name) ?? throw new \RuntimeException('La copia se creó pero no se encuentra en la carpeta de copias.');
    }

    /**
     * Poda las copias automáticas según la política de conservación: guarda la copia más reciente de cada uno de los últimos N
     * días, de las últimas N semanas y de los últimos N meses que tengan una (ver BackupRetentionPolicy) y borra el resto, más
     * los restos `.part` de una ejecución interrumpida. Si `$maxBytes` es mayor que 0, después borra las más antiguas de las
     * conservadas hasta que quepan; la más reciente se conserva siempre, aunque ella sola ocupe más (mejor una copia grande que
     * ninguna). Solo toca los ficheros `nexo-fp-backup-auto-*.zip`: las copias guardadas a mano, con `app:backup` o dejadas allí
     * por SSH no se tocan nunca.
     *
     * La fecha de una copia es la de su nombre (cuándo se hizo), no la de modificación del fichero, que cambia al copiar o
     * restaurar la carpeta.
     *
     * @return int cuántas copias se han borrado
     */
    public function pruneAutomatic(BackupRetentionPolicy $policy, int $maxBytes = 0): int
    {
        if (!is_dir($this->dir)) {
            return 0;
        }

        $prefix    = preg_quote(self::AUTO_PREFIX, '/');
        $automatic = [];
        foreach (scandir($this->dir) ?: [] as $name) {
            $path = $this->dir . '/' . $name;
            if (preg_match('/^' . $prefix . '-(\d{4})-(\d{2})-(\d{2})-(\d{2})(\d{2})(\d{2})\.zip$/', $name, $m) === 1 && is_file($path)) {
                $madeAt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', \sprintf('%s-%s-%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]));
                $automatic[$name] = $madeAt !== false ? $madeAt : $this->clock->now()->setTimestamp((int) filemtime($path));
            } elseif (preg_match('/^' . $prefix . '-[0-9-]+\.zip\.part$/', $name) === 1 && is_file($path) && time() - (int) filemtime($path) > 6 * 3600) {
                @unlink($path);
            }
        }
        // La más reciente primero.
        uksort($automatic, static fn (string $a, string $b): int => [$automatic[$b], $b] <=> [$automatic[$a], $a]);
        $names = array_keys($automatic);

        // La más reciente de cada día / semana ISO / mes, para tantos días / semanas / meses como pida la política.
        $keepNames = [];
        foreach (['daily' => 'Y-m-d', 'weekly' => 'o-\WW', 'monthly' => 'Y-m'] as $tier => $format) {
            $limit = $policy->tiers()[$tier];
            $seen  = [];
            foreach ($names as $name) {
                $bucket = $automatic[$name]->format($format);
                if (isset($seen[$bucket])) {
                    continue; // otra más reciente ya representa este día/semana/mes
                }
                if (\count($seen) >= $limit) {
                    break;
                }
                $seen[$bucket]    = true;
                $keepNames[$name] = true;
            }
        }
        if ($names !== []) {
            $keepNames[$names[0]] = true; // la más reciente no se poda nunca
        }

        $deleted = 0;
        foreach ($names as $name) {
            if (!isset($keepNames[$name]) && @unlink($this->dir . '/' . $name)) {
                ++$deleted;
            }
        }

        if ($maxBytes > 0) {
            $remaining = array_values(array_filter($names, static fn (string $name): bool => isset($keepNames[$name])));
            $total     = array_sum(array_map(fn (string $name): int => (int) @filesize($this->dir . '/' . $name), $remaining));
            while (\count($remaining) > 1 && $total > $maxBytes) {
                $oldest = array_pop($remaining);
                $total -= (int) @filesize($this->dir . '/' . $oldest);
                if (@unlink($this->dir . '/' . $oldest)) {
                    ++$deleted;
                }
            }
        }

        return $deleted;
    }

    /**
     * Cuándo se escribió la copia más reciente de la carpeta (manual o automática), o null si no hay ninguna. Es barato: solo
     * lee fechas de modificación y no abre ningún archivo (el listado abre todos para describirlos).
     */
    public function lastBackupAt(): ?int
    {
        if (!is_dir($this->dir)) {
            return null;
        }

        $latest = null;
        foreach (scandir($this->dir) ?: [] as $name) {
            if (preg_match(self::NAME_PATTERN, $name) === 1 && is_file($this->dir . '/' . $name)) {
                $latest = max($latest ?? 0, (int) filemtime($this->dir . '/' . $name));
            }
        }

        return $latest;
    }

    /**
     * Lo que ocupan las copias de la carpeta, de cualquier tipo.
     *
     * @return array{bytes: int, count: int}
     */
    public function usage(): array
    {
        $bytes = 0;
        $count = 0;
        if (is_dir($this->dir)) {
            foreach (scandir($this->dir) ?: [] as $name) {
                if (preg_match(self::NAME_PATTERN, $name) === 1 && is_file($this->dir . '/' . $name)) {
                    $bytes += (int) filesize($this->dir . '/' . $name);
                    ++$count;
                }
            }
        }

        return ['bytes' => $bytes, 'count' => $count];
    }

    public function delete(string $name): bool
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || !$this->isInside($name)) {
            return false;
        }

        return @unlink($this->dir . '/' . $name);
    }

    private function describe(string $name, int $modifiedAt): StoredBackup
    {
        $path = $this->dir . '/' . $name;
        try {
            $manifest = $this->inspector->inspect($path);
        } catch (BackupArchiveException) {
            // Cifrada con contraseña, dañada o de otra aplicación: se lista igualmente, sin detalles.
            $manifest = null;
        }

        return new StoredBackup($name, (int) filesize($path), $modifiedAt, $manifest, str_starts_with($name, self::AUTO_PREFIX . '-'));
    }

    /** El fichero existe y, una vez resueltos los enlaces simbólicos, está dentro de la carpeta de copias. */
    private function isInside(string $name): bool
    {
        $real = realpath($this->dir . '/' . $name);
        $base = realpath($this->dir);

        return $real !== false && $base !== false && is_file($real) && str_starts_with($real, $base . DIRECTORY_SEPARATOR);
    }
}
