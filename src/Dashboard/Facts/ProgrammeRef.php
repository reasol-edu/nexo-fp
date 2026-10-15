<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

/** Una enseñanza de una estancia, con su familia profesional y quién la dirige. */
final readonly class ProgrammeRef
{
    public function __construct(
        public string $id,
        public string $name,
        public string $familyName,
        public ?string $familyHeadId,
    ) {}
}
