<?php
namespace App\Entity;

use App\Repository\EducationalCentreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EducationalCentreRepository::class)]
class EducationalCentre
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private readonly Uuid $id;

    #[ORM\Column(length: 8, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\ManyToOne]
    private ?AcademicYear $activeAcademicYear = null;

    #[ORM\ManyToMany(targetEntity: Teacher::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'educational_centre_admins')]
    private Collection $admins;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->admins = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getActiveAcademicYear(): ?AcademicYear
    {
        return $this->activeAcademicYear;
    }

    public function requireActiveAcademicYear(): AcademicYear
    {
        return $this->activeAcademicYear
            ?? throw new \LogicException('This educational centre has no active academic year.');
    }

    public function setActiveAcademicYear(?AcademicYear $academicYear): static
    {
        if ($academicYear !== null && $academicYear->getEducationalCentre() !== $this) {
            throw new \LogicException('The academic year does not belong to this educational centre.');
        }

        $this->activeAcademicYear = $academicYear;

        return $this;
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function getAdmins(): Collection
    {
        return $this->admins;
    }

    public function addAdmin(Teacher $admin): static
    {
        if (!$this->admins->contains($admin)) {
            $this->admins->add($admin);
        }

        return $this;
    }

    public function removeAdmin(Teacher $admin): static
    {
        $this->admins->removeElement($admin);

        return $this;
    }
}
