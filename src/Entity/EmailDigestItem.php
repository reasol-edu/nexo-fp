<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmailDigestItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Una línea a la espera del resumen diario por correo de una persona (ver {@see \App\Service\EmailDigestSender}).
 * Guarda los parámetros de traducción y no el texto ya renderizado, de modo que el resumen se redacta al enviarlo.
 *
 * Las líneas se insertan con {@see EmailDigestItemRepository::enqueue()} (conexión DBAL) para no hacer flush de
 * cambios ajenos, igual que el registro de correos.
 */
#[ORM\Entity(repositoryClass: EmailDigestItemRepository::class)]
#[ORM\Table(name: 'email_digest_item')]
#[ORM\Index(columns: ['recipient_id', 'sent_at'], name: 'idx_edi_recipient_sent')]
#[ORM\Index(columns: ['educational_centre_id'], name: 'idx_edi_centre')]
class EmailDigestItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private EducationalCentre $educationalCentre;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Teacher $recipient;

    #[ORM\Column(length: 50)]
    private string $eventKey;

    /** @var array<string, scalar|null> parámetros de traducción de la línea (las claves incluyen los %) */
    #[ORM\Column(type: Types::JSON)]
    private array $params;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $url;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    /**
     * @param array<string, scalar|null> $params parámetros de traducción de la línea (las claves incluyen los %)
     */
    public function __construct(
        EducationalCentre $educationalCentre,
        Teacher $recipient,
        string $eventKey,
        array $params,
        ?string $url,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id                = Uuid::v7();
        $this->educationalCentre = $educationalCentre;
        $this->recipient         = $recipient;
        $this->eventKey          = mb_substr($eventKey, 0, 50);
        $this->params            = $params;
        $this->url               = $url === null ? null : mb_substr($url, 0, 500);
        $this->createdAt         = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEducationalCentre(): EducationalCentre
    {
        return $this->educationalCentre;
    }

    public function getRecipient(): Teacher
    {
        return $this->recipient;
    }

    public function getEventKey(): string
    {
        return $this->eventKey;
    }

    /** @return array<string, scalar|null> */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function markSent(\DateTimeImmutable $at): void
    {
        $this->sentAt = $at;
    }
}
