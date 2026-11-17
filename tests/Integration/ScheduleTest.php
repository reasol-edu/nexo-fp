<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\CreateAutomaticBackupMessage;
use App\Message\HeartbeatMessage;
use App\Schedule;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;

/** Las tareas programadas que Administración da por hechas: la copia automática (02:30) y la señal de vida del worker (cada 5 minutos). */
final class ScheduleTest extends KernelTestCase
{
    public function testTheBackupAndTheHeartbeatAreScheduled(): void
    {
        self::bootKernel();

        /** @var Schedule $provider */
        $provider = self::getContainer()->get(Schedule::class);

        /** @var array<class-string, string> $expressions mensaje => expresión cron */
        $expressions = [];
        foreach ($provider->getSchedule()->getRecurringMessages() as $recurring) {
            $context = new MessageContext('default', $recurring->getId(), $recurring->getTrigger(), new \DateTimeImmutable('2026-01-01 00:00:00'));
            foreach ($recurring->getMessages($context) as $message) {
                $expressions[$message::class] = (string) $recurring->getTrigger();
            }
        }

        self::assertSame('30 2 * * *', $expressions[CreateAutomaticBackupMessage::class] ?? null);
        self::assertSame('*/5 * * * *', $expressions[HeartbeatMessage::class] ?? null);
    }
}
