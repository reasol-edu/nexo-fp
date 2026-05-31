<?php
namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Rcsofttech\AuditTrailBundle\Attribute\Auditable;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[Auditable]
class Company
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $exceptionalCircumstances = null;

    #[ORM\ManyToMany(targetEntity: Teacher::class)]
    #[ORM\JoinTable(name: 'company_liason')]
    private Collection $liasons;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->liasons = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getExceptionalCircumstances(): ?string
    {
        return $this->exceptionalCircumstances;
    }

    public function setExceptionalCircumstances(?string $exceptionalCircumstances): static
    {
        $this->exceptionalCircumstances = $exceptionalCircumstances;

        return $this;
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function getLiasons(): Collection
    {
        return $this->liasons;
    }

    public function addLiason(Teacher $liason): static
    {
        if (!$this->liasons->contains($liason)) {
            $this->liasons->add($liason);
        }

        return $this;
    }

    public function removeLiason(Teacher $liason): static
    {
        $this->liasons->removeElement($liason);

        return $this;
    }
}
