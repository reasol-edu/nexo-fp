<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Dashboard\Facts\DashboardFacts;
use App\Dashboard\Facts\PositionFacts;
use App\Dashboard\Facts\StayFacts;
use App\Dashboard\Facts\StudentFacts;
use App\Dashboard\View\CompanyRow;
use App\Dashboard\View\CoverageRow;
use App\Dashboard\View\DashboardView;
use App\Dashboard\View\GroupRow;
use App\Dashboard\View\Milestone;
use App\Dashboard\View\StayCard;
use App\Dashboard\View\Task;
use App\Dashboard\View\TutorshipRow;
use App\Dashboard\View\WorkloadRow;
use App\Entity\TrainingPositionState;
use App\Service\TeacherAssignmentCalculator;

/**
 * Calcula el inicio a partir de los hechos ya cargados ({@see DashboardFacts}). No consulta nada: todo
 * es memoria, de modo que el coste en consultas lo fija el cargador y no crece con el número de estancias.
 *
 * Cada cifra se calcula solo sobre lo que la persona puede gestionar o ver:
 *  - quien gestiona una enseñanza ve a los estudiantes de las suyas (la dirección, a todos);
 *  - el docente de enlace ve los puestos de sus empresas;
 *  - el tutor/a dual docente ve sus puestos y el docente de grupo, a los estudiantes de sus grupos,
 *    sin cifras de puestos libres.
 *
 * @phpstan-type StayContext array{
 *     stay: StayFacts,
 *     finished: bool,
 *     days: int,
 *     manages: bool,
 *     managed: array<string, true>,
 *     positions: list<PositionFacts>,
 *     byStudent: array<string, PositionFacts>
 * }
 */
final class DashboardBuilder
{
    public const FUNNEL_ACTIVE   = 'activas';
    public const FUNNEL_UPCOMING = 'proximas';
    public const FUNNEL_ALL      = 'todas';

    private const MILESTONE_DAYS  = 30;
    private const MAX_LISTED      = 8;

    public function __construct(private readonly TeacherAssignmentCalculator $calculator = new TeacherAssignmentCalculator()) {}

    public function build(DashboardFacts $facts, AttentionThresholds $thresholds, string $funnelMode = self::FUNNEL_ACTIVE): DashboardView
    {
        $scope = $facts->scope;
        $ctx   = $this->contexts($facts);

        $tasks       = $this->tasks($facts, $thresholds, $ctx);
        $tutorships  = $this->tutorships($facts, $thresholds, $ctx);
        [$funnel, $finished] = $this->funnelAndFinished($ctx, $funnelMode);

        return new DashboardView(
            scope: $scope,
            thresholds: $thresholds,
            emptyScope: !$scope->manages() && !$scope->teaches() && !$scope->isLiaison() && $tutorships === [],
            tasks: $tasks,
            milestones: $this->milestones($facts, $ctx),
            stays: $this->cards($facts, $ctx, $tasks),
            funnelMode: $funnelMode,
            funnel: $funnel,
            coverage: $scope->seesCoverage() ? $this->coverage($facts, $thresholds, $ctx) : [],
            finished: $finished,
            tutorships: $tutorships,
            companies: $this->companies($facts, $ctx),
            groups: $this->groups($facts, $ctx),
            workload: $this->workload($facts, $ctx),
        );
    }

    // ── Contexto por estancia ────────────────────────────────────────────────

    /** @return array<string, StayContext> */
    private function contexts(DashboardFacts $facts): array
    {
        $scope = $facts->scope;

        $positionsByStay = [];
        foreach ($facts->positions as $position) {
            $positionsByStay[$position->stayId][] = $position;
        }

        $contexts = [];
        foreach ($facts->stays as $stay) {
            $manages = $scope->managesStay($stay);
            $managed = [];
            if ($manages) {
                foreach ($facts->students[$stay->id] ?? [] as $studentId => $student) {
                    if ($this->isManaged($student, $scope->isAdmin, $scope->manageProgrammeIds())) {
                        $managed[(string) $studentId] = true;
                    }
                }
            }

            $positions = $positionsByStay[$stay->id] ?? [];
            $byStudent = [];
            foreach ($positions as $position) {
                if ($position->studentId !== null) {
                    $byStudent[$position->studentId] = $position;
                }
            }

            $contexts[$stay->id] = [
                'stay'      => $stay,
                'finished'  => $stay->isFinished($facts->today),
                'days'      => $stay->daysToStart($facts->today),
                'manages'   => $manages,
                'managed'   => $managed,
                'positions' => $positions,
                'byStudent' => $byStudent,
            ];
        }

        return $contexts;
    }

