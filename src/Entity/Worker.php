<?php
namespace App\Entity;

use App\Repository\WorkerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: WorkerRepository::class)]
class Worker
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Embedded(class: PersonName::class)]
    private PersonName $name;

    #[ORM\Column(length: 20, unique: true)]
    private string $nationalIdNumber;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $workEmail = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $workPhoneNumber = null;

    public function __construct(PersonName $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): PersonName
    {
        return $this->name;
    }

    public function setName(PersonName $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getNationalIdNumber(): string
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
