<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Backup\BackupLibrary;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lo que necesita ver un administrador para saber que la plataforma está sana: si el worker está vivo (señal de vida), cuánto
 * ocupan la base de datos y las copias y cuánto espacio queda en el disco. Todas las cifras son «lo mejor posible»: si algo
 * falla se devuelve null, nunca una excepción, para que la pantalla se pinte siempre.
 */
class SystemStatus
{
    public const string HEARTBEAT = 'heartbeat';

    /** La señal de vida se escribe cada 5 minutos; con tantos segundos sin ninguna, se considera que el worker está parado. */
    public const int HEARTBEAT_STALE_AFTER = 900;

    public const string WORKER_OK    = 'ok';
    public const string WORKER_STALE = 'stale';
    public const string WORKER_NEVER = 'never';

    public function __construct(
        private readonly SystemState $state,
        private readonly Connection $connection,
        private readonly BackupLibrary $backups,
        #[Autowire('%app.backup_dir%')]
        private readonly string $backupDir,
    ) {}

    /** @return array{state: string, at: int|null} */
    public function worker(): array
    {
        $beat = $this->state->read(self::HEARTBEAT);
        $at   = isset($beat['at']) && \is_int($beat['at']) ? $beat['at'] : null;

        if ($at === null) {
            return ['state' => self::WORKER_NEVER, 'at' => null];
        }

        return ['state' => time() - $at <= self::HEARTBEAT_STALE_AFTER ? self::WORKER_OK : self::WORKER_STALE, 'at' => $at];
    }

    /** @return array{database: int|null, backups: int, backup_count: int, disk_free: int|null} tamaños en bytes */
    public function storage(): array
    {
        $usage = $this->backups->usage();
        $free  = @disk_free_space(is_dir($this->backupDir) ? $this->backupDir : \dirname($this->backupDir));

        return [
            'database'     => $this->databaseSize(),
            'backups'      => $usage['bytes'],
            'backup_count' => $usage['count'],
            'disk_free'    => $free === false ? null : (int) $free,
        ];
    }

    /**
     * Tamaño de la base de datos tal como lo da el motor (datos más índices), nunca sumando los contenidos: eso los leería.
     * Null si no se puede saber (una base en memoria, sin permiso).
     */
    public function databaseSize(): ?int
    {
        try {
            $platform = $this->connection->getDatabasePlatform();

            if ($platform instanceof SQLitePlatform) {
                $path = $this->connection->getParams()['path'] ?? null;

                return \is_string($path) && is_file($path) ? (int) filesize($path) : null;
            }
            if ($platform instanceof PostgreSQLPlatform) {
                $size = $this->connection->fetchOne('SELECT pg_database_size(current_database())');

                return is_numeric($size) ? (int) $size : null;
            }
            if ($platform instanceof AbstractMySQLPlatform) {
                $size = $this->connection->fetchOne('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()');

                return is_numeric($size) ? (int) $size : null;
            }
        } catch (\Throwable) {
            // No disponible: la pantalla muestra un guion.
        }

        return null;
    }
}
