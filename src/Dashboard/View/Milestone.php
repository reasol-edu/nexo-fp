<?php

declare(strict_types=1);

namespace App\Dashboard\View;

/** Un hito con fecha de los próximos días. */
final readonly class Milestone
{
    public const STAY_STARTS      = 'stay_starts';
    public const STAY_ENDS        = 'stay_ends';
    public const SIGNATURE_DUE    = 'signature_due';
    public const PRIORITY_ENDS    = 'priority_ends';
    public const POSITIONS_START  = 'positions_start';

    public function __construct(
        public \DateTimeImmutable $date,
        public string $key,
        public string $stayId,
        public string $stayName,
        public int $count = 0,
        /** Enseñanza a la que se refiere (preferencias de puestos compartidos). */
        public ?string $subject = null,
    ) {}
}
