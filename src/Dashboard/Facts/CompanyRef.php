<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

final readonly class CompanyRef
{
    public function __construct(
        public string $id,
        public string $name,
        /** Sin trabajadores registrados: no se les puede asignar tutor de empresa. */
        public bool $withoutWorkers,
    ) {}
}
