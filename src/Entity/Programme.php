<?php
namespace App\Entity;

use App\Repository\ProgrammeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgrammeRepository::class)]
class Programme
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProfessionalFamily $professionalFamily = null;

    #[ORM\ManyToMany(targetEntity: Teacher::class)]
    #[ORM\JoinTable(name: 'programme_coordinator')]
    private Collection $coordinators;

    public function __construct()
    {
        $this->coordinators = new ArrayCollection();
    }

    public function getId(): ?int
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

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;

        return $this;
    }

    public function getProfessionalFamily(): ?ProfessionalFamily
    {
        return $this->professionalFamily;
    }

    public function setProfessionalFamily(?ProfessionalFamily $professionalFamily): static
    {
        $this->professionalFamily = $professionalFamily;

        return $this;
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function getCoordinators(): Collection
    {
        return $this->coordinators;
    }

    public function addCoordinator(Teacher $coordinator): static
    {
        if (!$this->coordinators->contains($coordinator)) {
            $this->coordinators->add($coordinator);
        }

        return $this;
    }

    public function removeCoordinator(Teacher $coordinator): static
    {
        $this->coordinators->removeElement($coordinator);

        return $this;
    }
}
