<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Dashboard\View\DashboardView;

/** El inicio listo para mostrar: la vista calculada, los nombres que necesita y los avisos de datos del centro. */
final readonly class DashboardPage
{
    /**
     * @param array<string, string> $studentNames     id de estudiante → nombre
     * @param array<string, string> $workcenterLabels id de centro de trabajo → «Empresa — Centro»
     * @param array{teachersWithoutEmail: int, companiesWithoutWorkers: int, staysWithDrafts: int, failedEmails: int}|null $review
     *        avisos de datos del centro; solo para quien administra el centro
     */
    public function __construct(
        public DashboardView $view,
        public array $studentNames,
        public array $workcenterLabels,
        public ?array $review,
    ) {}
}
