<?php
namespace App\Entity;

use App\Repository\TrainingPositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingPositionRepository::class)]
class TrainingPosition
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\Column]
    private bool $signed = false;

    #[ORM\Column(type: 'string', enumType: TrainingPositionState::class)]
    private TrainingPositionState $state = TrainingPositionState::DRAFT;

    // Relación con Estudiante
    #[ORM\ManyToOne]
    private ?Student $student = null;

    #[ORM\ManyToOne]
    private ?Teacher $academicTutor = null;

    #[ORM\ManyToOne]
    private ?Worker $workplaceMentor = null;

    #[ORM\ManyToMany(targetEntity: ProgrammeYear::class)]
    private Collection $programmeYears;

    #[ORM\ManyToOne]
    private ?Workcenter $workcenter = null;

    public function __construct()
    {
        $this->programmeYears = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function isSigned(): ?bool
    {
        return $this->signed;
    }

    public function setSigned(bool $signed): static
    {
        $this->signed = $signed;

        return $this;
    }

    public function getState(): ?TrainingPositionState
    {
        return $this->state;
    }

    public function setState(TrainingPositionState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;

        return $this;
    }

    public function getAcademicTutor(): ?Teacher
    {
        return $this->academicTutor;
    }

    public function setAcademicTutor(?Teacher $academicTutor): static
    {
        $this->academicTutor = $academicTutor;

        return $this;
    }

    public function getWorkplaceMentor(): ?Worker
    {
        return $this->workplaceMentor;
    }

    public function setWorkplaceMentor(?Worker $workplaceMentor): static
    {
        $this->workplaceMentor = $workplaceMentor;

        return $this;
    }

    /**
     * @return Collection<int, ProgrammeYear>
     */
    public function getProgrammeYears(): Collection
    {
        return $this->programmeYears;
    }

    public function addProgrammeYear(ProgrammeYear $programmeYear): static
    {
        if (!$this->programmeYears->contains($programmeYear)) {
            $this->programmeYears->add($programmeYear);
        }

        return $this;
    }

    public function removeProgrammeYear(ProgrammeYear $programmeYear): static
    {
        $this->programmeYears->removeElement($programmeYear);

        return $this;
    }

    public function getWorkcenter(): ?Workcenter
    {
        return $this->workcenter;
    }

    public function setWorkcenter(?Workcenter $workcenter): static
    {
        $this->workcenter = $workcenter;

        return $this;
    }


}
