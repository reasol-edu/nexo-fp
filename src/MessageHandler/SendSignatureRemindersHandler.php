<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendSignatureRemindersMessage;
use App\Service\SignatureReminderDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendSignatureRemindersHandler
{
    public function __construct(
        private readonly SignatureReminderDispatcher $dispatcher,
    ) {}

    public function __invoke(SendSignatureRemindersMessage $message): void
    {
        $this->dispatcher->dispatch();
    }
}
