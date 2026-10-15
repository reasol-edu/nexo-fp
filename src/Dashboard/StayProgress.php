<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\TrainingPositionState;

/**
 * Avance de una estancia: estudiantes con puesto, con ambos tutores, registrados en Séneca y firmados,
 * y el siguiente paso recomendado. Lo comparten el detalle de la estancia y las tarjetas del inicio,
 * para que ambos digan siempre lo mismo.
 */
final class StayProgress
{
    /**
     * @param int                                                                                  $students           estudiantes en cuyo avance se cuenta
     * @param int                                                                                  $positionsTotal     puestos de la estancia (con o sin estudiante)
     * @param list<array{state: TrainingPositionState, signed: bool, hasAcademic: bool, hasMentor: bool}> $assigned puestos con estudiante
     *
     * @return array{
     *     steps: list<array{key: string, done: int, total: int}>,
     *     next: array{key: string, filter: string|null, count: int}
     * }
     */
    public static function calculate(int $students, int $positionsTotal, array $assigned, bool $canManage, bool $canViewUnassigned): array
    {
        $withTutors      = 0;
        $missingAcademic = 0;
        $registered      = 0;
        $signed          = 0;
        $readyToSend     = 0;
        $awaitingSeneca  = 0;

        foreach ($assigned as $position) {
            $tutors = $position['hasAcademic'] && $position['hasMentor'];
            if ($tutors) {
                ++$withTutors;
            }
            if (!$position['hasAcademic']) {
                ++$missingAcademic;
            }
            if ($position['state'] === TrainingPositionState::DONE) {
                ++$registered;
                if ($position['signed']) {
                    ++$signed;
                }
            } elseif ($position['state'] === TrainingPositionState::PENDING) {
                ++$awaitingSeneca;
            } elseif ($tutors) {
                ++$readyToSend;
            }
        }
        $withPosition = \count($assigned);

        $steps = [
            ['key' => 'with_position', 'done' => $withPosition, 'total' => $students],
            ['key' => 'with_tutors', 'done' => $withTutors, 'total' => $withPosition],
            ['key' => 'registered', 'done' => $registered, 'total' => $withPosition],
            ['key' => 'signed', 'done' => $signed, 'total' => $withPosition],
        ];

        $next = match (true) {
            $students === 0                                           => ['key' => 'enrol', 'filter' => null, 'count' => 0],
            $positionsTotal === 0 && $canViewUnassigned && $canManage => ['key' => 'create_positions', 'filter' => null, 'count' => 0],
            $withPosition < $students                                 => ['key' => 'assign', 'filter' => 'sin-puesto', 'count' => $students - $withPosition],
            $withTutors < $withPosition                               => ['key' => 'tutors', 'filter' => $missingAcademic > 0 ? 'sin-tutor' : 'sin-empresa', 'count' => $withPosition - $withTutors],
            $readyToSend > 0                                          => ['key' => 'send', 'filter' => null, 'count' => $readyToSend],
            $awaitingSeneca > 0                                       => ['key' => 'register', 'filter' => null, 'count' => $awaitingSeneca],
            $signed < $withPosition                                   => ['key' => 'sign', 'filter' => 'sin-firmar', 'count' => $withPosition - $signed],
            default                                                   => ['key' => 'complete', 'filter' => null, 'count' => 0],
        };

        return ['steps' => $steps, 'next' => $next];
    }
}
