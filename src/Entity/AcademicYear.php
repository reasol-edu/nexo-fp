<?php
namespace App\Entity;

use App\Repository\AcademicYearRepository;
use Doctrine\ORM\Mapping as ORM;
use Rcsofttech\AuditTrailBundle\Attribute\Auditable;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AcademicYearRepository::class)]
#[Auditable]
class AcademicYear
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $name = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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
}
