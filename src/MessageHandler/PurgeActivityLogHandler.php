<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeActivityLogMessage;
use App\Repository\ActivityLogRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgeActivityLogHandler
{
    public function __construct(
        private readonly ActivityLogRepository $logs,
        #[Autowire(env: 'int:APP_LOG_RETENTION_DAYS')] private readonly int $retentionDays,
    ) {}

    public function __invoke(PurgeActivityLogMessage $message): void
    {
        $cutoff = new \DateTimeImmutable("-{$this->retentionDays} days");
        $this->logs->deleteOlderThan($cutoff);
    }
}
