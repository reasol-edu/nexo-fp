<?php

namespace App;

use App\Message\CreateAutomaticBackupMessage;
use App\Message\HeartbeatMessage;
use App\Message\PurgeActivityLogMessage;
use App\Message\PurgeEmailNotificationLogMessage;
use App\Message\SendDailyEmailDigestsMessage;
use App\Message\SendSignatureRemindersMessage;
use App\Message\SendWeeklyDigestMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->add(
                // Antes de las purgas del domingo (03:00 y 03:30) para que la copia aún contenga lo que van a borrar. No hace
                // nada si el ajuste global backup.auto_enabled está desactivado.
                RecurringMessage::cron('30 2 * * *', new CreateAutomaticBackupMessage()),
            )
            ->add(
                // Señal de vida del worker, que Administración → Estado del sistema muestra.
                RecurringMessage::cron('*/5 * * * *', new HeartbeatMessage()),
            )
            ->add(
                RecurringMessage::cron('0 8 * * *', new SendSignatureRemindersMessage()),
            )
            ->add(
                RecurringMessage::cron('0 8 * * 1', new SendWeeklyDigestMessage()),
            )
            ->add(
                RecurringMessage::cron('0 * * * *', new SendDailyEmailDigestsMessage()),
            )
            ->add(
                RecurringMessage::cron('0 3 * * 0', new PurgeActivityLogMessage()),
            )
            ->add(
                RecurringMessage::cron('30 3 * * 0', new PurgeEmailNotificationLogMessage()),
            )
            ->stateful($this->cache) // recupera disparos perdidos si el worker estuvo apagado
            ->processOnlyLastMissedRun(true) // tras una parada larga, ejecuta solo el último disparo
        ;
    }
}
