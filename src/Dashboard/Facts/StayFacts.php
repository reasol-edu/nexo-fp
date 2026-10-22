<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

final readonly class StayFacts
{
    /**
     * @param list<ProgrammeRef> $programmes enseñanzas de la estancia (las de sus niveles)
     * @param list<string>       $levelIds   niveles que participan en la estancia
     * @param array<string, string> $levelLabels nombre de cada nivel (id → «DAW · 2.º»), para los avisos
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $programmes,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $calculateByTeacher,
        public array $levelIds = [],
        public array $levelLabels = [],
    ) {}

    /** @return list<string> */
    public function programmeIds(): array
    {
        return array_map(static fn (ProgrammeRef $p): string => $p->id, $this->programmes);
    }

    public function programmeNames(): string
    {
        return implode(' · ', array_map(static fn (ProgrammeRef $p): string => $p->name, $this->programmes));
    }

    public function isFinished(\DateTimeImmutable $today): bool
    {
        return $this->end < $today;
    }

    /** Días hasta el inicio; negativo si ya empezó. */
    public function daysToStart(\DateTimeImmutable $today): int
    {
        return (int) $today->diff($this->start)->format('%r%a');
    }

    public function daysToEnd(\DateTimeImmutable $today): int
    {
        return (int) $today->diff($this->end)->format('%r%a');
    }
}
