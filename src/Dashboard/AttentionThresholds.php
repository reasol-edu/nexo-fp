<?php

declare(strict_types=1);

namespace App\Dashboard;

/**
 * Umbrales de urgencia en días hasta el inicio de una estancia: hasta {@see $urgentDays} (o si ya
 * empezó) es crítico, hasta {@see $soonDays} es próximo y a partir de ahí es informativo.
 * Son ajustes de plataforma y de centro («attention.urgent_days» y «attention.soon_days»).
 */
final readonly class AttentionThresholds
{
    public const DEFAULT_URGENT_DAYS = 7;
    public const DEFAULT_SOON_DAYS   = 21;

    public int $urgentDays;
    public int $soonDays;

    public function __construct(int $urgentDays = self::DEFAULT_URGENT_DAYS, int $soonDays = self::DEFAULT_SOON_DAYS)
    {
        $this->urgentDays = max(1, $urgentDays);
        // El plazo de «próximo» siempre es mayor que el de «urgente», aunque los ajustes se contradigan.
        $this->soonDays = max($this->urgentDays + 1, $soonDays);
    }

    /** Construye los umbrales con los valores de los ajustes (que pueden no estar definidos). */
    public static function fromSettings(mixed $urgent, mixed $soon): self
    {
        return new self(
            \is_int($urgent) && $urgent > 0 ? $urgent : self::DEFAULT_URGENT_DAYS,
            \is_int($soon) && $soon > 0 ? $soon : self::DEFAULT_SOON_DAYS,
        );
    }

    /** @param int $daysToStart días hasta el inicio; negativo si la estancia ya empezó */
    public function severityFor(int $daysToStart): Severity
    {
        return match (true) {
            $daysToStart <= $this->urgentDays => Severity::Critical,
            $daysToStart <= $this->soonDays   => Severity::Soon,
            default                           => Severity::Info,
        };
    }
}
