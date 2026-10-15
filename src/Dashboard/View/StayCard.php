<?php

declare(strict_types=1);

namespace App\Dashboard\View;

use App\Dashboard\Severity;

/** Una estancia no finalizada con su progreso, para las tarjetas (o la tabla) de «Mis estancias». */
final readonly class StayCard
{
    /**
     * @param list<array{key: string, done: int, total: int}>   $steps el embudo de la estancia
     * @param array{key: string, filter: string|null, count: int} $next  siguiente paso recomendado
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $programmeNames,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public int $daysToStart,
        public int $daysToEnd,
        public bool $current,
        /** Porcentaje del periodo transcurrido (0–100); 0 si aún no ha empezado. */
        public int $elapsedPercent,
        public array $steps,
        public array $next,
        public ?Severity $worst,
        public int $students,
    ) {}
}
