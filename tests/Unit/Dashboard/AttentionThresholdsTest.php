<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\AttentionThresholds;
use App\Dashboard\Severity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AttentionThresholdsTest extends TestCase
{
    /** @return iterable<string, array{int, Severity}> */
    public static function severities(): iterable
    {
        yield 'ya empezó hace mucho'      => [-46, Severity::Critical];
        yield 'empieza hoy'               => [0, Severity::Critical];
        yield 'en el límite de urgente'   => [7, Severity::Critical];
        yield 'justo después de urgente'  => [8, Severity::Soon];
        yield 'en el límite de próximo'   => [21, Severity::Soon];
        yield 'justo después de próximo'  => [22, Severity::Info];
        yield 'dentro de dos meses'       => [60, Severity::Info];
    }

    #[DataProvider('severities')]
    public function testSeverityDependsOnTheDaysToStart(int $days, Severity $expected): void
    {
        self::assertSame($expected, (new AttentionThresholds())->severityFor($days));
    }

    public function testTheThresholdsAreConfigurable(): void
    {
        $thresholds = new AttentionThresholds(3, 10);

        self::assertSame(Severity::Critical, $thresholds->severityFor(3));
        self::assertSame(Severity::Soon, $thresholds->severityFor(4));
        self::assertSame(Severity::Soon, $thresholds->severityFor(10));
        self::assertSame(Severity::Info, $thresholds->severityFor(11));
    }

    public function testSoonIsAlwaysLongerThanUrgentEvenIfTheSettingsContradictEachOther(): void
    {
        $thresholds = new AttentionThresholds(14, 7);

        self::assertSame(14, $thresholds->urgentDays);
        self::assertSame(15, $thresholds->soonDays);
    }

    public function testDefaultsAreUsedWhenTheSettingsAreMissingOrInvalid(): void
    {
        foreach ([[null, null], ['x', []], [0, -3]] as [$urgent, $soon]) {
            $thresholds = AttentionThresholds::fromSettings($urgent, $soon);

            self::assertSame(7, $thresholds->urgentDays);
            self::assertSame(21, $thresholds->soonDays);
        }
    }

    public function testSettingsValuesAreUsedWhenValid(): void
    {
        $thresholds = AttentionThresholds::fromSettings(5, 30);

        self::assertSame(5, $thresholds->urgentDays);
        self::assertSame(30, $thresholds->soonDays);
    }

    public function testMoreUrgentKeepsTheMostUrgentSeverity(): void
    {
        self::assertSame(Severity::Critical, Severity::Info->moreUrgent(Severity::Critical));
        self::assertSame(Severity::Critical, Severity::Critical->moreUrgent(Severity::Soon));
        self::assertSame(Severity::Soon, Severity::Info->moreUrgent(Severity::Soon));
    }
}
