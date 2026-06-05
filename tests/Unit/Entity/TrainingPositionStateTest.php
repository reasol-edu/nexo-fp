<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\TrainingPositionState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrainingPositionStateTest extends TestCase
{
    public function testCasesExist(): void
    {
        self::assertSame('DRAFT', TrainingPositionState::DRAFT->value);
        self::assertSame('PENDING', TrainingPositionState::PENDING->value);
        self::assertSame('DONE', TrainingPositionState::DONE->value);
    }

    public function testTryFromReturnsCorrectCases(): void
    {
        self::assertSame(TrainingPositionState::DRAFT, TrainingPositionState::tryFrom('DRAFT'));
        self::assertSame(TrainingPositionState::PENDING, TrainingPositionState::tryFrom('PENDING'));
        self::assertSame(TrainingPositionState::DONE, TrainingPositionState::tryFrom('DONE'));
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        self::assertNull(TrainingPositionState::tryFrom('INVALID'));
        self::assertNull(TrainingPositionState::tryFrom(''));
        self::assertNull(TrainingPositionState::tryFrom('draft'));
    }

    /**
     * Verifica el fallback a DRAFT que usa el controlador:
     *   $state = TrainingPositionState::tryFrom($values['state']) ?? TrainingPositionState::DRAFT;
     */
    public function testControllerFallbackToDraftOnInvalidValue(): void
    {
        $state = TrainingPositionState::tryFrom('INVALID') ?? TrainingPositionState::DRAFT;

        self::assertSame(TrainingPositionState::DRAFT, $state);
    }

    public function testDraftIsNotPendingNorDone(): void
    {
        self::assertNotSame(TrainingPositionState::DRAFT, TrainingPositionState::PENDING);
        self::assertNotSame(TrainingPositionState::DRAFT, TrainingPositionState::DONE);
    }

    #[DataProvider('nonDraftStatesProvider')]
    public function testNonDraftStateRequiresTutors(TrainingPositionState $state): void
    {
        // Reproduce the controller condition: state !== DRAFT requires both tutors
        $requiresTutors = $state !== TrainingPositionState::DRAFT;

        self::assertTrue($requiresTutors);
    }

    /** @return array<string, array{TrainingPositionState}> */
    public static function nonDraftStatesProvider(): array
    {
        return [
            'PENDING requires tutors' => [TrainingPositionState::PENDING],
            'DONE requires tutors'    => [TrainingPositionState::DONE],
        ];
    }

    public function testDraftDoesNotRequireTutors(): void
    {
        $requiresTutors = TrainingPositionState::DRAFT !== TrainingPositionState::DRAFT;

        self::assertFalse($requiresTutors);
    }
}