    /**
     * Un estudiante lo gestiona quien coordina o dirige alguna de sus enseñanzas; el que no tiene grupo en
     * ninguna enseñanza de la estancia lo gestiona cualquiera que gestione alguna (igual que StayScope).
     *
     * @param list<string> $manageProgrammeIds
     */
    private function isManaged(StudentFacts $student, bool $isAdmin, array $manageProgrammeIds): bool
    {
        return $isAdmin || $student->levels === [] || array_intersect($student->programmeIds(), $manageProgrammeIds) !== [];
    }

    // ── Atención ahora ───────────────────────────────────────────────────────

    /**
     * @param array<string, StayContext> $ctx
     * @return list<Task>
     */
    private function tasks(DashboardFacts $facts, AttentionThresholds $thresholds, array $ctx): array
    {
        $scope = $facts->scope;
        $tasks = [];

        foreach ($ctx as $c) {
            if ($c['finished']) {
                continue;
            }
            $stay     = $c['stay'];
            $days     = $c['days'];
            $severity = $thresholds->severityFor($days);
            $add      = static function (string $key, Severity $sev, int $count, ?string $filter = null, ?string $subject = null, int $extra = 0) use (&$tasks, $stay, $days): void {
                if ($count > 0) {
                    $tasks[] = new Task($key, $sev, $count, $stay->id, $stay->name, $days, $filter, $subject, $extra);
                }
            };

            if ($c['manages']) {
                $managedPositions = array_values(array_filter(
                    $c['positions'],
                    static fn (PositionFacts $p): bool => $p->studentId !== null && isset($c['managed'][$p->studentId]),
                ));

                $withoutIds = array_diff_key($c['managed'], $c['byStudent']);
                $add(Task::STUDENTS_WITHOUT_POSITION, $severity, \count($withoutIds), 'sin-puesto');
                $add(Task::MISSING_TUTOR, $severity, $this->count($managedPositions, static fn (PositionFacts $p): bool => $p->tutorId === null), 'sin-tutor');
                $add(Task::MISSING_MENTOR, $severity, $this->count($managedPositions, static fn (PositionFacts $p): bool => $p->mentorId === null), 'sin-empresa');
                $add(Task::REGISTERED_UNSIGNED, $severity, $this->count($managedPositions, static fn (PositionFacts $p): bool => $p->isRegistered() && !$p->signed), 'sin-firmar');

                // Un puesto listo para pasar a Séneca nunca es crítico: es trabajo pendiente, no un bloqueo.
                $ready = $this->count($managedPositions, static fn (PositionFacts $p): bool => $p->state === TrainingPositionState::DRAFT && $p->hasBothTutors());
                $add(Task::READY_FOR_SENECA, $severity === Severity::Critical ? Severity::Soon : $severity, $ready);

                $deficit = $this->supplyDeficit($facts, $c, array_keys($withoutIds));
                $add(Task::SUPPLY_DEFICIT, Severity::Info, $deficit['deficit'], 'libres', null, $deficit['demand']);

                continue;
            }

            // Sin gestionar la estancia: sus propias tutorías y los estudiantes de sus grupos.
            $add(Task::MY_UNSIGNED, $severity, $this->count($c['positions'], static fn (PositionFacts $p): bool => $p->tutorId === $scope->userId && $p->isRegistered() && !$p->signed), 'sin-firmar');

            if ($scope->teaches()) {
                $groupNames = [];
                $without    = 0;
                foreach ($facts->students[$stay->id] ?? [] as $studentId => $student) {
                    $mine = array_filter($student->levels, static fn ($l): bool => \in_array($l->groupId, $scope->groupIds(), true));
                    if ($mine === [] || isset($c['byStudent'][(string) $studentId])) {
                        continue;
                    }
                    ++$without;
                    foreach ($mine as $level) {
                        $groupNames[$level->groupName] = true;
                    }
                }
                $add(Task::GROUP_WITHOUT_POSITION, $severity, $without, 'sin-puesto', implode(', ', array_keys($groupNames)));
            }
        }

        // Empresas de las que es enlace y que no tienen trabajadores: se avisa una vez por empresa, con la estancia más próxima.
        $companyIds = $scope->companyIds();
        foreach ($scope->companies as $company) {
            if (!$company->withoutWorkers) {
                continue;
            }
            $count = 0;
            $best  = null;
            foreach ($ctx as $c) {
                if ($c['finished']) {
                    continue;
                }
                $n = $this->count($c['positions'], static fn (PositionFacts $p): bool => $p->companyId === $company->id);
                if ($n > 0) {
                    $count += $n;
                    if ($best === null || $c['days'] < $best['days']) {
                        $best = $c;
                    }
                }
            }
            if ($best !== null && $companyIds !== []) {
                $tasks[] = new Task(Task::COMPANY_WITHOUT_WORKERS, $thresholds->severityFor($best['days']), $count, $best['stay']->id, $best['stay']->name, $best['days'], null, $company->name, 0, $company->id);
            }
        }

        usort($tasks, static fn (Task $a, Task $b): int =>
            $a->severity->rank() <=> $b->severity->rank()
            ?: $a->daysToStart <=> $b->daysToStart
            ?: $b->count <=> $a->count
            ?: strcmp($a->stayName, $b->stayName)
            ?: strcmp($a->key, $b->key));

        return $tasks;
    }

