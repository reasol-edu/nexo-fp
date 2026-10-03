<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Student;
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
        private readonly EmailNotificationRecorder $recorder,
    ) {}

    public function notifyTutorAssigned(TrainingPosition $position): void
    {
        $tutor = $position->getAcademicTutor();
        if ($tutor === null || !$this->hasEmail($tutor, 'tutor_assigned')) {
            return;
        }

        $stay   = $position->getStay();
        $centre = $stay->getAcademicYear()->getEducationalCentre();

        $this->send((new TemplatedEmail())
            ->to(new Address((string) $tutor->getEmail(), $this->fullName($tutor)))
            ->subject($this->subject('emails.tutor_assigned.subject', $centre))
            ->htmlTemplate('email/tutor_assigned.html.twig')
            ->context([
                'tutor'    => $tutor,
                'stay'     => $stay,
                'position' => $position,
                'stay_url' => $this->stayUrl($stay),
            ]), 'tutor_assigned', $centre, $tutor);
    }

    public function notifyLiaisonsPositionsCreated(Stay $stay, Company $company, int $count, ?Teacher $skip = null): void
    {
        $centre = $stay->getAcademicYear()->getEducationalCentre();

        foreach ($company->getLiaisons() as $liaison) {
            if ($skip !== null && $liaison->getId()->toRfc4122() === $skip->getId()->toRfc4122()) {
                continue;
            }
            if (!$this->hasEmail($liaison, 'positions_created')) {
                continue;
            }

            $this->send((new TemplatedEmail())
                ->to(new Address((string) $liaison->getEmail(), $this->fullName($liaison)))
                ->subject($this->subject('emails.positions_created.subject', $centre))
                ->htmlTemplate('email/positions_created.html.twig')
                ->context([
                    'liaison'  => $liaison,
                    'company'  => $company,
                    'stay'     => $stay,
                    'count'    => $count,
                    'stay_url' => $this->stayUrl($stay),
                ]), 'positions_created', $centre, $liaison);
        }
    }

    /**
     * Avisa a las coordinaciones de las demás enseñanzas a las que se ofertaba un puesto compartido
     * de que otra coordinación lo ha asignado a un estudiante suyo y ya no está disponible.
     * No envía nada si el puesto no se compartía con ninguna otra enseñanza.
     */
    public function notifySharedPositionTaken(TrainingPosition $position, Student $student, Teacher $actor): void
    {
        $own = $position->getStay()->getProgrammesOfStudent($student);

        $this->notifySharedPosition($position, 'taken', $actor, $own, $student);
    }

    /**
     * Avisa a las coordinaciones de las demás enseñanzas a las que se ofertaba un puesto libre
     * compartido de que otra persona lo ha eliminado.
     *
     * @param list<Programme> $actorProgrammes enseñanzas de la estancia que gestiona quien lo elimina
     */
    public function notifySharedPositionRemoved(TrainingPosition $position, Teacher $actor, array $actorProgrammes): void
    {
        $this->notifySharedPosition($position, 'removed', $actor, $actorProgrammes, null);
    }

    /**
     * @param list<Programme> $ownProgrammes enseñanzas ya implicadas en el cambio, que no se avisan
     */
    private function notifySharedPosition(
        TrainingPosition $position,
        string $change,
        Teacher $actor,
        array $ownProgrammes,
        ?Student $student,
    ): void {
        $stay   = $position->getStay();
        $centre = $stay->getAcademicYear()->getEducationalCentre();

        $ownIds = array_map(static fn (Programme $p): string => $p->getId()->toRfc4122(), $ownProgrammes);

        /** @var array<string, Teacher> $recipients */
        $recipients = [];
        foreach ($position->getProgrammeYears() as $programmeYear) {
            $programme = $programmeYear->getProgramme();
            if (in_array($programme->getId()->toRfc4122(), $ownIds, true)) {
                continue;
            }
            foreach ($programme->getCoordinators() as $coordinator) {
                $recipients[$coordinator->getId()->toRfc4122()] = $coordinator;
            }
        }
        unset($recipients[$actor->getId()->toRfc4122()]);

        foreach ($recipients as $recipient) {
            if (!$this->hasEmail($recipient, 'shared_position')) {
                continue;
            }

            $this->send((new TemplatedEmail())
                ->to(new Address((string) $recipient->getEmail(), $this->fullName($recipient)))
                ->subject($this->subject('emails.shared_position.subject.' . $change, $centre))
                ->htmlTemplate('email/shared_position.html.twig')
                ->context([
                    'recipient' => $recipient,
                    'change'    => $change,
                    'stay'      => $stay,
                    'position'  => $position,
                    'student'   => $student,
                    'actor'     => $actor,
                    'stay_url'  => $this->stayUrl($stay),
                ]), 'shared_position', $centre, $recipient);
        }
    }

    /**
     * Envía a un destinatario el recordatorio de puestos registrados sin firmar,
     * agrupado por estancia. Devuelve true si se ha enviado (false si el
     * destinatario no tiene email o lo tiene desactivado, o no hay grupos).
     *
     * @param list<array{stay: Stay, positions: list<TrainingPosition>}> $groups
     */
    public function sendSignatureReminderDigest(Teacher $recipient, array $groups): bool
    {
        if ($groups === [] || !$this->hasEmail($recipient, 'signature_reminder')) {
            return false;
        }

        $stayUrls = [];
        foreach ($groups as $group) {
            $stayId = $group['stay']->getId()->toRfc4122();
            $stayUrls[$stayId] ??= $this->stayUrl($group['stay']);
        }

        $centre = $groups[0]['stay']->getAcademicYear()->getEducationalCentre();

        $this->send((new TemplatedEmail())
            ->to(new Address((string) $recipient->getEmail(), $this->fullName($recipient)))
            ->subject($this->subject('emails.signature_reminder.subject', $centre))
            ->htmlTemplate('email/signature_reminder.html.twig')
            ->context([
                'recipient' => $recipient,
                'groups'    => $groups,
                'stay_urls' => $stayUrls,
            ]), 'signature_reminder', $centre, $recipient);

        return true;
    }

    /** Antepone el prefijo de asunto configurado (global o de centro) al texto traducido, si lo hay. */
    private function subject(string $translationKey, EducationalCentre $centre): string
    {
        $text   = $this->translator->trans($translationKey, [], 'emails');
        $prefix = (string) $this->appSettings->getForCentre('email.subject_prefix', $centre);

        return $prefix === '' ? $text : $prefix . ' ' . $text;
    }

    private function send(TemplatedEmail $email, string $eventKey, EducationalCentre $centre, Teacher $recipient): void
    {
        $email->from(new Address($this->fromAddress, $this->appName));

        $error = null;
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $error = $e->getMessage();
            $this->logger->error('Could not send email "{subject}": {error}', [
                'subject' => $email->getSubject(),
                'error'   => $error,
            ]);
        }

        $this->recorder->record(
            $centre,
            $recipient,
            $this->fullName($recipient),
            (string) $recipient->getEmail(),
            $eventKey,
            (string) $email->getSubject(),
            $error,
        );
    }

    private function hasEmail(Teacher $teacher, string $kind): bool
    {
        if ($teacher->getEmail() === null || $teacher->getEmail() === '') {
            $this->logger->info('Notification "{kind}" skipped: teacher {username} has no email.', [
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
