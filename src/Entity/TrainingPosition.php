<?php
namespace App\Entity;

use App\Repository\TrainingPositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TrainingPositionRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_stay_student', columns: ['stay_id', 'student_id'])]
class TrainingPosition
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column]
    private bool $signed = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $signedAt = null;

    #[ORM\Column(type: Types::STRING, enumType: TrainingPositionState::class)]
    private TrainingPositionState $state = TrainingPositionState::DRAFT;

    #[ORM\ManyToOne(inversedBy: 'trainingPositions')]
    #[ORM\JoinColumn(nullable: false)]
    private Stay $stay;

    #[ORM\ManyToOne]
    private ?Student $student = null;

    #[ORM\ManyToOne]
    private ?Teacher $academicTutor = null;

    #[ORM\ManyToOne]
    private ?Worker $workplaceMentor = null;

    /** @var Collection<int, ProgrammeYear> */
    #[ORM\ManyToMany(targetEntity: ProgrammeYear::class, fetch: 'EXTRA_LAZY')]
    private Collection $programmeYears;

    /** Enseñanza con preferencia sobre el puesto hasta {@see $priorityUntil}; después se abre a todas. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Programme $priorityProgramme = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $priorityUntil = null;

    #[ORM\ManyToOne]
    private ?Workcenter $workcenter = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    public function __construct()
    {
        $this->programmeYears = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
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

    public function isSigned(): bool
    {
        return $this->signed;
    }

    public function setSigned(bool $signed): static
    {
        if ($signed && !$this->signed) {
            $this->signedAt = new \DateTimeImmutable();
        } elseif (!$signed) {
            $this->signedAt = null;
        }

        $this->signed = $signed;

        return $this;
    }

    public function getSignedAt(): ?\DateTimeImmutable
    {
        return $this->signedAt;
    }

    public function setSignedAt(?\DateTimeImmutable $signedAt): static
    {
        $this->signedAt = $signedAt;

        return $this;
    }

    public function getState(): TrainingPositionState
    {
        return $this->state;
    }

    public function setState(TrainingPositionState $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getStay(): Stay
    {
        return $this->stay;
    }

    public function setStay(Stay $stay): static
    {
        $this->stay = $stay;

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

    public function getPriorityProgramme(): ?Programme
    {
        return $this->priorityProgramme;
    }

    public function getPriorityUntil(): ?\DateTimeImmutable
    {
        return $this->priorityUntil;
    }

    /** Fija (o quita, con null) la enseñanza preferente y la fecha hasta la que lo es. */
    public function setPriority(?Programme $programme, ?\DateTimeImmutable $until): static
    {
        $active = $programme !== null && $until !== null;

        $this->priorityProgramme = $active ? $programme : null;
        $this->priorityUntil     = $active ? $until : null;

        return $this;
    }

    /** ¿Hay una preferencia vigente el día indicado? La fecha límite es inclusive. */
    public function hasActivePriority(\DateTimeImmutable $today): bool
    {
        return $this->priorityProgramme !== null
            && $this->priorityUntil !== null
            && $today->setTime(0, 0) <= $this->priorityUntil;
    }

    /**
     * ¿Está el puesto reservado, por una preferencia vigente, a otra enseñanza distinta de las del
     * alumno? Un alumno sin grupo en ninguna enseñanza de la estancia no se considera excluido.
     *
     * @param Programme|null          $programme preferencia a evaluar en lugar de la guardada
     *                                           (p. ej. la recién enviada en un formulario)
     * @param \DateTimeImmutable|null $until     fecha límite a evaluar en lugar de la guardada
     */
    public function isReservedAgainst(
        Student $student,
        \DateTimeImmutable $today,
        ?Programme $programme = null,
        ?\DateTimeImmutable $until = null,
    ): bool {
        $programme ??= $this->priorityProgramme;
        $until     ??= $this->priorityUntil;

        if ($programme === null || $until === null || $today->setTime(0, 0) > $until) {
            return false;
        }

        $studentProgrammes = $this->stay->getProgrammesOfStudent($student);
        if ($studentProgrammes === []) {
            return false;
        }

        foreach ($studentProgrammes as $studentProgramme) {
            if ($studentProgramme->getId()->equals($programme->getId())) {
                return false;
            }
        }

        return true;
    }

    /**
     * Indica si el puesto está ofertado al nivel del alumno. Un puesto sin niveles, o un alumno
     * sin grupo en ninguna enseñanza de la estancia, no restringen la compatibilidad.
     *
     * @param iterable<ProgrammeYear>|null $programmeYears niveles a evaluar en lugar de los actuales
     *                                                     (p. ej. los recién enviados en un formulario)
     */
    public function acceptsStudent(Student $student, ?iterable $programmeYears = null): bool
    {
        $programmeYears = $programmeYears !== null ? [...$programmeYears] : $this->programmeYears->toArray();
        if ($programmeYears === []) {
            return true;
        }

        $studentYearIds = [];
        foreach ($student->getGroups() as $group) {
            $programmeYear = $group->getProgrammeYear();
            if ($this->stay->hasProgramme($programmeYear->getProgramme())) {
                $studentYearIds[$programmeYear->getId()->toRfc4122()] = true;
            }
        }

        if ($studentYearIds === []) {
            return true;
        }

        foreach ($programmeYears as $programmeYear) {
            if (isset($studentYearIds[$programmeYear->getId()->toRfc4122()])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enseñanzas a las que pertenece el puesto, separadas por « · »: las del estudiante dentro de
     * la estancia si está asignado; si no, las de los niveles a los que se oferta.
     */
    public function getProgrammeNames(): string
    {
        $programmes = $this->student !== null ? $this->stay->getProgrammesOfStudent($this->student) : [];
        if ($programmes === []) {
            foreach ($this->programmeYears as $programmeYear) {
                $programmes[$programmeYear->getProgramme()->getId()->toRfc4122()] = $programmeYear->getProgramme();
            }
        }

        $names = array_values(array_unique(array_map(static fn (Programme $p): string => $p->getName(), $programmes)));
        sort($names);

        return implode(' · ', $names);
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

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }
}