    /**
     * Estimación de cuántos puestos faltan para los estudiantes sin puesto: puestos libres que alguno de
     * ellos puede ocupar (su nivel está entre los ofertados y no hay una preferencia vigente a otra enseñanza).
     *
     * @param StayContext  $c
     * @param list<string|int> $withoutStudentIds
     * @return array{deficit: int, demand: int}
     */
    private function supplyDeficit(DashboardFacts $facts, array $c, array $withoutStudentIds): array
    {
        $demand = \count($withoutStudentIds);
        if ($demand === 0) {
            return ['deficit' => 0, 'demand' => 0];
        }

        $students = [];
        foreach ($withoutStudentIds as $studentId) {
            $student = $facts->students[$c['stay']->id][(string) $studentId] ?? null;
            if ($student !== null) {
                $students[] = $student;
            }
        }

        $supply = 0;
        foreach ($c['positions'] as $position) {
            if (!$position->isFree()) {
                continue;
            }
            foreach ($students as $student) {
                if ($this->accepts($position, $student, $facts->today)) {
                    ++$supply;
                    break;
                }
            }
        }

        return ['deficit' => max(0, $demand - $supply), 'demand' => $demand];
    }

    private function accepts(PositionFacts $position, StudentFacts $student, \DateTimeImmutable $today): bool
    {
        // Preferencia vigente a una enseñanza: los estudiantes de otras no pueden ocuparlo todavía.
        if ($position->priorityProgrammeId !== null && $position->priorityUntil !== null && $today <= $position->priorityUntil) {
            $programmes = $student->programmeIds();
            if ($programmes !== [] && !\in_array($position->priorityProgrammeId, $programmes, true)) {
                return false;
            }
        }

        $levels = $student->levelIds();

        return $position->levelIds === [] || $levels === [] || array_intersect($position->levelIds, $levels) !== [];
    }

    // ── Próximos 30 días ─────────────────────────────────────────────────────

