<?php

declare(strict_types=1);

namespace App\Dashboard;

/** Gravedad de una tarea del inicio. Cuanto menor el rango, más urgente. */
enum Severity: string
{
    case Critical = 'critical';
    case Soon     = 'soon';
    case Info     = 'info';

    public function rank(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Soon     => 1,
            self::Info     => 2,
        };
    }

    /** La más urgente de las dos. */
    public function moreUrgent(self $other): self
    {
        return $other->rank() < $this->rank() ? $other : $this;
    }
}
