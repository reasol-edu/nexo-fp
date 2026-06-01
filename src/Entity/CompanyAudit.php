<?php
namespace App\Entity;

use App\Repository\CompanyAuditRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CompanyAuditRepository::class)]
class CompanyAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\Column]
    private \DateTimeImmutable $changedAt;

    #[ORM\ManyToOne]
    private ?Teacher $changedBy;

    /** @var array<string, array{old: scalar|null, new: scalar|null}> */
    #[ORM\Column(type: 'json')]
    private array $changes;

    /** @param array<string, array{old: scalar|null, new: scalar|null}> $changes */
    public function __construct(Company $company, ?Teacher $changedBy, array $changes)
    {
        $this->company = $company;
        $this->changedBy = $changedBy;
        $this->changedAt = new \DateTimeImmutable();
        $this->changes = $changes;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function getChangedBy(): ?Teacher
    {
        return $this->changedBy;
    }

    /** @return array<string, array{old: scalar|null, new: scalar|null}> */
    public function getChanges(): array
    {
        return $this->changes;
    }
}