    /**
     * @param array<string, StayContext> $ctx
     * @return list<Milestone>
     */
    private function milestones(DashboardFacts $facts, array $ctx): array
    {
        $today   = $facts->today;
        $limit   = $today->modify('+' . self::MILESTONE_DAYS . ' days');
        $scope   = $facts->scope;
        $items   = [];
        $inRange = static fn (\DateTimeImmutable $d): bool => $d >= $today && $d <= $limit;

        foreach ($ctx as $c) {
            if ($c['finished']) {
                continue;
            }
            $stay = $c['stay'];

            if ($inRange($stay->start)) {
                $items[] = new Milestone($stay->start, Milestone::STAY_STARTS, $stay->id, $stay->name);
            }
            if ($inRange($stay->end)) {
                $items[] = new Milestone($stay->end, Milestone::STAY_ENDS, $stay->id, $stay->name);
            }

            // Convenios por firmar: antes de que empiece la estancia.
            if ($inRange($stay->start)) {
                $unsigned = $this->count($c['positions'], static fn (PositionFacts $p): bool =>
                    $p->isRegistered() && !$p->signed && $p->studentId !== null
                    && (isset($c['managed'][$p->studentId]) || $p->tutorId === $scope->userId));
                if ($unsigned > 0) {
                    $items[] = new Milestone($stay->start, Milestone::SIGNATURE_DUE, $stay->id, $stay->name, $unsigned);
                }
            }

            if (!$c['manages']) {
                continue;
            }

            // Fin de la preferencia de una enseñanza en puestos libres compartidos.
            $byDate = [];
            foreach ($c['positions'] as $p) {
                if ($p->isFree() && $p->priorityProgrammeId !== null && $p->priorityUntil !== null && $inRange($p->priorityUntil)) {
                    $byDate[$p->priorityUntil->format('Y-m-d') . '|' . $p->priorityProgrammeId][] = $p;
                }
            }
            foreach ($byDate as $group) {
                $first = $group[0];
                \assert($first->priorityUntil !== null);
                $name = null;
                foreach ($stay->programmes as $programme) {
                    if ($programme->id === $first->priorityProgrammeId) {
                        $name = $programme->name;
                    }
                }
                $items[] = new Milestone($first->priorityUntil, Milestone::PRIORITY_ENDS, $stay->id, $stay->name, \count($group), $name);
            }

            // Puestos con fecha de inicio propia distinta de la de la estancia.
            $starts = [];
            foreach ($c['positions'] as $p) {
                $ownsStudent = $p->studentId === null || isset($c['managed'][$p->studentId]);
                if ($ownsStudent && $p->start !== null && $p->start != $stay->start && $inRange($p->start)) {
                    $starts[$p->start->format('Y-m-d')][] = $p;
                }
            }
            foreach ($starts as $group) {
                \assert($group[0]->start !== null);
                $items[] = new Milestone($group[0]->start, Milestone::POSITIONS_START, $stay->id, $stay->name, \count($group));
            }
        }

        usort($items, static fn (Milestone $a, Milestone $b): int => $a->date <=> $b->date ?: strcmp($a->key, $b->key) ?: strcmp($a->stayName, $b->stayName));

        return $items;
    }

    // ── Mis estancias ────────────────────────────────────────────────────────

    /**
     * @param array<string, StayContext> $ctx
     * @param list<Task>                $tasks
     * @return list<StayCard>
     */
    private function cards(DashboardFacts $facts, array $ctx, array $tasks): array
    {
        $worst = [];
        foreach ($tasks as $task) {
            $worst[$task->stayId] = isset($worst[$task->stayId]) ? $worst[$task->stayId]->moreUrgent($task->severity) : $task->severity;
        }

        $cards = [];
        foreach ($ctx as $c) {
            if ($c['finished'] || !$c['manages']) {
                continue;
            }
            $stay      = $c['stay'];
            $assigned  = $this->assignedOf($c);
            $progress  = StayProgress::calculate(\count($c['managed']), \count($c['positions']), $assigned, true, true);
            $current   = $c['days'] <= 0;
            $total     = max(1, (int) $stay->start->diff($stay->end)->days);
            $elapsed   = $current ? min($total, max(0, -$c['days'])) : 0;

            $cards[] = new StayCard(
                id: $stay->id,
                name: $stay->name,
                programmeNames: $stay->programmeNames(),
                start: $stay->start,
                end: $stay->end,
                daysToStart: $c['days'],
                daysToEnd: $stay->daysToEnd($facts->today),
                current: $current,
                elapsedPercent: (int) round(100 * $elapsed / $total),
                steps: $progress['steps'],
                next: $progress['next'],
                worst: $worst[$stay->id] ?? null,
                students: \count($c['managed']),
            );
        }

        // En curso primero (la que termina antes arriba) y después las próximas por fecha de inicio.
        usort($cards, static fn (StayCard $a, StayCard $b): int =>
            (int) !$a->current <=> (int) !$b->current
            ?: ($a->current ? $a->end <=> $b->end : $a->start <=> $b->start)
            ?: strcmp($a->name, $b->name));

        return $cards;
    }

