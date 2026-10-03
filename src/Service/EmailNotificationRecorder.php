<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Deja constancia en el registro de correos (EmailNotificationLog) de cada intento de envío.
 *
 * Inserta directamente con la conexión DBAL en vez de persistir una entidad: así no hace flush de
 * cambios pendientes que no le corresponden (los avisos se envían justo después del flush de la
 * operación del usuario) y sigue funcionando aunque el EntityManager se haya cerrado por un error.
 * Registrar nunca debe impedir ni deshacer el envío: cualquier fallo se anota y se ignora.
 */
class EmailNotificationRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param string  $eventKey     identifica el evento (p. ej. «tutor_assigned»), máx. 50 caracteres
     * @param ?string $errorMessage motivo del fallo; null si el transporte aceptó el mensaje
     */
    public function record(
        ?EducationalCentre $centre,
        ?Teacher $recipient,
        string $recipientName,
        string $recipientEmail,
        string $eventKey,
        string $subject,
        ?string $errorMessage = null,
    ): void {
        try {
            $this->em->getConnection()->insert(
                'email_notification_log',
                [
                    'educational_centre_id' => $centre?->getId(),
                    'recipient_id'          => $recipient?->getId(),
                    'recipient_name'        => mb_substr($recipientName, 0, 200),
                    'recipient_email'       => mb_substr($recipientEmail, 0, 255),
                    'event_key'             => mb_substr($eventKey, 0, 50),
                    'subject'               => mb_substr($subject, 0, 255),
                    'success'               => $errorMessage === null,
                    'error_message'         => $errorMessage,
                    'sent_at'               => $this->clock->now(),
                ],
                [
                    'educational_centre_id' => 'uuid',
                    'recipient_id'          => 'uuid',
                    'success'               => 'boolean',
                    'sent_at'               => 'datetime_immutable',
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->warning('No se pudo registrar el envío del correo "{subject}": {error}', [
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
