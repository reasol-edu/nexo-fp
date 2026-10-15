<?php

declare(strict_types=1);

namespace App\Dashboard\Facts;

/**
 * Todo lo que el inicio necesita del curso, cargado con un número fijo de consultas, sea cual sea el
 * número de estancias: el resto del cálculo se hace en memoria sobre estos datos.
 */
final readonly class DashboardFacts
{
    /**
     * @param list<StayFacts>                                  $stays     estancias del curso que la persona puede ver
     * @param list<PositionFacts>                              $positions puestos de esas estancias
     * @param array<string, array<string, StudentFacts>>       $students  estancia → estudiante → datos
     * @param array<string, array<string, array<string, int>>> $hours     estancia → grupo → docente → horas dualizadas
     */
    public function __construct(
        public \DateTimeImmutable $today,
        public ScopeFacts $scope,
        public array $stays,
        public array $positions,
        public array $students,
        public array $hours = [],
    ) {}
}