    /**
     * Puestos con estudiante gestionado, con lo que necesita el cálculo del avance.
     *
     * @param StayContext $c
     * @return list<array{state: TrainingPositionState, signed: bool, hasAcademic: bool, hasMentor: bool}>
     */
    private function assignedOf(array $c): array
    {
        $assigned = [];
        foreach ($c['positions'] as $p) {
            if ($p->studentId !== null && isset($c['managed'][$p->studentId])) {
                $assigned[] = ['state' => $p->state, 'signed' => $p->signed, 'hasAcademic' => $p->tutorId !== null, 'hasMentor' => $p->mentorId !== null];
            }
        }

        return $assigned;
    }

    // ── Avance del proceso y finalizadas ─────────────────────────────────────

    /**
     * @param array<string, StayContext> $ctx
     * @return array{0: list<array{key: string, done: int, total: int}>, 1: array{count: int, signedPercent: int}|null}
     */
    private function funnelAndFinished(array $ctx, string $mode): array
    {
        $sums = ['with_position' => [0, 0], 'with_tutors' => [0, 0], 'registered' => [0, 0], 'signed' => [0, 0]];
        $any  = false;
        $finishedCount  = 0;
        $finishedSigned = 0;
        $finishedAssigned = 0;

        foreach ($ctx as $c) {
            if (!$c['manages']) {
                continue;
            }
            $assigned = $this->assignedOf($c);

            if ($c['finished']) {
                ++$finishedCount;
                $finishedAssigned += \count($assigned);
                $finishedSigned   += \count(array_filter($assigned, static fn (array $p): bool => $p['state'] === TrainingPositionState::DONE && $p['signed']));
            }

            $included = match ($mode) {
                self::FUNNEL_ALL      => true,
                self::FUNNEL_UPCOMING => !$c['finished'] && $c['days'] > 0,
                default               => !$c['finished'],
            };
            if (!$included) {
                continue;
            }

            $any = true;
            foreach (StayProgress::calculate(\count($c['managed']), \count($c['positions']), $assigned, true, true)['steps'] as $step) {
                $sums[$step['key']][0] += $step['done'];
                $sums[$step['key']][1] += $step['total'];
            }
        }

        $funnel = [];
        if ($any) {
            foreach ($sums as $key => [$done, $total]) {
                $funnel[] = ['key' => $key, 'done' => $done, 'total' => $total];
            }
        }

        return [
            $funnel,
            $finishedCount > 0
                ? ['count' => $finishedCount, 'signedPercent' => $finishedAssigned > 0 ? (int) round(100 * $finishedSigned / $finishedAssigned) : 0]
                : null,
        ];
    }

    // ── Cobertura por enseñanza ──────────────────────────────────────────────

    /**
     * @param array<string, StayContext> $ctx
     * @return list<CoverageRow>
     */
    private function coverage(DashboardFacts $facts, AttentionThresholds $thresholds, array $ctx): array
    {
        $scope = $facts->scope;
        /** @var array<string, \App\Dashboard\Facts\ProgrammeRef> $refs */
        $refs = [];
        /** @var array<string, array{students: int, without: int, tutors: int, registered: int, signed: int}> $counts */
        $counts = [];
        /** @var array<string, Severity> $risks */
        $risks = [];

        foreach ($ctx as $c) {
            if ($c['finished'] || !$c['manages']) {
                continue;
            }
            $stay     = $c['stay'];
            $severity = $thresholds->severityFor($c['days']);

            foreach ($c['managed'] as $studentId => $_) {
                $student = $facts->students[$stay->id][(string) $studentId] ?? null;
                if ($student === null) {
                    continue;
                }
                foreach (array_intersect($student->programmeIds(), $stay->programmeIds()) as $programmeId) {
                    if (!$scope->isAdmin && !\in_array($programmeId, $scope->headedProgrammeIds, true)) {
                        continue;
                    }
                    $ref = null;
                    foreach ($stay->programmes as $programme) {
                        if ($programme->id === $programmeId) {
                            $ref = $programme;
                        }
                    }
                    if ($ref === null) {
                        continue;
                    }

                    $refs[$programmeId] = $ref;
                    $counts[$programmeId] ??= ['students' => 0, 'without' => 0, 'tutors' => 0, 'registered' => 0, 'signed' => 0];
                    ++$counts[$programmeId]['students'];

                    $position = $c['byStudent'][(string) $studentId] ?? null;
                    $open     = false;
                    if ($position === null) {
                        ++$counts[$programmeId]['without'];
                        $open = true;
                    } else {
                        if ($position->hasBothTutors()) {
                            ++$counts[$programmeId]['tutors'];
                        } else {
                            $open = true;
                        }
                        if ($position->isRegistered()) {
                            ++$counts[$programmeId]['registered'];
                            if ($position->signed) {
                                ++$counts[$programmeId]['signed'];
                            } else {
                                $open = true;
                            }
                        }
                    }
                    if ($open) {
                        $risks[$programmeId] = isset($risks[$programmeId]) ? $risks[$programmeId]->moreUrgent($severity) : $severity;
                    }
                }
            }
        }

        $result = [];
        foreach ($refs as $programmeId => $ref) {
            $n        = $counts[$programmeId];
            $result[] = new CoverageRow($ref->id, $ref->name, $ref->familyName, $n['students'], $n['without'], $n['tutors'], $n['registered'], $n['signed'], $risks[$programmeId] ?? null);
        }

        usort($result, static fn (CoverageRow $a, CoverageRow $b): int =>
            ($a->risk?->rank() ?? 9) <=> ($b->risk?->rank() ?? 9)
            ?: $b->withoutPosition <=> $a->withoutPosition
            ?: strcmp($a->programmeName, $b->programmeName));

        return $result;
    }

