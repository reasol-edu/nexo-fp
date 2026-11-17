<?php

declare(strict_types=1);

namespace App\Service\Backup;

/**
 * Cuántas copias automáticas se conservan: la más reciente de cada uno de los últimos N días, de las últimas N semanas y de
 * los últimos N meses **que tengan copia**. La copia nocturna es la única que se hace; las más antiguas son simplemente las
 * que sobreviven a la poda, así que una política nunca cuesta más de diarias + semanales + mensuales ficheros.
 */
final readonly class BackupRetentionPolicy
{
    public function __construct(
        public int $daily,
        public int $weekly,
        public int $monthly,
    ) {}

    /** @return array{daily: int, weekly: int, monthly: int} */
    public function tiers(): array
    {
        return ['daily' => max(0, $this->daily), 'weekly' => max(0, $this->weekly), 'monthly' => max(0, $this->monthly)];
    }
}
