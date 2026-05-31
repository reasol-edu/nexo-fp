<?php
namespace App\Entity;

use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: '`group`')]
class Group
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ProgrammeYear $programmeYear;

    /** @var Collection<int, Teacher> */
    #[ORM\ManyToMany(targetEntity: Teacher::class, fetch: 'EXTRA_LAZY')]
    private Collection $teachers;

    #[ORM\ManyToOne]
    private ?Teacher $tutor = null;

    /** @var Collection<int, Student> */
    #[ORM\ManyToMany(targetEntity: Student::class, mappedBy: 'groups', fetch: 'EXTRA_LAZY')]
    private Collection $students;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->teachers = new ArrayCollection();
        $this->students = new ArrayCollection();
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

    public function getProgrammeYear(): ProgrammeYear
    {
        return $this->programmeYear;
    }

    public function setProgrammeYear(ProgrammeYear $programmeYear): static
    {
        $this->programmeYear = $programmeYear;

        return $this;
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function getTeachers(): Collection
    {
        return $this->teachers;
    }

    public function addTeacher(Teacher $teacher): static
    {
        if (!$this->teachers->contains($teacher)) {
            $this->teachers->add($teacher);
        }

        return $this;
    }

    public function removeTeacher(Teacher $teacher): static
    {
        $this->teachers->removeElement($teacher);

        return $this;
    }

    public function getTutor(): ?Teacher
    {
        return $this->tutor;
    }

    public function setTutor(?Teacher $tutor): static
    {
        $this->tutor = $tutor;

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
            $student->addGroup($this);
        }

        return $this;
    }

    public function removeStudent(Student $student): static
    {
        if ($this->students->removeElement($student)) {
            $student->removeGroup($this);
        }

        return $this;
    }
}
