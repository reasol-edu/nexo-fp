<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailNotificationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un intento de envío de correo electrónico desde la aplicación: a quién, con qué asunto, para qué
 * evento, cuándo y si el transporte lo aceptó. No guarda el contenido del mensaje (los avisos de
 * restablecimiento de contraseña y de verificación llevan enlaces con un token).
 *
 * «Enviado» significa entregado al transporte de correo: con un transporte asíncrono (Messenger) el
 * resultado final de la entrega no se conoce aquí.
 */
#[ORM\Entity(repositoryClass: EmailNotificationLogRepository::class)]
#[ORM\Table(name: 'email_notification_log')]
#[ORM\Index(columns: ['educational_centre_id', 'sent_at'], name: 'idx_enl_centre_sent')]
#[ORM\Index(columns: ['sent_at'], name: 'idx_enl_sent')]
#[ORM\Index(columns: ['event_key'], name: 'idx_enl_event')]
class EmailNotificationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    /** Nulo en los correos que no pertenecen a ningún centro (restablecer contraseña, verificar correo). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?EducationalCentre $educationalCentre;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Teacher $recipient;

    #[ORM\Column(length: 200)]
    private string $recipientName;

    #[ORM\Column(length: 255)]
    private string $recipientEmail;

    #[ORM\Column(length: 50)]
    private string $eventKey;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $success;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    public function __construct(
        ?EducationalCentre $educationalCentre,
        ?Teacher $recipient,
        string $recipientName,
        string $recipientEmail,
        string $eventKey,
        string $subject,
        bool $success,
        ?string $errorMessage,
        \DateTimeImmutable $sentAt,
    ) {
        $this->educationalCentre = $educationalCentre;
        $this->recipient         = $recipient;
        $this->recipientName     = $recipientName;
        $this->recipientEmail    = $recipientEmail;
        $this->eventKey          = $eventKey;
        $this->subject           = $subject;
        $this->success           = $success;
        $this->errorMessage      = $errorMessage;
        $this->sentAt            = $sentAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEducationalCentre(): ?EducationalCentre
    {
        return $this->educationalCentre;
    }

    public function getRecipient(): ?Teacher
    {
        return $this->recipient;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getEventKey(): string
    {
        return $this->eventKey;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
