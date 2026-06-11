<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Company;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


/**
 * Envía notificaciones por email relacionadas con las estancias. Los fallos
 * de transporte se registran pero nunca se propagan: el envío ocurre después
 * del flush y no debe deshacer la operación del usuario.
 */
class StayNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $fromAddress,
        #[Autowire('%app.name%')]
        private readonly string $appName,
        private readonly AppSettingsInterface $appSettings,
    ) {}

    public function notifyTutorAssigned(TrainingPosition $position): void
    {
        $tutor = $position->getAcademicTutor();
        if ($tutor === null || !$this->hasEmail($tutor, 'tutor_assigned')) {
            return;
        }

        $stay = $position->getStay();

        $this->send((new TemplatedEmail())
            ->to(new Address((string) $tutor->getEmail(), $this->fullName($tutor)))
            ->subject($this->translator->trans('emails.tutor_assigned.subject', [], 'emails'))
            ->htmlTemplate('email/tutor_assigned.html.twig')
            ->context([
                'tutor'    => $tutor,
                'stay'     => $stay,
                'position' => $position,
                'stay_url' => $this->stayUrl($stay),
            ]));
    }

    public function notifyLiaisonsPositionsCreated(Stay $stay, Company $company, int $count, ?Teacher $skip = null): void
    {
        foreach ($company->getLiaisons() as $liaison) {
            if ($skip !== null && $liaison->getId()->toRfc4122() === $skip->getId()->toRfc4122()) {
                continue;
            }
            if (!$this->hasEmail($liaison, 'positions_created')) {
                continue;
            }

            $this->send((new TemplatedEmail())
                ->to(new Address((string) $liaison->getEmail(), $this->fullName($liaison)))
                ->subject($this->translator->trans('emails.positions_created.subject', [], 'emails'))
                ->htmlTemplate('email/positions_created.html.twig')
                ->context([
                    'liaison'  => $liaison,
                    'company'  => $company,
                    'stay'     => $stay,
                    'count'    => $count,
                    'stay_url' => $this->stayUrl($stay),
                ]));
        }
    }

    /** @param list<TrainingPosition> $positions */
    public function sendSignatureReminder(Teacher $tutor, array $positions, int $daysLeft): void
    {
        if ($positions === [] || !$this->hasEmail($tutor, 'signature_reminder')) {
            return;
        }

        $stayUrls = [];
        foreach ($positions as $position) {
            $stayId = $position->getStay()->getId()->toRfc4122();
            $stayUrls[$stayId] ??= $this->stayUrl($position->getStay());
        }

        $this->send((new TemplatedEmail())
            ->to(new Address((string) $tutor->getEmail(), $this->fullName($tutor)))
            ->subject($this->translator->trans('emails.signature_reminder.subject', [], 'emails'))
            ->htmlTemplate('email/signature_reminder.html.twig')
            ->context([
                'tutor'     => $tutor,
                'positions' => $positions,
                'days_left' => $daysLeft,
                'stay_urls' => $stayUrls,
            ]));
    }

    private function send(TemplatedEmail $email): void
    {
        $email->from(new Address($this->fromAddress, $this->appName));

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('No se pudo enviar el email "{subject}": {error}', [
                'subject' => $email->getSubject(),
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function hasEmail(Teacher $teacher, string $kind): bool
    {
        if ($teacher->getEmail() === null || $teacher->getEmail() === '') {
            $this->logger->info('Notificación "{kind}" omitida: el docente {username} no tiene email.', [
                'kind'     => $kind,
                'username' => $teacher->getUsername(),
            ]);

            return false;
        }

        if ($this->appSettings->getForTeacher('email.notifications', $teacher) === false) {
            return false;
        }

        if ($this->appSettings->getForTeacher('email.notification.' . $kind, $teacher) === false) {
            return false;
        }

        return true;
    }

    private function fullName(Teacher $teacher): string
    {
        return $teacher->getName()->getFirstName() . ' ' . $teacher->getName()->getLastName();
    }

    private function stayUrl(Stay $stay): string
    {
        return $this->urlGenerator->generate(
            'app_stays_show',
            ['id' => $stay->getId()->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
