<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendDailyEmailDigestsMessage;
use App\Service\EmailDigestSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Se ejecuta cada hora: envía los resúmenes cuya hora (por persona) ya ha llegado. */
#[AsMessageHandler]
final class SendDailyEmailDigestsHandler
{
    public function __construct(private readonly EmailDigestSender $sender) {}

    public function __invoke(SendDailyEmailDigestsMessage $message): void
    {
        $this->sender->sendDue();
    }
}
