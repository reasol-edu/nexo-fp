<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

use App\Entity\TrainingPositionState;

/** Un puesto formativo reducido a lo que necesita el inicio (sin entidades ni cargas perezosas). */
final readonly class PositionFacts
{
    /** @param list<string> $levelIds niveles a los que se oferta (solo se cargan en los puestos libres) */
    public function __construct(
        public string $id,
        public string $stayId,
        public TrainingPositionState $state,
        public bool $signed,
        public ?string $studentId,
        public ?string $tutorId,
        public ?string $mentorId,
        public ?string $companyId,
        public ?string $workcenterId,
        public ?\DateTimeImmutable $start,
        public ?\DateTimeImmutable $end,
        public ?\DateTimeImmutable $priorityUntil,
        public ?string $priorityLevelId,
        public array $levelIds = [],
    ) {}

    public function isFree(): bool
    {
        return $this->studentId === null;
    }

    public function hasBothTutors(): bool
    {
        return $this->tutorId !== null && $this->mentorId !== null;
    }

    public function isRegistered(): bool
    {
        return $this->state === TrainingPositionState::DONE;
    }
}
