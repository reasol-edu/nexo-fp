<?php
namespace App\Entity;

use App\Repository\StayRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StayRepository::class)]
class Stay
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private AcademicYear $academicYear;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Programme $programme;

    /** @var Collection<int, Student> */
    #[ORM\ManyToMany(targetEntity: Student::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'stay_students')]
    private Collection $students;

    /** @var Collection<int, TrainingPosition> */
    #[ORM\OneToMany(targetEntity: TrainingPosition::class, mappedBy: 'stay', fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $trainingPositions;

    public function __construct()
    {
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

    public function getProgramme(): Programme
    {
        return $this->programme;
    }

    public function setProgramme(Programme $programme): static
    {
        $this->programme = $programme;

        return $this;
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

    /**
     * @return Collection<int, TrainingPosition>
     */
    public function getTrainingPositions(): Collection
    {
        return $this->trainingPositions;
    }
}
