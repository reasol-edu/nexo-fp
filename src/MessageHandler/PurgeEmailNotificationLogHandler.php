<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeEmailNotificationLogMessage;
use App\Repository\EmailNotificationLogRepository;
use App\Service\AppSettingsInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Borra las entradas del registro de correos más antiguas que el ajuste global
 * email.log_retention_days (0 = no se borra nunca).
 */
#[AsMessageHandler]
final class PurgeEmailNotificationLogHandler
{
    private const DEFAULT_DAYS = 90;

    public function __construct(
        private readonly EmailNotificationLogRepository $logs,
        private readonly AppSettingsInterface $settings,
        private readonly ClockInterface $clock,
    ) {}

    public function __invoke(PurgeEmailNotificationLogMessage $message): void
    {
        // getGlobal(): el worker no tiene sesión ni centro seleccionado.
        $days = $this->settings->getGlobal('email.log_retention_days');
        $days = \is_int($days) ? $days : self::DEFAULT_DAYS;
        if ($days <= 0) {
            return;
        }

        $this->logs->deleteOlderThan($this->clock->now()->modify("-{$days} days"));
    }
}
