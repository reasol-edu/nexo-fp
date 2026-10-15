<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

final readonly class StayFacts
{
    /** @param list<ProgrammeRef> $programmes */
    public function __construct(
        public string $id,
        public string $name,
        public array $programmes,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public bool $calculateByTeacher,
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
