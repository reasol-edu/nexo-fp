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

    /**
     * Niveles que participan en la estancia (al menos uno), de una o varias enseñanzas: la estancia puede
     * incluir solo 2.º de una enseñanza. Las enseñanzas de la estancia se derivan de sus niveles.
     *
     * @var Collection<int, ProgrammeYear>
     */
    #[ORM\ManyToMany(targetEntity: ProgrammeYear::class)]
    #[ORM\JoinTable(
        name: 'stay_programme_year',
        // Un nivel que forma parte de una estancia no se puede borrar.
        inverseJoinColumns: [new ORM\JoinColumn(name: 'programme_year_id', referencedColumnName: 'id', onDelete: 'RESTRICT')],
    )]
    private Collection $programmeYears;

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

    /**
     * Si está activo, el detalle de la estancia permite indicar las horas dualizadas de cada docente en
     * cada grupo y calcula cuántos estudiantes debería tutorizar cada uno (ver {@see StayTeacherHours}).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $calculateByTeacher = false;

    /** @var Collection<int, TrainingPosition> */
    #[ORM\OneToMany(targetEntity: TrainingPosition::class, mappedBy: 'stay', fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $trainingPositions;

    public function __construct()
    {
        $this->programmeYears = new ArrayCollection();
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
     * @return Collection<int, ProgrammeYear>
     */
    public function getProgrammeYears(): Collection
    {
        return $this->programmeYears;
    }

    public function addProgrammeYear(ProgrammeYear $programmeYear): static
    {
        if (!$this->hasProgrammeYear($programmeYear)) {
            $this->programmeYears->add($programmeYear);
        }

        return $this;
    }

    public function removeProgrammeYear(ProgrammeYear $programmeYear): static
    {
        foreach ($this->programmeYears as $key => $existing) {
            if (self::sameEntity(ProgrammeYear::class, $existing, $programmeYear)) {
                $this->programmeYears->remove($key);
            }
        }

        return $this;
    }

    public function hasProgrammeYear(ProgrammeYear $programmeYear): bool
    {
        foreach ($this->programmeYears as $existing) {
            if (self::sameEntity(ProgrammeYear::class, $existing, $programmeYear)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enseñanzas de la estancia: las de sus niveles, sin repetir, en el orden en que aparecen.
     *
     * @return Collection<int, Programme>
     */
    public function getProgrammes(): Collection
    {
        $result = [];
        foreach ($this->programmeYears as $programmeYear) {
            $programme = $programmeYear->getProgramme();
            $result[self::key($programme)] = $programme;
        }

        return new ArrayCollection(array_values($result));
    }

    /** Añade **todos los niveles** de la enseñanza (atajo para «la enseñanza completa»). */
    public function addProgramme(Programme $programme): static
    {
        foreach ($programme->getProgrammeYears() as $programmeYear) {
            $this->addProgrammeYear($programmeYear);
        }

        return $this;
    }

    /** Quita **todos los niveles** de la enseñanza de la estancia. */
    public function removeProgramme(Programme $programme): static
    {
        foreach ($this->programmeYears->toArray() as $existing) {
            if (self::sameEntity(Programme::class, $existing->getProgramme(), $programme)) {
                $this->removeProgrammeYear($existing);
            }
        }

        return $this;
    }

    /** ¿Participa algún nivel de esa enseñanza en la estancia? */
    public function hasProgramme(Programme $programme): bool
    {
        foreach ($this->programmeYears as $existing) {
            if (self::sameEntity(Programme::class, $existing->getProgramme(), $programme)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Misma entidad por identidad o, si ambas ya tienen id, por id. No exige que estén persistidas:
     * una entidad recién creada aún no tiene id.
     *
     * @param class-string<Programme|ProgrammeYear> $class
     */
    private static function sameEntity(string $class, Programme|ProgrammeYear $a, Programme|ProgrammeYear $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $id = new \ReflectionProperty($class, 'id');
        if (!$id->isInitialized($a) || !$id->isInitialized($b)) {
            return false;
        }

        return $a->getId()->equals($b->getId());
    }

    /** Clave para deduplicar entidades con o sin id todavía. */
    private static function key(Programme|ProgrammeYear $entity): string
    {
        $id = new \ReflectionProperty($entity::class, 'id');

        return $id->isInitialized($entity) ? $entity->getId()->toRfc4122() : 'new-' . spl_object_id($entity);
    }

    /**
     * Enseñanzas ordenadas por nombre, para mostrarlas siempre en el mismo orden.
     *
     * @return list<Programme>
     */
    public function getProgrammesSorted(): array
    {
        $list = $this->getProgrammes()->toArray();
        usort($list, static fn (Programme $a, Programme $b): int => $a->getName() <=> $b->getName());

        return $list;
    }

    /**
     * Niveles de la estancia a los que pertenece el alumno según sus grupos. Un alumno sin grupo en
     * ningún nivel de la estancia devuelve una lista vacía.
     *
     * @return list<ProgrammeYear>
     */
    public function getProgrammeYearsOfStudent(Student $student): array
    {
        $result = [];
        foreach ($student->getGroups() as $group) {
            $programmeYear = $group->getProgrammeYear();
            if ($this->hasProgrammeYear($programmeYear)) {
                $result[self::key($programmeYear)] = $programmeYear;
            }
        }

        return array_values($result);
    }

    /**
     * Enseñanzas de la estancia a las que pertenece el alumno según sus grupos. Un alumno
     * sin grupo en ningún nivel de la estancia devuelve una lista vacía.
     *
     * @return list<Programme>
     */
    public function getProgrammesOfStudent(Student $student): array
    {
        $result = [];
        foreach ($this->getProgrammeYearsOfStudent($student) as $programmeYear) {
            $programme = $programmeYear->getProgramme();
            $result[self::key($programme)] = $programme;
        }

        return array_values($result);
    }

    /**
     * Niveles ordenados por enseñanza y nombre, para mostrarlos siempre en el mismo orden.
     *
     * @return list<ProgrammeYear>
     */
    public function getProgrammeYearsSorted(): array
    {
        $list = $this->programmeYears->toArray();
        usort($list, static fn (ProgrammeYear $a, ProgrammeYear $b): int => [$a->getProgramme()->getName(), $a->getName()] <=> [$b->getProgramme()->getName(), $b->getName()]);

        return $list;
    }

    /**
     * Qué enseñanzas y niveles reúne la estancia, agrupado por enseñanza: «2.º DAW» si de una enseñanza solo
     * participa un nivel, «DAW (1.º y 2.º)» si participan varios, separadas por « · ».
     */
    public function getLevelSummary(): string
    {
        /** @var array<string, list<ProgrammeYear>> $byProgramme */
        $byProgramme = [];
        foreach ($this->getProgrammeYearsSorted() as $level) {
            $byProgramme[$level->getProgramme()->getName()][] = $level;
        }

        $parts = [];
        foreach ($byProgramme as $programmeName => $levels) {
            if (\count($levels) === 1) {
                $level   = $levels[0];
                $parts[] = stripos($level->getName(), $programmeName) !== false ? $level->getName() : $programmeName . ' (' . $level->getShortName() . ')';
                continue;
            }

            $names   = array_map(static fn (ProgrammeYear $l): string => $l->getShortName(), $levels);
            $last    = array_pop($names);
            $parts[] = $programmeName . ' (' . implode(', ', $names) . ' y ' . $last . ')';
        }

        return implode(' · ', $parts);
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

    public function isCalculateByTeacher(): bool
    {
        return $this->calculateByTeacher;
    }

    public function setCalculateByTeacher(bool $calculateByTeacher): static
    {
        $this->calculateByTeacher = $calculateByTeacher;

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
