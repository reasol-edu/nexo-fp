<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_al_created')]
#[ORM\Index(columns: ['active_user_id', 'created_at'], name: 'idx_al_user_created')]
#[ORM\Index(columns: ['action_type', 'created_at'], name: 'idx_al_type_created')]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 45)]
    private string $ip;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Teacher $activeUser = null;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Teacher $realUser = null;

    #[ORM\ManyToOne(targetEntity: AcademicYear::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AcademicYear $academicYear = null;

    #[ORM\Column(length: 100)]
    private string $actionType;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data = null;

    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        \DateTimeImmutable $createdAt,
        string $ip,
        string $actionType,
        ?Teacher $activeUser = null,
        ?Teacher $realUser = null,
        ?AcademicYear $academicYear = null,
        ?array $data = null,
    ) {
        $this->createdAt    = $createdAt;
        $this->ip           = $ip;
        $this->actionType   = $actionType;
        $this->activeUser   = $activeUser;
        $this->realUser     = $realUser;
        $this->academicYear = $academicYear;
        $this->data         = $data;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getActiveUser(): ?Teacher
    {
        return $this->activeUser;
    }

    public function getRealUser(): ?Teacher
    {
        return $this->realUser;
    }

    public function getAcademicYear(): ?AcademicYear
    {
        return $this->academicYear;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    /** @return array<string, mixed>|null */
    public function getData(): ?array
    {
        return $this->data;
    }
}
