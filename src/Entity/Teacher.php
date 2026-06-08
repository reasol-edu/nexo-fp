<?php
namespace App\Entity;

use App\Repository\TeacherRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeacherRepository::class)]
class Teacher implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Embedded(class: PersonName::class)]
    private PersonName $name;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 180)]
    private string $username;

    #[ORM\Column]
    private bool $admin = false;

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    private bool $external = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    /** @var Collection<int, AcademicYear> */
    #[ORM\ManyToMany(targetEntity: AcademicYear::class, mappedBy: 'teachers', fetch: 'EXTRA_LAZY')]
    private Collection $academicYears;

    public function __construct(PersonName $name)
    {
        $this->name   = $name;
        $this->academicYears = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): PersonName
    {
        return $this->name;
    }

    public function setName(PersonName $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_TEACHER'];
        if ($this->admin) {
            $roles[] = 'ROLE_ADMIN';
        }

        return $roles;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function setAdmin(bool $admin): static
    {
        $this->admin = $admin;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void {}

    public function isExternal(): bool
    {
        return $this->external;
    }

    public function setExternal(bool $external): static
    {
        $this->external = $external;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /** @return Collection<int, AcademicYear> */
    public function getAcademicYears(): Collection
    {
        return $this->academicYears;
    }

    public function addAcademicYear(AcademicYear $year): static
    {
        if (!$this->academicYears->contains($year)) {
            $this->academicYears->add($year);
            $year->addTeacher($this);
        }

        return $this;
    }

    public function removeAcademicYear(AcademicYear $year): static
    {
        if ($this->academicYears->removeElement($year)) {
            $year->removeTeacher($this);
        }

        return $this;
    }
}
