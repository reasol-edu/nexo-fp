<?php

declare(strict_types=1);

namespace App\Service\Backup;

use App\Service\AppSettingsInterface;
use App\Service\SystemState;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * La copia automática nocturna (ver Schedule.php) y lo que Administración muestra de ella.
 *
 * Es opcional, desde los ajustes globales: `backup.auto_enabled` (desactivada por defecto), cuántas copias se conservan
 * (`backup.auto_keep_daily`, `backup.auto_keep_weekly` y `backup.auto_keep_monthly`, ver BackupRetentionPolicy) y
 * `backup.auto_max_size_mb` (0 = sin límite), el espacio que pueden ocupar entre todas. El resultado de cada ejecución se
 * anota en `var/state` para que las pantallas digan cuándo fue la última y si falló; la ejecución nunca lanza excepciones
 * (una copia fallida no debe reintentarla Messenger: varias ejecuciones pesadas seguidas solo empeorarían las cosas).
 */
class AutomaticBackup
{
    public const string STATE = 'backup-auto';

    public function __construct(
        private readonly BackupLibrary $library,
        private readonly SystemState $state,
        private readonly LoggerInterface $logger,
        private readonly AppSettingsInterface $settings,
        #[Autowire('%app.backup_stale_days%')]
        private readonly int $staleDays,
    ) {}

    public function isEnabled(): bool
    {
        // getGlobal(), nunca get(): esto corre en el worker, donde no hay petición de la que sacar un centro.
        return $this->settings->getGlobal('backup.auto_enabled') === true;
    }

    /** Cuántas copias automáticas se conservan. */
    public function policy(): BackupRetentionPolicy
    {
        return new BackupRetentionPolicy(
            $this->intSetting('backup.auto_keep_daily'),
            $this->intSetting('backup.auto_keep_weekly'),
            $this->intSetting('backup.auto_keep_monthly'),
        );
    }

    /** Espacio que pueden ocupar entre todas las copias automáticas, en MB (0 = sin límite). */
    public function maxSizeMb(): int
    {
        return $this->intSetting('backup.auto_max_size_mb');
    }

    /** Hace la copia y poda las antiguas. No hace nada si está desactivada. */
    public function run(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $backup  = $this->library->saveAutomatic();
            $deleted = $this->library->pruneAutomatic($this->policy(), $this->maxSizeMb() * 1048576);
            $this->state->write(self::STATE, ['ok' => true, 'at' => time(), 'file' => $backup->name, 'size' => $backup->size, 'pruned' => $deleted]);
            $this->logger->info('Copia automática guardada: {file} ({size} bytes); {pruned} antigua(s) eliminada(s).', ['file' => $backup->name, 'size' => $backup->size, 'pruned' => $deleted]);
        } catch (\Throwable $e) {
            $this->state->write(self::STATE, ['ok' => false, 'at' => time(), 'error' => mb_substr($e->getMessage(), 0, 300)]);
            $this->logger->error('La copia automática ha fallado: {reason}', ['reason' => $e->getMessage(), 'exception' => $e]);
        }
    }

    /**
     * Lo que muestra Administración: si está activada, la última ejecución, la copia más reciente de cualquier tipo y si toca hacer otra.
     *
     * @return array{enabled: bool, tiers: array{daily: int, weekly: int, monthly: int}, max_size_mb: int, last_run: array<string, mixed>|null, last_backup_at: int|null, stale: bool, stale_days: int}
     */
    public function status(): array
    {
        $last  = $this->library->lastBackupAt();
        $stale = $last === null || time() - $last > $this->staleDays * 86400;

        return [
            'enabled'        => $this->isEnabled(),
            'tiers'          => $this->policy()->tiers(),
            'max_size_mb'    => $this->maxSizeMb(),
            'last_run'       => $this->state->read(self::STATE),
            'last_backup_at' => $last,
            'stale'          => $stale,
            'stale_days'     => $this->staleDays,
        ];
    }

    private function intSetting(string $key): int
    {
        $value = $this->settings->getGlobal($key);

        return \is_int($value) ? max(0, $value) : 0;
    }
}
