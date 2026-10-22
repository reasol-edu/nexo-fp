<?php
namespace App\Entity;

use App\Repository\ProgrammeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProgrammeRepository::class)]
class Programme
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private AcademicYear $academicYear;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ProfessionalFamily $professionalFamily;

    /**
     * Niveles de la enseñanza (lado inverso). La coordinación dual y las estancias se asignan por nivel;
     * esta colección permite también hacerlo «a toda la enseñanza» de una vez.
     *
     * @var Collection<int, ProgrammeYear>
     */
    #[ORM\OneToMany(targetEntity: ProgrammeYear::class, mappedBy: 'programme')]
    private Collection $programmeYears;

    public function __construct()
    {
        $this->programmeYears = new ArrayCollection();
    }

    /**
     * @return Collection<int, ProgrammeYear>
     */
    public function getProgrammeYears(): Collection
    {
        return $this->programmeYears;
    }

    /** Lo llama {@see ProgrammeYear::setProgramme()} para mantener el lado inverso al día. */
    public function registerProgrammeYear(ProgrammeYear $programmeYear): void
    {
        if (!$this->programmeYears->contains($programmeYear)) {
            $this->programmeYears->add($programmeYear);
        }
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

    public function getAcademicYear(): AcademicYear
    {
        return $this->academicYear;
    }

    public function setAcademicYear(AcademicYear $academicYear): static
    {
        $this->academicYear = $academicYear;

        return $this;
    }

    public function getProfessionalFamily(): ProfessionalFamily
    {
        return $this->professionalFamily;
    }

    public function setProfessionalFamily(ProfessionalFamily $professionalFamily): static
    {
        $this->professionalFamily = $professionalFamily;

        return $this;
    }

    /**
     * Docentes que coordinan **todos** los niveles de la enseñanza: es lo que muestra y edita el panel de
     * la enseñanza (el «atajo» que asigna la coordinación a todos sus niveles de una vez). Quien coordina
     * solo algunos niveles no aparece aquí.
     *
     * @return list<Teacher>
     */
    public function getCoordinatorsOfAllLevels(): array
    {
        $levels = $this->programmeYears->toArray();
        if ($levels === []) {
            return [];
        }

        $common = $levels[0]->getCoordinators()->toArray();
        foreach (array_slice($levels, 1) as $level) {
            $common = array_filter($common, static fn (Teacher $t): bool => $level->getCoordinators()->contains($t));
        }

        return array_values($common);
    }

    /**
     * Docentes que coordinan **al menos un** nivel de la enseñanza, sin repetir. Es lo que hace falta para
     * comprobaciones generales (¿coordina algo de esta enseñanza?), no para decidir sobre un estudiante concreto.
     *
     * @return list<Teacher>
     */
    public function getCoordinatorsOfAnyLevel(): array
    {
        $result = [];
        foreach ($this->programmeYears as $level) {
            foreach ($level->getCoordinators() as $teacher) {
                $result[$teacher->getId()->toRfc4122()] = $teacher;
            }
        }

        return array_values($result);
    }

    /**
     * Asigna la coordinación a todos los niveles de la enseñanza. Una enseñanza sin niveles no puede
     * guardar coordinadores (la coordinación vive en el nivel).
     */
    public function addCoordinator(Teacher $coordinator): static
    {
        if ($this->programmeYears->isEmpty()) {
            throw new \LogicException('La enseñanza no tiene niveles: crea antes un nivel para asignar la coordinación dual.');
        }
        foreach ($this->programmeYears as $level) {
            $level->addCoordinator($coordinator);
        }

        return $this;
    }

    /** Quita la coordinación de todos los niveles de la enseñanza. */
    public function removeCoordinator(Teacher $coordinator): static
    {
        foreach ($this->programmeYears as $level) {
            $level->removeCoordinator($coordinator);
        }

        return $this;
    }
}
