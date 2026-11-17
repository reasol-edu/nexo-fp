<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CreateAutomaticBackupMessage;
use App\Service\Backup\AutomaticBackup;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Cada noche (ver Schedule.php): guarda una copia automática y poda las antiguas según los ajustes globales `backup.auto_*`. Nunca lanza excepciones, así que Messenger no la reintenta. */
#[AsMessageHandler]
final class CreateAutomaticBackupHandler
{
    public function __construct(private readonly AutomaticBackup $automaticBackup) {}

    public function __invoke(CreateAutomaticBackupMessage $message): void
    {
        $this->automaticBackup->run();
    }
}
