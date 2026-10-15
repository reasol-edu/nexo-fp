<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Repository\CompanyRepository;
use App\Repository\EmailNotificationLogRepository;
use App\Repository\StayRepository;
use App\Repository\StudentRepository;
use App\Repository\TeacherRepository;
use App\Repository\WorkcenterRepository;
use App\Service\AppSettingsInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Compone el inicio de una persona: carga los hechos con un número fijo de consultas, calcula la vista en
 * memoria y añade lo único que necesita nombres (estudiantes y centros de trabajo) y los avisos de datos
 * del centro, que solo ve quien lo administra.
 */
final class DashboardService
{
    /** Días hacia atrás que cuentan los correos fallidos del aviso de sistema. */
    private const FAILED_EMAIL_DAYS = 7;
    /** Días hacia delante en los que una estancia con puestos en borrador se considera ya en marcha. */
    private const DRAFT_LOOKAHEAD_DAYS = 14;

    public function __construct(
        private readonly DashboardLoader $loader,
        private readonly DashboardBuilder $builder,
        private readonly AppSettingsInterface $settings,
        private readonly StudentRepository $students,
        private readonly WorkcenterRepository $workcenters,
        private readonly TeacherRepository $teachers,
        private readonly CompanyRepository $companies,
        private readonly StayRepository $stays,
        private readonly EmailNotificationLogRepository $emailLog,
        private readonly ClockInterface $clock,
    ) {}

    public function build(EducationalCentre $centre, AcademicYear $year, Teacher $viewer, string $funnelMode = DashboardBuilder::FUNNEL_ACTIVE): DashboardPage
    {
        $facts      = $this->loader->load($centre, $year, $viewer);
        $values     = $this->settings->getManyForCentre(['attention.urgent_days', 'attention.soon_days'], $centre);
        $thresholds = AttentionThresholds::fromSettings($values['attention.urgent_days'] ?? null, $values['attention.soon_days'] ?? null);
        $view       = $this->builder->build($facts, $thresholds, $funnelMode);

        $studentIds    = [];
        $workcenterIds = [];
        foreach ($view->tutorships as $row) {
            $studentIds[] = $row->studentId;
            if ($row->workcenterId !== null) {
                $workcenterIds[] = $row->workcenterId;
            }
        }
        foreach ($view->groups as $group) {
            foreach ($group->withoutPositionIds as $studentId) {
                $studentIds[] = $studentId;
            }
        }

        return new DashboardPage(
            $view,
            $this->students->findNamesByIds($studentIds),
            $this->workcenters->findLabelsByIds($workcenterIds),
            $view->scope->isAdmin ? $this->review($centre, $year) : null,
        );
    }

    /** @return array{teachersWithoutEmail: int, companiesWithoutWorkers: int, staysWithDrafts: int, failedEmails: int} */
    private function review(EducationalCentre $centre, AcademicYear $year): array
    {
        $today = $this->clock->now()->setTime(0, 0, 0);

        return [
            'teachersWithoutEmail'    => \count($this->teachers->findActiveWithoutEmailByAcademicYear($year)),
            'companiesWithoutWorkers' => \count($this->companies->findWithPositionsInYearWithoutWorkers($centre, $year)),
            'staysWithDrafts'         => \count($this->stays->findWithDraftPositionsStartingBefore($year, $today->modify('+' . self::DRAFT_LOOKAHEAD_DAYS . ' days'))),
            'failedEmails'            => $this->emailLog->countFailedSince($centre, $today->modify('-' . self::FAILED_EMAIL_DAYS . ' days')),
        ];
    }
}
