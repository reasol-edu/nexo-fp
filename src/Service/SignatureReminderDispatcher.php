<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Repository\EducationalCentreRepository;
use App\Repository\TrainingPositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Envía el aviso diario de puestos registrados sin firmar a quienes tienen
 * responsabilidad sobre ellos (tutor dual docente, coordinación de FP dual y
 * jefatura de familia profesional), agrupado por persona y estancia.
 *
 * Idempotente por día: cada estancia incluida se sella con la fecha del aviso
 * y se omite si ya se procesó hoy (compatible con el Scheduler stateful, que
 * puede recuperar disparos perdidos).
 */
final class SignatureReminderDispatcher
{
    private const DEFAULT_DAYS = 7;

    public function __construct(
        private readonly EducationalCentreRepository $centres,
        private readonly TrainingPositionRepository $positions,
        private readonly StayNotifier $notifier,
        private readonly AppSettingsInterface $appSettings,
        private readonly ClockInterface $clock,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array{recipients: int, stays: int}
     */
    public function dispatch(?int $daysOverride = null): array
    {
        $now      = $this->clock->now();
        $today    = $now->setTime(0, 0, 0);
        $todayKey = $today->format('Y-m-d');

        $recipientsNotified = 0;
        $staysSealed        = 0;

        foreach ($this->centres->findAllOrderedByName() as $centre) {
            $days = $daysOverride ?? (int) ($this->appSettings->getForCentre(
                'email.notification.signature_reminder.days',
                $centre,
            ) ?? self::DEFAULT_DAYS);

            if ($days < 1) {
                $days = self::DEFAULT_DAYS;
            }

            $limit = $today->modify(sprintf('+%d days', $days));

            /** @var array<string, Stay> $staysToSeal */
            $staysToSeal = [];
            /** @var array<string, array{teacher: Teacher, positions: array<string, TrainingPosition>}> $byRecipient */
            $byRecipient = [];

            foreach ($this->positions->findRegisteredUnsignedStartingWithinForCentre($centre, $limit) as $position) {
                $stay = $position->getStay();

                if ($stay->getLastSignatureReminderSentAt()?->format('Y-m-d') === $todayKey) {
                    continue;
                }

                $staysToSeal[$stay->getId()->toRfc4122()] = $stay;

                $programme = $stay->getProgramme();

                $recipients = [];
                if (($tutor = $position->getAcademicTutor()) !== null) {
                    $recipients[] = $tutor;
                }
                foreach ($programme->getCoordinators() as $coordinator) {
                    $recipients[] = $coordinator;
                }
                if (($head = $programme->getProfessionalFamily()->getHead()) !== null) {
                    $recipients[] = $head;
                }

                foreach ($recipients as $recipient) {
                    $teacherId = $recipient->getId()->toRfc4122();
                    $byRecipient[$teacherId] ??= ['teacher' => $recipient, 'positions' => []];
                    $byRecipient[$teacherId]['positions'][$position->getId()->toRfc4122()] = $position;
                }
            }

            foreach ($byRecipient as $entry) {
                $groups = $this->groupByStay(array_values($entry['positions']));
                if ($this->notifier->sendSignatureReminderDigest($entry['teacher'], $groups)) {
                    $recipientsNotified++;
                }
            }

            foreach ($staysToSeal as $stay) {
                $stay->setLastSignatureReminderSentAt($now);
                $staysSealed++;
            }

            if ($staysToSeal !== []) {
                $this->em->flush();
            }
        }

        return ['recipients' => $recipientsNotified, 'stays' => $staysSealed];
    }

    /**
     * @param list<TrainingPosition> $positions ya ordenados por inicio de estancia y apellido
     * @return list<array{stay: Stay, positions: list<TrainingPosition>}>
     */
    private function groupByStay(array $positions): array
    {
        /** @var array<string, array{stay: Stay, positions: list<TrainingPosition>}> $groups */
        $groups = [];
        foreach ($positions as $position) {
            $stay   = $position->getStay();
            $stayId = $stay->getId()->toRfc4122();
            $groups[$stayId] ??= ['stay' => $stay, 'positions' => []];
            $groups[$stayId]['positions'][] = $position;
        }

        return array_values($groups);
    }
}
