<?php

declare(strict_types=1);

namespace App\Dashboard\View;

/** Un grupo de la persona con sus estudiantes en estancias no finalizadas. */
final readonly class GroupRow
{
    /** @param list<string> $withoutPositionIds estudiantes sin puesto (se muestran solo los primeros) */
    public function __construct(
        public string $groupId,
        public string $name,
        public int $students,
        public int $withPosition,
        public array $withoutPositionIds,
        public int $withoutPositionTotal,
        /** Primera estancia en la que hay estudiantes del grupo sin puesto, para el enlace. */
        public ?string $stayId,
    ) {}
}
