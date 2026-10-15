<?php

declare(strict_types=1);

namespace App\Dashboard\View;

use App\Dashboard\Severity;
use App\Entity\TrainingPositionState;

/** Un puesto que tutoriza la persona (tutor/a dual docente), en una estancia no finalizada. */
final readonly class TutorshipRow
{
    public const ACTION_COMPLETE_TUTORS = 'complete_tutors';
    public const ACTION_SEND            = 'send';
    public const ACTION_REGISTER        = 'register';
    public const ACTION_SIGN            = 'sign';
    public const ACTION_DONE            = 'done';

    public function __construct(
        public string $positionId,
        public string $stayId,
        public string $stayName,
        public string $studentId,
        public ?string $workcenterId,
        public TrainingPositionState $state,
        public bool $signed,
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public int $daysToStart,
        public string $action,
        public ?Severity $severity,
    ) {}
}
