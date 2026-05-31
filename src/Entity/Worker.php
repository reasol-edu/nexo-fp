<?php
namespace App\Entity;

use App\Repository\WorkerRepository;
use Doctrine\ORM\Mapping as ORM;
use Rcsofttech\AuditTrailBundle\Attribute\Auditable;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WorkerRepository::class)]
#[Auditable]
class Worker
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $nationalIdNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $workEmail = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $workPhoneNumber = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getNationalIdNumber(): ?string
    {
        return $this->nationalIdNumber;
    }

    public function setNationalIdNumber(string $nationalIdNumber): static
    {
        $this->nationalIdNumber = $nationalIdNumber;

        return $this;
    }

    public function getWorkEmail(): ?string
    {
        return $this->workEmail;
    }

    public function setWorkEmail(?string $workEmail): static
    {
        $this->workEmail = $workEmail;

        return $this;
    }

    public function getWorkPhoneNumber(): ?string
    {
        return $this->workPhoneNumber;
    }

    public function setWorkPhoneNumber(?string $workPhoneNumber): static
    {
        $this->workPhoneNumber = $workPhoneNumber;

        return $this;
    }
}
