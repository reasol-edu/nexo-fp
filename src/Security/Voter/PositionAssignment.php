<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Student;
use App\Entity\TrainingPosition;

/**
 * Sujeto de {@see StayVoter::ASSIGN}: la intención de asignar un puesto concreto a un estudiante.
 */
final readonly class PositionAssignment
{
    public function __construct(
        public TrainingPosition $position,
        public Student $student,
    ) {}
}
