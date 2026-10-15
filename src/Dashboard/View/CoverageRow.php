<?php

declare(strict_types=1);

namespace App\Dashboard\View;

use App\Dashboard\Severity;

/** Cobertura de una enseñanza en las estancias no finalizadas (jefatura y dirección). */
final readonly class CoverageRow
{
    public function __construct(
        public string $programmeId,
        public string $programmeName,
        public string $familyName,
        public int $students,
        public int $withoutPosition,
        public int $bothTutors,
        public int $registered,
        public int $signed,
        /** Gravedad de lo más urgente que queda por resolver en la enseñanza; null si está al día. */
        public ?Severity $risk,
    ) {}
}
