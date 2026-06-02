<?php
namespace App\Entity;

use App\Repository\CompanyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CompanyRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_company_vat_centre', columns: ['vat_number', 'educational_centre_id'])]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $vatNumber;

    #[ORM\Column(length: 255)]
    private string $city;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $exceptionalCircumstances = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private EducationalCentre $educationalCentre;

    /** @var Collection<int, Teacher> */
    #[ORM\ManyToMany(targetEntity: Teacher::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'company_liaisons')]
    private Collection $liaisons;

    /** @var Collection<int, Worker> */
    #[ORM\ManyToMany(targetEntity: Worker::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'company_workers')]
    private Collection $workers;

    public function __construct()
    {
        $this->liaisons = new ArrayCollection();
        $this->workers  = new ArrayCollection();
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

    public function getVatNumber(): string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getExceptionalCircumstances(): ?string
    {
        return $this->exceptionalCircumstances;
    }

    public function setExceptionalCircumstances(?string $exceptionalCircumstances): static
    {
        $this->exceptionalCircumstances = $exceptionalCircumstances;

        return $this;
    }

    public function getEducationalCentre(): EducationalCentre
    {
        return $this->educationalCentre;
    }

    public function setEducationalCentre(EducationalCentre $educationalCentre): static
    {
        $this->educationalCentre = $educationalCentre;

        return $this;
    }

    /**
     * @return Collection<int, Teacher>
     */
    public function getLiaisons(): Collection
    {
        return $this->liaisons;
    }

    public function addLiaison(Teacher $liaison): static
    {
        if (!$this->liaisons->contains($liaison)) {
            $this->liaisons->add($liaison);
        }

        return $this;
    }

    public function removeLiaison(Teacher $liaison): static
    {
        $this->liaisons->removeElement($liaison);

        return $this;
    }

    /**
     * @return Collection<int, Worker>
     */
    public function getWorkers(): Collection
    {
        return $this->workers;
    }

    public function addWorker(Worker $worker): static
    {
        if (!$this->workers->contains($worker)) {
            $this->workers->add($worker);
        }

        return $this;
    }

    public function removeWorker(Worker $worker): static
    {
        $this->workers->removeElement($worker);

        return $this;
    }
}
