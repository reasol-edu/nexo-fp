<?php

declare(strict_types=1);

namespace App\Dashboard\View;

use App\Dashboard\AttentionThresholds;
use App\Dashboard\Facts\ScopeFacts;

/** Todo lo que muestra el inicio, ya calculado y ordenado por prioridad. */
final readonly class DashboardView
{
    /**
     * @param list<Task>                                              $tasks        «Atención ahora», ordenadas por prioridad
     * @param list<Milestone>                                         $milestones   hitos de los próximos días
     * @param list<StayCard>                                          $stays        estancias no finalizadas del ámbito de gestión
     * @param list<array{key: string, done: int, total: int}>         $funnel       embudo agregado de las estancias de $funnelMode
     * @param list<CoverageRow>                                       $coverage     cobertura por enseñanza (jefatura y dirección)
     * @param array{count: int, signedPercent: int}|null              $finished     resumen de las estancias finalizadas
     * @param list<TutorshipRow>                                      $tutorships
     * @param list<CompanyRow>                                        $companies
     * @param list<GroupRow>                                          $groups
     * @param list<WorkloadRow>                                       $workload
     */
    public function __construct(
        public ScopeFacts $scope,
        public AttentionThresholds $thresholds,
        public bool $emptyScope,
        public array $tasks,
        public array $milestones,
        public array $stays,
        public string $funnelMode,
        public array $funnel,
        public array $coverage,
        public ?array $finished,
        public array $tutorships,
        public array $companies,
        public array $groups,
        public array $workload,
    ) {}

    /** ¿Tiene la persona algún bloque de gestión de estancias (tarjetas, embudo)? */
    public function showsStays(): bool
    {
        return $this->stays !== [];
    }
}
