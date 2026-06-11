<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GlobalSettingValueRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: GlobalSettingValueRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_global_setting_definition', columns: ['definition_id'])]
class GlobalSettingValue
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator('doctrine.uuid_generator')]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private SettingDefinition $definition;

    #[ORM\Column(length: 255)]
    private string $value;

    #[ORM\Column]
    private bool $locked = false;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDefinition(): SettingDefinition
    {
        return $this->definition;
    }

    public function setDefinition(SettingDefinition $definition): static
    {
        $this->definition = $definition;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): static
    {
        $this->locked = $locked;

        return $this;
    }
}
