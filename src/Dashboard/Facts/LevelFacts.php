<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

/** El grupo de un estudiante dentro de una enseñanza de la estancia. */
final readonly class LevelFacts
{
    public function __construct(
        public string $levelId,
        public string $programmeId,
        public string $groupId,
        public string $groupName,
    ) {}
}
