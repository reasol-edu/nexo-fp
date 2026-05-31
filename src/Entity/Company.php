<?php
namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[Gedmo\Loggable]
class Company
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[Gedmo\Versioned]
    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[Gedmo\Versioned]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $vatNumber = null;

    #[Gedmo\Versioned]
    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[Gedmo\Versioned]
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $exceptionalCircumstances = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?EducationalCentre $educationalCentre = null;

    #[ORM\ManyToMany(targetEntity: Teacher::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'company_liaisons')]
    private Collection $liaisons;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->liaisons = new ArrayCollection();
    }

    public function getId(): Uuid
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
    public function getLiaisons(): Collection
    {
        return $this->liaisons;
    }

    public function addLiaison(Teacher $liaison): static
    {
        if (!$this->liaisons->contains($liaison)) {
            $this->liaisons->add($liaison);
        }

        return $this;
    }

    public function removeLiaison(Teacher $liaison): static
    {
        $this->liaisons->removeElement($liaison);

        return $this;
    }

    public function getEducationalCentre(): ?EducationalCentre
    {
        return $this->educationalCentre;
    }

    public function setEducationalCentre(?EducationalCentre $educationalCentre): static
    {
        $this->educationalCentre = $educationalCentre;

        return $this;
    }
}