    // ── Mis tutorías ─────────────────────────────────────────────────────────

    /**
     * @param array<string, StayContext> $ctx
     * @return list<TutorshipRow>
     */
    private function tutorships(DashboardFacts $facts, AttentionThresholds $thresholds, array $ctx): array
    {
        $rows = [];
        foreach ($ctx as $c) {
            if ($c['finished']) {
                continue;
            }
            $stay = $c['stay'];
            foreach ($c['positions'] as $p) {
                if ($p->tutorId !== $facts->scope->userId || $p->studentId === null) {
                    continue;
                }
                $start = $p->start ?? $stay->start;
                $days  = (int) $facts->today->diff($start)->format('%r%a');

                [$action, $severity] = match (true) {
                    $p->state === TrainingPositionState::DONE && $p->signed  => [TutorshipRow::ACTION_DONE, null],
                    $p->state === TrainingPositionState::DONE                => [TutorshipRow::ACTION_SIGN, $thresholds->severityFor($days)],
                    $p->state === TrainingPositionState::PENDING             => [TutorshipRow::ACTION_REGISTER, $thresholds->severityFor($days)],
                    $p->hasBothTutors()                                      => [TutorshipRow::ACTION_SEND, $thresholds->severityFor($days)],
                    default                                                  => [TutorshipRow::ACTION_COMPLETE_TUTORS, $thresholds->severityFor($days)],
                };

                $rows[] = new TutorshipRow($p->id, $stay->id, $stay->name, $p->studentId, $p->workcenterId, $p->state, $p->signed, $start, $p->end ?? $stay->end, $days, $action, $severity);
            }
        }

        usort($rows, static fn (TutorshipRow $a, TutorshipRow $b): int =>
            ($a->severity?->rank() ?? 9) <=> ($b->severity?->rank() ?? 9)
            ?: $a->daysToStart <=> $b->daysToStart
            ?: strcmp($a->stayName, $b->stayName));

        return $rows;
    }

    // ── Mis empresas ─────────────────────────────────────────────────────────

    /**
     * @param array<string, StayContext> $ctx
     * @return list<CompanyRow>
     */
    private function companies(DashboardFacts $facts, array $ctx): array
    {
        $rows = [];
        foreach ($facts->scope->companies as $company) {
            $free      = 0;
            $assigned  = 0;
            $nextStart = null;
            foreach ($ctx as $c) {
                if ($c['finished']) {
                    continue;
                }
                $own = array_filter($c['positions'], static fn (PositionFacts $p): bool => $p->companyId === $company->id);
                if ($own === []) {
                    continue;
                }
                foreach ($own as $p) {
                    $p->isFree() ? ++$free : ++$assigned;
                    $start = $p->start ?? $c['stay']->start;
                    if ($start >= $facts->today && ($nextStart === null || $start < $nextStart)) {
                        $nextStart = $start;
                    }
                }
            }
            $rows[] = new CompanyRow($company->id, $company->name, $company->withoutWorkers, $free, $assigned, $nextStart);
        }

        usort($rows, static fn (CompanyRow $a, CompanyRow $b): int =>
            (int) $b->withoutWorkers <=> (int) $a->withoutWorkers
            ?: ($a->nextStart ?? new \DateTimeImmutable('9999-12-31')) <=> ($b->nextStart ?? new \DateTimeImmutable('9999-12-31'))
            ?: strcmp($a->name, $b->name));

        return $rows;
    }

