<?php

declare(strict_types=1);

namespace App\Service;

final class ImportOptions
{
    public function __construct(
        public readonly bool $importHeads = false,
        public readonly bool $importTutors = false,
        public readonly bool $importTeachers = false,
    ) {}
}
