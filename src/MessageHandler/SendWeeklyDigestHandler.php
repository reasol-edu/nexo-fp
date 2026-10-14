<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendWeeklyDigestMessage;
use App\Service\WeeklyDigestDispatcher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendWeeklyDigestHandler
{
    public function __construct(
        private readonly WeeklyDigestDispatcher $dispatcher,
    ) {}

    public function __invoke(SendWeeklyDigestMessage $message): void
    {
        $this->dispatcher->dispatch();
    }
}
