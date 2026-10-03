<?php

declare(strict_types=1);

namespace App\Tests\Integration\MessageHandler;

use App\Entity\EmailNotificationLog;
use App\Entity\GlobalSettingValue;
use App\Entity\SettingDefinition;
use App\Entity\SettingType;
use App\Message\PurgeEmailNotificationLogMessage;
use App\MessageHandler\PurgeEmailNotificationLogHandler;
use App\Repository\EmailNotificationLogRepository;
use App\Service\AppSettings;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Clock\MockClock;

class PurgeEmailNotificationLogHandlerTest extends RepositoryTestCase
{
    private const NOW = '2026-10-05 12:00:00';

    public function testDeletesEntriesOlderThanTheDefaultRetention(): void
    {
        $this->seedSetting(null);
        $this->logAt('2026-07-01 10:00:00', 'antiguo');  // 96 días
        $this->logAt('2026-08-01 10:00:00', 'reciente'); // 65 días

        $this->handler()(new PurgeEmailNotificationLogMessage());

        self::assertSame(['reciente'], $this->remainingSubjects());
    }

    public function testFollowsTheConfiguredRetention(): void
    {
        $this->seedSetting(30);
        $this->logAt('2026-08-01 10:00:00', 'de hace dos meses');
        $this->logAt('2026-09-20 10:00:00', 'de hace dos semanas');

        $this->handler()(new PurgeEmailNotificationLogMessage());

        self::assertSame(['de hace dos semanas'], $this->remainingSubjects());
    }

    public function testZeroNeverDeletes(): void
    {
        $this->seedSetting(0);
        $this->logAt('2020-01-01 10:00:00', 'muy antiguo');

        $this->handler()(new PurgeEmailNotificationLogMessage());

        self::assertSame(['muy antiguo'], $this->remainingSubjects());
    }

    private function seedSetting(?int $days): void
    {
        $definition = (new SettingDefinition())
            ->setKey('email.log_retention_days')
            ->setType(SettingType::Integer)
            ->setDefaultValue('90')
            ->setGlobalScope(true)
            ->setMinValue(0)
            ->setMaxValue(3650);
        $this->persist($definition);

        if ($days !== null) {
            $this->persist((new GlobalSettingValue())->setDefinition($definition)->setValue((string) $days));
        }
    }

    private function handler(): PurgeEmailNotificationLogHandler
    {
        /** @var EmailNotificationLogRepository $logs */
        $logs = self::getContainer()->get(EmailNotificationLogRepository::class);
        /** @var AppSettings $settings */
        $settings = self::getContainer()->get(AppSettings::class);

        return new PurgeEmailNotificationLogHandler($logs, $settings, new MockClock(self::NOW));
    }

    private function logAt(string $when, string $subject): void
    {
        $this->persist(new EmailNotificationLog(null, null, 'Alguien', 'alguien@test.local', 'tutor_assigned', $subject, true, null, new \DateTimeImmutable($when)));
    }

    /** @return list<string> */
    private function remainingSubjects(): array
    {
        $this->em->clear();

        return array_map(
            static fn (EmailNotificationLog $e): string => $e->getSubject(),
            $this->em->getRepository(EmailNotificationLog::class)->findAll(),
        );
    }
}
