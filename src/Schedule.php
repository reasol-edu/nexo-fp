<?php

namespace App;

use App\Message\SendSignatureRemindersMessage;
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
                RecurringMessage::cron('0 8 * * *', new SendSignatureRemindersMessage()),
            )
            ->stateful($this->cache) // recupera disparos perdidos si el worker estuvo apagado
            ->processOnlyLastMissedRun(true) // tras una parada larga, ejecuta solo el último disparo
        ;
    }
}
