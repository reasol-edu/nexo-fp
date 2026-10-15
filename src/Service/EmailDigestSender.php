<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EducationalCentre;
use App\Entity\EmailDigestItem;
use App\Entity\Teacher;
use App\Repository\EmailDigestItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Envía los resúmenes diarios por correo: uno por persona y centro, a la hora que cada persona tenga
 * configurada («email.digest_hour», las 7 por defecto), con las líneas encoladas desde el resumen anterior.
 *
 * Un resumen vence cuando la hora actual ha pasado la hora de resumen de su destinatario; incluye las
 * líneas encoladas hasta esa hora, de modo que una pasada tardía (p. ej. tras parar el worker) sigue
 * enviando un único resumen al día.
 */
final class EmailDigestSender
{
    private const DEFAULT_HOUR = 7;

    /** Las líneas ya enviadas se conservan estos días; las que no pudieron enviarse, el doble. */
    private const KEEP_SENT_DAYS = 7;

    private const KEEP_UNSENT_DAYS = 14;

    public function __construct(
        private readonly EmailDigestItemRepository $items,
        private readonly StayNotifier $notifier,
        private readonly AppSettingsInterface $settings,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @param bool $ignoreHour envía ya todas las líneas pendientes, sea cual sea la hora de cada persona
     * @return int número de resúmenes enviados
     */
    public function sendDue(bool $ignoreHour = false): int
    {
        $now = $this->clock->now();

        /** @var array<string, array{teacher: Teacher, centre: EducationalCentre, items: list<EmailDigestItem>}> $groups */
        $groups = [];
        foreach ($this->items->findUnsent() as $item) {
            $key = $item->getRecipient()->getId()->toRfc4122() . '|' . $item->getEducationalCentre()->getId()->toRfc4122();

            $groups[$key] ??= ['teacher' => $item->getRecipient(), 'centre' => $item->getEducationalCentre(), 'items' => []];
            $groups[$key]['items'][] = $item;
        }

        $sent = 0;
        foreach ($groups as $group) {
            $dueAt = $now->setTime($this->hourFor($group['teacher']), 0);
            if (!$ignoreHour && $now < $dueAt) {
                continue;
            }

            $included = $ignoreHour
                ? $group['items']
                : array_values(array_filter($group['items'], static fn (EmailDigestItem $i): bool => $i->getCreatedAt() <= $dueAt));
            if ($included === []) {
                continue;
            }

            if (!$this->notifier->sendDailyDigest($group['teacher'], $group['centre'], $included)) {
                continue; // las líneas siguen pendientes y se reintentan en la próxima pasada
            }

            foreach ($included as $item) {
                $item->markSent($now);
            }
            $this->em->flush();
            ++$sent;
        }

        $this->items->purge(
            $now->modify('-' . self::KEEP_SENT_DAYS . ' days'),
            $now->modify('-' . self::KEEP_UNSENT_DAYS . ' days'),
        );

        return $sent;
    }

    private function hourFor(Teacher $teacher): int
    {
        $hour = $this->settings->getForTeacher('email.digest_hour', $teacher);

        return \is_int($hour) && $hour >= 0 && $hour <= 23 ? $hour : self::DEFAULT_HOUR;
    }
}
