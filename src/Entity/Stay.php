<?php
namespace App\Entity;

use App\Repository\StayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StayRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_stay_name_year', columns: ['name', 'academic_year_id'])]
class Stay
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private AcademicYear $academicYear;

    /** @var Collection<int, Programme> Enseñanzas que participan en la estancia (al menos una). */
    #[ORM\ManyToMany(targetEntity: Programme::class)]
    #[ORM\JoinTable(
        name: 'stay_programme',
        // Una enseñanza que forma parte de una estancia no se puede borrar (como antes con stay.programme_id).
        inverseJoinColumns: [new ORM\JoinColumn(name: 'programme_id', referencedColumnName: 'id', onDelete: 'RESTRICT')],
    )]
    private Collection $programmes;

    /** @var Collection<int, Student> */
    #[ORM\ManyToMany(targetEntity: Student::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'stay_students')]
    private Collection $students;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSignatureReminderSentAt = null;

    /** @var Collection<int, TrainingPosition> */
    #[ORM\OneToMany(targetEntity: TrainingPosition::class, mappedBy: 'stay', fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $trainingPositions;

    public function __construct()
    {
        $this->programmes = new ArrayCollection();
        $this->students = new ArrayCollection();
        $this->trainingPositions = new ArrayCollection();
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

    public function getAcademicYear(): AcademicYear
    {
        return $this->academicYear;
    }

    public function setAcademicYear(AcademicYear $academicYear): static
    {
        $this->academicYear = $academicYear;

        return $this;
    }

    /**
     * @return Collection<int, Programme>
     */
    public function getProgrammes(): Collection
    {
        return $this->programmes;
    }

    public function addProgramme(Programme $programme): static
    {
        if (!$this->hasProgramme($programme)) {
            $this->programmes->add($programme);
        }

        return $this;
    }

    public function removeProgramme(Programme $programme): static
    {
        foreach ($this->programmes as $key => $existing) {
            if (self::sameProgramme($existing, $programme)) {
                $this->programmes->remove($key);
            }
        }

        return $this;
    }

    public function hasProgramme(Programme $programme): bool
    {
        foreach ($this->programmes as $existing) {
            if (self::sameProgramme($existing, $programme)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Misma enseñanza por identidad o, si ambas ya tienen id, por id. No exige que estén
     * persistidas: una enseñanza recién creada aún no tiene id.
     */
    private static function sameProgramme(Programme $a, Programme $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $id = new \ReflectionProperty(Programme::class, 'id');
        if (!$id->isInitialized($a) || !$id->isInitialized($b)) {
            return false;
        }

        return $a->getId()->equals($b->getId());
    }

    /**
     * Enseñanzas ordenadas por nombre, para mostrarlas siempre en el mismo orden.
     *
     * @return list<Programme>
     */
    public function getProgrammesSorted(): array
    {
        $list = $this->programmes->toArray();
        usort($list, static fn (Programme $a, Programme $b): int => $a->getName() <=> $b->getName());

        return $list;
    }

    /**
     * Enseñanzas de la estancia a las que pertenece el alumno según sus grupos. Un alumno
     * sin grupo en ninguna enseñanza de la estancia devuelve una lista vacía.
     *
     * @return list<Programme>
     */
    public function getProgrammesOfStudent(Student $student): array
    {
        $result = [];
        foreach ($student->getGroups() as $group) {
            $programme = $group->getProgrammeYear()->getProgramme();
            if ($this->hasProgramme($programme)) {
                $result[$programme->getId()->toRfc4122()] = $programme;
            }
        }

        return array_values($result);
    }

    /** Nombres de las enseñanzas separados por « · » (p. ej. «DAW · DAM»). */
    public function getProgrammeNames(): string
    {
        return implode(' · ', array_map(static fn (Programme $p): string => $p->getName(), $this->getProgrammesSorted()));
    }

    /**
     * Familias profesionales distintas de las enseñanzas de la estancia, ordenadas por nombre.
     *
     * @return list<ProfessionalFamily>
     */
    public function getProfessionalFamilies(): array
    {
        $families = [];
        foreach ($this->getProgrammesSorted() as $programme) {
            $family = $programme->getProfessionalFamily();
            $families[$family->getId()->toRfc4122()] = $family;
        }

        $list = array_values($families);
        usort($list, static fn (ProfessionalFamily $a, ProfessionalFamily $b): int => $a->getName() <=> $b->getName());

        return $list;
    }

    /** Nombres de las familias profesionales separados por « · ». */
    public function getFamilyNames(): string
    {
        return implode(' · ', array_map(static fn (ProfessionalFamily $f): string => $f->getName(), $this->getProfessionalFamilies()));
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(Student $student): static
    {
        if (!$this->students->contains($student)) {
            $this->students->add($student);
        }

        return $this;
    }

    public function removeStudent(Student $student): static
    {
        $this->students->removeElement($student);

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getLastSignatureReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->lastSignatureReminderSentAt;
    }

    public function setLastSignatureReminderSentAt(?\DateTimeImmutable $lastSignatureReminderSentAt): static
    {
        $this->lastSignatureReminderSentAt = $lastSignatureReminderSentAt;

        return $this;
    }

    /**
     * @return Collection<int, TrainingPosition>
     */
    public function getTrainingPositions(): Collection
    {
        return $this->trainingPositions;
    }
}