    // ── Mi grupo ─────────────────────────────────────────────────────────────

    /**
     * @param array<string, StayContext> $ctx
     * @return list<GroupRow>
     */
    private function groups(DashboardFacts $facts, array $ctx): array
    {
        $rows = [];
        foreach ($facts->scope->groups as $group) {
            $students = [];   // estudiante → ¿tiene puesto en alguna estancia no finalizada?
            $stayOf   = [];   // estudiante sin puesto → estancia
            foreach ($ctx as $c) {
                if ($c['finished']) {
                    continue;
                }
                foreach ($facts->students[$c['stay']->id] ?? [] as $studentId => $student) {
                    if (!\in_array($group->id, $student->groupIds(), true)) {
                        continue;
                    }
                    $sid     = (string) $studentId;
                    $placed  = isset($c['byStudent'][$sid]);
                    $students[$sid] = ($students[$sid] ?? false) || $placed;
                    if (!$placed) {
                        $stayOf[$sid] ??= $c['stay']->id;
                    }
                }
            }
            if ($students === []) {
                continue;
            }

            $without = array_keys(array_filter($students, static fn (bool $placed): bool => !$placed));
            $rows[]  = new GroupRow(
                $group->id,
                $group->name,
                \count($students),
                \count($students) - \count($without),
                \array_slice(array_map('strval', $without), 0, self::MAX_LISTED),
                \count($without),
                $without !== [] ? $stayOf[(string) $without[0]] ?? null : null,
            );
        }

        usort($rows, static fn (GroupRow $a, GroupRow $b): int => $b->withoutPositionTotal <=> $a->withoutPositionTotal ?: strcmp($a->name, $b->name));

        return $rows;
    }

    // ── Reparto por docente ──────────────────────────────────────────────────

    /**
     * Para cada grupo de una estancia con «Calcular asignaciones por docente» en el que la persona
     * tiene horas dualizadas o tutorías: cuántos estudiantes le corresponden y cuántos tiene.
     *
     * @param array<string, StayContext> $ctx
     * @return list<WorkloadRow>
     */
    private function workload(DashboardFacts $facts, array $ctx): array
    {
        $userId = $facts->scope->userId;
        $rows   = [];

        foreach ($ctx as $c) {
            $stay = $c['stay'];
            if ($c['finished'] || !$stay->calculateByTeacher || !isset($facts->hours[$stay->id])) {
                continue;
            }

            // Estudiantes de cada grupo en la estancia y nombre del grupo.
            $members = [];
            $names   = [];
            foreach ($facts->students[$stay->id] ?? [] as $studentId => $student) {
                foreach ($student->levels as $level) {
                    $members[$level->groupId][] = (string) $studentId;
                    $names[$level->groupId]     = $level->groupName;
                }
            }

            foreach ($facts->hours[$stay->id] as $groupId => $hoursByTeacher) {
                $students = $members[$groupId] ?? [];
                $mine     = $hoursByTeacher[$userId] ?? 0;
                $assigned = $this->count(
                    array_map(static fn (string $s): ?PositionFacts => $c['byStudent'][$s] ?? null, $students),
                    static fn (?PositionFacts $p): bool => $p !== null && $p->tutorId === $userId,
                );
                if ($mine <= 0 && $assigned === 0) {
                    continue;
                }

                $expected = $this->calculator->distribute(\count($students), $hoursByTeacher)[$userId] ?? 0;
                $rows[]   = new WorkloadRow($stay->id, $stay->name, $names[$groupId] ?? '', $expected, $assigned);
            }
        }

        usort($rows, static fn (WorkloadRow $a, WorkloadRow $b): int => strcmp($a->stayName, $b->stayName) ?: strcmp($a->groupName, $b->groupName));

        return $rows;
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    /**
     * @template T
     * @param iterable<T>        $items
     * @param callable(T): bool  $predicate
     */
    private function count(iterable $items, callable $predicate): int
    {
        $n = 0;
        foreach ($items as $item) {
            if ($predicate($item)) {
                ++$n;
            }
        }

        return $n;
    }
}
