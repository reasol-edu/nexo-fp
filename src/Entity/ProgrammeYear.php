<?php
namespace App\Entity;

use App\Repository\ProgrammeYearRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProgrammeYearRepository::class)]
class ProgrammeYear
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\ManyToOne(inversedBy: 'programmeYears')]
    #[ORM\JoinColumn(nullable: false)]
    private Programme $programme;

    /**
     * Docentes que coordinan la FP dual de este nivel. Se pueden asignar a un solo nivel (p. ej. solo 2.º)
     * o a todos los de la enseñanza de una vez (ver {@see Programme::addCoordinator()}).
     *
     * @var Collection<int, Teacher>
     */
    #[ORM\ManyToMany(targetEntity: Teacher::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'programme_year_coordinator')]
    private Collection $coordinators;

    public function __construct()
    {
        $this->coordinators = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
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

    public function getProgramme(): Programme
    {
        return $this->programme;
    }

    /**
     * El nivel sin repetir el nombre de su enseñanza: «2.º DAW» en DAW → «2.º»; «Primero» se queda igual.
     * Es lo que se muestra entre paréntesis detrás del nombre de la enseñanza.
     */
    public function getShortName(): string
    {
        $programmeName = trim($this->programme->getName());
        if ($programmeName !== '') {
            $short = trim(str_ireplace($programmeName, '', $this->name), " \t-·");
            if ($short !== '') {
                return $short;
            }
        }

        return $this->name;
    }

    /** Enseñanza y nivel para cuando hay varias enseñanzas a la vista: «DAW · 2.º». */
    public function getLabel(): string
    {
        return $this->programme->getName() . ' · ' . $this->getShortName();
    }

    public function setProgramme(Programme $programme): static
    {
        $this->programme = $programme;
        $programme->registerProgrammeYear($this);

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
