<?php

declare(strict_types=1);

namespace App\Dashboard\View;

/** Una empresa de la que la persona es docente de enlace, con sus puestos en estancias no finalizadas. */
final readonly class CompanyRow
{
    public function __construct(
        public string $companyId,
        public string $name,
        public bool $withoutWorkers,
        public int $freePositions,
        public int $assignedPositions,
        public ?\DateTimeImmutable $nextStart,
    ) {}
}
