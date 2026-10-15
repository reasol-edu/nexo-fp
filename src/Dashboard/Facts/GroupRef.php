<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

final readonly class GroupRef
{
    public function __construct(
        public string $id,
        public string $name,
        public string $programmeId,
    ) {}
}
