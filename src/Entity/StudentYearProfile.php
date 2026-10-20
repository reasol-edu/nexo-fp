<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StudentYearProfileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Datos de un estudiante que solo valen para un curso académico: si dispone de NUSS, si realiza la
 * FFEOE, si tiene coche o aporta empresa, su actitud y las localidades de residencia y preferente.
 *
 * El estudiante es una sola ficha para toda su vida en el centro; esta es la ficha de cada curso, que
 * empieza vacía al llegar un curso nuevo. Se crea la primera vez que se necesita, no para todo el alumnado.
 */
#[ORM\Entity(repositoryClass: StudentYearProfileRepository::class)]
#[ORM\Table(name: 'student_year_profile')]
#[ORM\UniqueConstraint(name: 'uq_syp_student_year', columns: ['student_id', 'academic_year_id'])]
#[ORM\Index(columns: ['academic_year_id'], name: 'idx_syp_year')]
class StudentYearProfile
{
    /** Valores del filtro de las listas por ficha del estudiante. */
    public const FILTER_NO_NUSS  = 'sin-nuss';
    public const FILTER_NO_FFEOE = 'no-ffeoe';

    public const ATTITUDE_MIN = 1;
    public const ATTITUDE_MAX = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Student $student;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AcademicYear $academicYear;

    /** null = sin informar. Se rellena al importar (columna «NºSeg.Social») o a mano. */
    #[ORM\Column(nullable: true)]
    private ?bool $hasNuss = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $takesFfeoe = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $ffeoeExclusionReason = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasCar = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $bringsCompany = false;

    #[ORM\Column(nullable: true)]
    private ?string $broughtCompanyName = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $attitude = null;

    /** Se rellena al importar (columna «Localidad de residencia»). */
    #[ORM\Column(nullable: true)]
    private ?string $residenceCity = null;

    /** Dónde le gustaría hacer la FFEOE, si no es en su localidad de residencia. */
    #[ORM\Column(nullable: true)]
    private ?string $preferredCity = null;

    public function __construct(Student $student, AcademicYear $academicYear)
    {
        $this->student      = $student;
        $this->academicYear = $academicYear;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getStudent(): Student
    {
        return $this->student;
    }

    public function getAcademicYear(): AcademicYear
    {
        return $this->academicYear;
    }

    public function hasNuss(): ?bool
    {
        return $this->hasNuss;
    }

    public function setHasNuss(?bool $hasNuss): static
    {
        $this->hasNuss = $hasNuss;

        return $this;
    }

    public function takesFfeoe(): bool
    {
        return $this->takesFfeoe;
    }

    /** Al volver a realizar la FFEOE se descarta el motivo de exclusión. */
    public function setTakesFfeoe(bool $takesFfeoe): static
    {
        $this->takesFfeoe = $takesFfeoe;
        if ($takesFfeoe) {
            $this->ffeoeExclusionReason = null;
        }

        return $this;
    }

    public function getFfeoeExclusionReason(): ?string
    {
        return $this->ffeoeExclusionReason;
    }

    public function setFfeoeExclusionReason(?string $reason): static
    {
        $reason = $reason === null ? null : trim($reason);
        $this->ffeoeExclusionReason = $reason === '' ? null : $reason;

        return $this;
    }

    public function hasCar(): bool
    {
        return $this->hasCar;
    }

    public function setHasCar(bool $hasCar): static
    {
        $this->hasCar = $hasCar;

        return $this;
    }

    public function bringsCompany(): bool
    {
        return $this->bringsCompany;
    }

    /** Al dejar de aportar empresa se descarta su nombre. */
    public function setBringsCompany(bool $bringsCompany): static
    {
        $this->bringsCompany = $bringsCompany;
        if (!$bringsCompany) {
            $this->broughtCompanyName = null;
        }

        return $this;
    }

    public function getBroughtCompanyName(): ?string
    {
        return $this->broughtCompanyName;
    }

    public function setBroughtCompanyName(?string $name): static
    {
        $this->broughtCompanyName = self::clean($name);

        return $this;
    }

    public function getAttitude(): ?int
    {
        return $this->attitude;
    }

    public function setAttitude(?int $attitude): static
    {
        $this->attitude = $attitude !== null && $attitude >= self::ATTITUDE_MIN && $attitude <= self::ATTITUDE_MAX ? $attitude : null;

        return $this;
    }

    public function getResidenceCity(): ?string
    {
        return $this->residenceCity;
    }

    public function setResidenceCity(?string $city): static
    {
        $this->residenceCity = self::clean($city);

        return $this;
    }

    public function getPreferredCity(): ?string
    {
        return $this->preferredCity;
    }

    public function setPreferredCity(?string $city): static
    {
        $this->preferredCity = self::clean($city);

        return $this;
    }

    /** Realiza la FFEOE pero no consta que tenga NUSS (NO confirmado): no podrá hacer la estancia. */
    public function isMissingNuss(): bool
    {
        return $this->takesFfeoe && $this->hasNuss === false;
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : $value;
    }
}
