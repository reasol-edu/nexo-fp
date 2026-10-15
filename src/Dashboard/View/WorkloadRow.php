<?php

declare(strict_types=1);

namespace App\Dashboard\View;

/** Reparto de tutorías de la persona en un grupo de una estancia con «Calcular asignaciones por docente». */
final readonly class WorkloadRow
{
    public function __construct(
        public string $stayId,
        public string $stayName,
        public string $groupName,
        public int $expected,
        public int $assigned,
    ) {}
}
