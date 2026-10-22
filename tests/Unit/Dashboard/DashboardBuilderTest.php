<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dashboard;

use App\Dashboard\AttentionThresholds;
use App\Dashboard\DashboardBuilder;
use App\Dashboard\Facts\CompanyRef;
use App\Dashboard\Facts\DashboardFacts;
use App\Dashboard\Facts\GroupRef;
use App\Dashboard\Facts\LevelFacts;
use App\Dashboard\Facts\PositionFacts;
use App\Dashboard\Facts\ProgrammeRef;
use App\Dashboard\Facts\ScopeFacts;
use App\Dashboard\Facts\StayFacts;
use App\Dashboard\Facts\StudentFacts;
use App\Dashboard\Severity;
use App\Dashboard\View\DashboardView;
use App\Dashboard\View\Milestone;
use App\Dashboard\View\Task;
use App\Dashboard\View\TutorshipRow;
use App\Entity\TrainingPositionState;
use PHPUnit\Framework\TestCase;

/**
 * El inicio se calcula en memoria sobre hechos ya cargados: aquí se prueba qué ve cada responsabilidad
 * (que es lo que fallaba: cifras de otras enseñanzas, puestos libres para quien no debe verlos…).
 */
class DashboardBuilderTest extends TestCase
{
    private const TODAY = '2026-10-15';

    private \DateTimeImmutable $today;

    /** @var array<string, StayFacts> */
    private array $stays = [];
    /** @var list<PositionFacts> */
    private array $positions = [];
    /** @var array<string, array<string, StudentFacts>> */
    private array $students = [];
    /** @var array<string, array<string, array<string, int>>> */
    private array $hours = [];

    private ProgrammeRef $daw;
    private ProgrammeRef $smr;

    protected function setUp(): void
    {
        $this->today = new \DateTimeImmutable(self::TODAY);
        $this->daw   = new ProgrammeRef('prog-daw', 'DAW', 'Informática', 'head-inf');
        $this->smr   = new ProgrammeRef('prog-smr', 'SMR', 'Informática', 'head-inf');
    }

    // ── Estancias compartidas: cada coordinación ve solo lo suyo ─────────────

    public function testACoordinatorOnlyCountsTheStudentsOfTheirOwnProgrammeInASharedStay(): void
    {
        $this->stay('shared', -45, 45, [$this->daw, $this->smr]);
        foreach (['d1', 'd2', 'd3'] as $id) {
            $this->student('shared', $id, $this->daw);
        }
        foreach (['s1', 's2', 's3', 's4', 's5'] as $id) {
            $this->student('shared', $id, $this->smr);
        }

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        $task = $this->task($view, Task::STUDENTS_WITHOUT_POSITION);
        self::assertSame(3, $task->count, 'solo los 3 de DAW, no los 8 de la estancia');
        self::assertSame(3, $view->stays[0]->students);
    }

    public function testTheAdministrationCountsEveryStudent(): void
    {
        $this->stay('shared', -45, 45, [$this->daw, $this->smr]);
        $this->student('shared', 'd1', $this->daw);
        $this->student('shared', 's1', $this->smr);

        $view = $this->build($this->scope('admin', admin: true));

        self::assertSame(2, $this->task($view, Task::STUDENTS_WITHOUT_POSITION)->count);
    }

    public function testAStudentWithoutGroupInTheStayIsManagedByAnyManager(): void
    {
        $this->stay('s', -10, 80, [$this->daw]);
        $this->students['s']['orphan'] = new StudentFacts('orphan', []);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        self::assertSame(1, $this->task($view, Task::STUDENTS_WITHOUT_POSITION)->count);
    }

    // ── Gravedad según los días hasta el inicio ──────────────────────────────

    public function testTheSeverityOfATaskDependsOnTheDaysToTheStart(): void
    {
        foreach ([['started', -46], ['week', 5], ['month', 15], ['far', 60]] as [$id, $start]) {
            $this->stay($id, $start, $start + 90, [$this->daw]);
            $this->student($id, $id . '-s', $this->daw);
        }

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        $bySeverity = [];
        foreach ($view->tasks as $task) {
            if ($task->key === Task::STUDENTS_WITHOUT_POSITION) {
                $bySeverity[$task->stayId] = $task->severity;
            }
        }
        self::assertSame(Severity::Critical, $bySeverity['started']);
        self::assertSame(Severity::Critical, $bySeverity['week']);
        self::assertSame(Severity::Soon, $bySeverity['month']);
        self::assertSame(Severity::Info, $bySeverity['far']);
    }

    public function testTasksAreSortedBySeverityThenByDaysToStart(): void
    {
        $this->stay('far', 60, 150, [$this->daw]);
        $this->stay('soon', 10, 100, [$this->daw]);
        $this->stay('now', -20, 70, [$this->daw]);
        foreach (['far', 'soon', 'now'] as $id) {
            $this->student($id, $id . '-s', $this->daw);
        }

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        $students = array_values(array_filter($view->tasks, static fn (Task $t): bool => $t->key === Task::STUDENTS_WITHOUT_POSITION));
        self::assertSame(['now', 'soon', 'far'], array_map(static fn (Task $t): string => $t->stayId, $students));

        // Todas las críticas van antes que las próximas y éstas antes que las informativas.
        $ranks = array_map(static fn (Task $t): int => $t->severity->rank(), $view->tasks);
        $sorted = $ranks;
        sort($sorted);
        self::assertSame($sorted, $ranks);
    }

    public function testCustomThresholdsChangeTheSeverity(): void
    {
        $this->stay('s', 10, 100, [$this->daw]);
        $this->student('s', 's1', $this->daw);

        $default = $this->build($this->scope('diego', manage: [$this->daw]));
        $custom  = $this->build($this->scope('diego', manage: [$this->daw]), new AttentionThresholds(14, 30));

        self::assertSame(Severity::Soon, $this->task($default, Task::STUDENTS_WITHOUT_POSITION)->severity);
        self::assertSame(Severity::Critical, $this->task($custom, Task::STUDENTS_WITHOUT_POSITION)->severity);
    }

    public function testAMissingTutorIsReportedEvenBeforeTheStayStarts(): void
    {
        $this->stay('s', 10, 100, [$this->daw]);
        $this->student('s', 's1', $this->daw);
        $this->position('p1', 's', 's1', tutor: null, mentor: 'm1');

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        $task = $this->task($view, Task::MISSING_TUTOR);
        self::assertSame(1, $task->count);
        self::assertSame(Severity::Soon, $task->severity);
        self::assertSame('sin-tutor', $task->filter);
    }

    public function testPositionsReadyForSenecaAreNeverCritical(): void
    {
        $this->stay('s', -5, 85, [$this->daw]);
        $this->student('s', 's1', $this->daw);
        $this->position('p1', 's', 's1', tutor: 't1', mentor: 'm1', state: TrainingPositionState::DRAFT);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        self::assertSame(Severity::Soon, $this->task($view, Task::READY_FOR_SENECA)->severity);
    }

    public function testRegisteredButUnsignedPositionsAreUrgentWhenTheStayIsAboutToStart(): void
    {
        $this->stay('s', 3, 93, [$this->daw]);
        $this->student('s', 's1', $this->daw);
        $this->position('p1', 's', 's1', tutor: 't1', mentor: 'm1', state: TrainingPositionState::DONE, signed: false);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        $task = $this->task($view, Task::REGISTERED_UNSIGNED);
        self::assertSame(Severity::Critical, $task->severity);
        self::assertSame('sin-firmar', $task->filter);
    }

    // ── Estancias finalizadas ────────────────────────────────────────────────

    public function testFinishedStaysAreNotPartOfTheTasksOrTheCards(): void
    {
        $this->stay('old', -120, -30, [$this->daw]);
        $this->student('old', 'o1', $this->daw);
        $this->position('p1', 'old', 'o1', tutor: 't1', mentor: 'm1', state: TrainingPositionState::DONE, signed: true);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        self::assertSame([], $view->tasks);
        self::assertSame([], $view->stays);
        self::assertSame(['count' => 1, 'signedPercent' => 100], $view->finished);
    }

    // ── Oferta y demanda ─────────────────────────────────────────────────────

    public function testTheSupplyDeficitCountsHowManyPositionsAreMissing(): void
    {
        $this->stay('s', -5, 85, [$this->daw]);
        foreach (['a', 'b', 'c', 'd', 'e'] as $id) {
            $this->student('s', $id, $this->daw);
        }
        $this->position('free1', 's', null);
        $this->position('free2', 's', null);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        $deficit = $this->task($view, Task::SUPPLY_DEFICIT);
        self::assertSame(3, $deficit->count, '5 estudiantes y 2 puestos libres: faltan 3');
        self::assertSame(5, $deficit->extra);
        self::assertSame(Severity::Info, $deficit->severity);
    }

    public function testThereIsNoDeficitWhenThereAreEnoughFreePositions(): void
    {
        $this->stay('s', -5, 85, [$this->daw]);
        $this->student('s', 'a', $this->daw);
        $this->position('free1', 's', null);
        $this->position('free2', 's', null);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        self::assertNull($this->find($view, Task::SUPPLY_DEFICIT));
    }

    public function testAPositionReservedForAnotherProgrammeDoesNotCountAsSupply(): void
    {
        $this->stay('s', -5, 85, [$this->daw, $this->smr]);
        $this->student('s', 'd1', $this->daw);
        // Libre, pero reservado a SMR hasta dentro de 10 días: el estudiante de DAW no puede ocuparlo.
        $this->position('free1', 's', null, priorityLevel: 'lvl-prog-smr',priorityUntil: '+10 days');

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        self::assertSame(1, $this->task($view, Task::SUPPLY_DEFICIT)->count);
    }

    public function testAPositionOfferedToAnotherLevelDoesNotCountAsSupply(): void
    {
        $this->stay('s', -5, 85, [$this->daw]);
        $this->student('s', 'd1', $this->daw);
        $this->position('free1', 's', null, levels: ['daw-2']);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        self::assertSame(1, $this->task($view, Task::SUPPLY_DEFICIT)->count);
    }

    // ── Docente de grupo: sin cifras de puestos libres ───────────────────────

    public function testAGroupTeacherOnlySeesTheStudentsOfTheirGroupAndNoFreePositions(): void
    {
        $this->stay('s', -45, 45, [$this->daw]);
        $this->student('s', 'mine1', $this->daw, group: 'g-mine');
        $this->student('s', 'mine2', $this->daw, group: 'g-mine');
        $this->student('s', 'other', $this->daw, group: 'g-other');
        $this->position('free1', 's', null);
        $this->position('free2', 's', null);

        $view = $this->build(new ScopeFacts('fernando', false, [], [], [new GroupRef('g-mine', 'DAW1A', 'prog-daw')], []));

        self::assertSame([Task::GROUP_WITHOUT_POSITION], array_map(static fn (Task $t): string => $t->key, $view->tasks));
        self::assertSame(2, $view->tasks[0]->count, 'solo los de su grupo');
        self::assertSame('G-MINE', $view->tasks[0]->subject, 'el nombre del grupo de los estudiantes afectados');
        self::assertSame([], $view->stays, 'no gestiona ninguna estancia: sin tarjetas ni embudo');
        self::assertSame([], $view->funnel);
        self::assertNull($this->find($view, Task::SUPPLY_DEFICIT));
    }

    public function testTheGroupBlockListsTheStudentsWithoutPosition(): void
    {
        $this->stay('s', -45, 45, [$this->daw]);
        $this->student('s', 'placed', $this->daw, group: 'g1');
        $this->student('s', 'waiting', $this->daw, group: 'g1');
        $this->position('p1', 's', 'placed', tutor: 't1', mentor: 'm1');

        $view = $this->build(new ScopeFacts('fernando', false, [], [], [new GroupRef('g1', 'DAW1A', 'prog-daw')], []));

        self::assertCount(1, $view->groups);
        $group = $view->groups[0];
        self::assertSame([2, 1, 1], [$group->students, $group->withPosition, $group->withoutPositionTotal]);
        self::assertSame(['waiting'], $group->withoutPositionIds);
        self::assertSame('s', $group->stayId);
    }

    // ── Estudiantes sin NUSS ─────────────────────────────────────────────────

    public function testAManagerIsToldHowManyOfTheirStudentsHaveNoNuss(): void
    {
        $this->stay('shared', -5, 80, [$this->daw, $this->smr]);
        $this->student('shared', 'd1', $this->daw);
        $this->student('shared', 'd2', $this->daw);
        $this->student('shared', 's1', $this->smr);
        $this->missingNuss('shared', 'd1');
        $this->missingNuss('shared', 's1');   // de otra enseñanza: no es suyo

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        $task = $this->task($view, Task::STUDENTS_WITHOUT_NUSS);
        self::assertSame(1, $task->count);
        self::assertSame('sin-nuss', $task->filter);
        self::assertSame(Severity::Critical, $task->severity, 'la estancia ya ha empezado');
    }

    public function testAnUnknownNussGeneratesNoTask(): void
    {
        $this->stay('s', -5, 80, [$this->daw]);
        $this->student('s', 'd1', $this->daw);

        self::assertNull($this->find($this->build($this->scope('diego', manage: [$this->daw])), Task::STUDENTS_WITHOUT_NUSS));
    }

    public function testAGroupTeacherIsOnlyToldAboutTheStudentsOfTheirGroups(): void
    {
        $this->stay('s', -45, 45, [$this->daw]);
        $this->student('s', 'mine', $this->daw, group: 'g-mine');
        $this->student('s', 'other', $this->daw, group: 'g-other');
        $this->missingNuss('s', 'mine');
        $this->missingNuss('s', 'other');

        $view = $this->build(new ScopeFacts('fernando', false, [], [], [new GroupRef('g-mine', 'DAW1A', 'prog-daw')], []));

        $task = $this->task($view, Task::GROUP_WITHOUT_NUSS);
        self::assertSame(1, $task->count);
        self::assertSame('G-MINE', $task->subject);
        self::assertNull($this->find($view, Task::STUDENTS_WITHOUT_NUSS), 'no gestiona la estancia');
    }

    // ── Docente de enlace: solo sus empresas ─────────────────────────────────

    public function testALiaisonOnlySeesTheTasksAndFiguresOfTheirCompanies(): void
    {
        $this->stay('s', -10, 80, [$this->daw]);
        $this->student('s', 'a', $this->daw);
        $this->position('mine1', 's', null, company: 'c-mine');
        $this->position('mine2', 's', 'a', company: 'c-mine', tutor: 't1', mentor: 'm1');
        $this->position('other1', 's', null, company: 'c-other');

        $scope = new ScopeFacts('beatriz', false, [], [], [], [new CompanyRef('c-mine', 'Alfa S.L.', true)]);
        $view  = $this->build($scope);

        self::assertSame([Task::COMPANY_WITHOUT_WORKERS], array_map(static fn (Task $t): string => $t->key, $view->tasks));
        self::assertSame(2, $view->tasks[0]->count, 'los 2 puestos de su empresa, no los 3 de la estancia');
        self::assertSame('Alfa S.L.', $view->tasks[0]->subject);
        self::assertCount(1, $view->companies);
        self::assertSame([1, 1], [$view->companies[0]->freePositions, $view->companies[0]->assignedPositions]);
        self::assertSame([], $view->stays);
    }

    public function testACompanyWithWorkersGeneratesNoTask(): void
    {
        $this->stay('s', -10, 80, [$this->daw]);
        $this->position('mine1', 's', null, company: 'c-mine');

        $view = $this->build(new ScopeFacts('beatriz', false, [], [], [], [new CompanyRef('c-mine', 'Alfa S.L.', false)]));

        self::assertSame([], $view->tasks);
        self::assertCount(1, $view->companies);
    }

    // ── Tutor/a dual docente ─────────────────────────────────────────────────

    public function testATutorSeesTheirTutorshipsWithTheActionThatTheyNeed(): void
    {
        $this->stay('s', 3, 93, [$this->daw]);
        foreach (['u', 'r', 'd', 'x'] as $id) {
            $this->student('s', $id, $this->daw);
        }
        $this->position('p-unsigned', 's', 'u', tutor: 'laura', mentor: 'm1', state: TrainingPositionState::DONE, signed: false);
        $this->position('p-pending', 's', 'r', tutor: 'laura', mentor: 'm1', state: TrainingPositionState::PENDING);
        $this->position('p-done', 's', 'd', tutor: 'laura', mentor: 'm1', state: TrainingPositionState::DONE, signed: true);
        $this->position('p-other', 's', 'x', tutor: 'someone-else', mentor: 'm1');

        $view = $this->build(new ScopeFacts('laura', false, [], [], [], []));

        self::assertFalse($view->emptyScope);
        $actions = array_map(static fn (TutorshipRow $t): string => $t->action, $view->tutorships);
        self::assertSame([TutorshipRow::ACTION_SIGN, TutorshipRow::ACTION_REGISTER, TutorshipRow::ACTION_DONE], $actions);
        self::assertSame(Severity::Critical, $view->tutorships[0]->severity);
        self::assertNull($view->tutorships[2]->severity);

        $task = $this->task($view, Task::MY_UNSIGNED);
        self::assertSame(1, $task->count);
        self::assertSame(Severity::Critical, $task->severity);
    }

    public function testAManagersTutorshipsAreNotCountedTwice(): void
    {
        $this->stay('s', 3, 93, [$this->daw]);
        $this->student('s', 'u', $this->daw);
        $this->position('p1', 's', 'u', tutor: 'diego', mentor: 'm1', state: TrainingPositionState::DONE, signed: false);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        self::assertNull($this->find($view, Task::MY_UNSIGNED));
        self::assertSame(1, $this->task($view, Task::REGISTERED_UNSIGNED)->count);
    }

    // ── Sin responsabilidades ────────────────────────────────────────────────

    public function testSomeoneWithoutResponsibilitiesGetsAnEmptyScopeInsteadOfAllClear(): void
    {
        $this->stay('s', -10, 80, [$this->daw]);
        $this->student('s', 'a', $this->daw);

        $view = $this->build(new ScopeFacts('nobody', false, [], [], [], []));

        self::assertTrue($view->emptyScope);
        self::assertSame([], $view->tasks);
        self::assertSame([], $view->stays);
        self::assertSame([], $view->milestones);
    }

    // ── Próximos hitos ───────────────────────────────────────────────────────

    public function testMilestonesListStartsEndsSignaturesAndEndsOfPriority(): void
    {
        $this->stay('s', 10, 100, [$this->daw, $this->smr]);
        $this->student('s', 'd1', $this->daw);
        $this->position('p1', 's', 'd1', tutor: 't1', mentor: 'm1', state: TrainingPositionState::DONE, signed: false);
        $this->position('free', 's', null, priorityLevel: 'lvl-prog-smr',priorityUntil: '+20 days');
        $this->stay('ending', -60, 14, [$this->daw]);

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        $byKey = [];
        foreach ($view->milestones as $m) {
            $byKey[$m->key . '|' . $m->stayId] = $m;
        }
        self::assertArrayHasKey(Milestone::STAY_STARTS . '|s', $byKey);
        self::assertArrayHasKey(Milestone::STAY_ENDS . '|ending', $byKey);
        self::assertSame(1, $byKey[Milestone::SIGNATURE_DUE . '|s']->count);
        self::assertSame('SMR · 2.º', $byKey[Milestone::PRIORITY_ENDS . '|s']->subject);
        self::assertArrayNotHasKey(Milestone::STAY_ENDS . '|s', $byKey, 'termina dentro de 100 días: fuera de los 30');

        $dates = array_map(static fn (Milestone $m): string => $m->date->format('Y-m-d'), $view->milestones);
        $sorted = $dates;
        sort($sorted);
        self::assertSame($sorted, $dates, 'ordenados por fecha');
    }

    // ── Tarjetas y embudo ────────────────────────────────────────────────────

    public function testTheCardsShowTheProgressAndTheNextStepOfEachStay(): void
    {
        $this->stay('s', -46, 45, [$this->daw]);
        foreach (['a', 'b', 'c', 'd'] as $id) {
            $this->student('s', $id, $this->daw);
        }
        $this->position('p1', 's', 'a', tutor: 't1', mentor: 'm1', state: TrainingPositionState::DONE, signed: true);
        $this->position('p2', 's', 'b', tutor: 't1', mentor: null);

        $card = $this->build($this->scope('diego', manage: [$this->daw]))->stays[0];

        self::assertTrue($card->current);
        self::assertSame(51, $card->elapsedPercent, '46 de 91 días');
        self::assertSame(
            [['key' => 'with_position', 'done' => 2, 'total' => 4], ['key' => 'with_tutors', 'done' => 1, 'total' => 2], ['key' => 'registered', 'done' => 1, 'total' => 2], ['key' => 'signed', 'done' => 1, 'total' => 2]],
            $card->steps,
        );
        self::assertSame(['key' => 'assign', 'filter' => 'sin-puesto', 'count' => 2], $card->next);
        self::assertSame(Severity::Critical, $card->worst);
    }

    public function testCardsListTheCurrentStaysFirstAndThenTheUpcomingOnes(): void
    {
        $this->stay('later', 40, 130, [$this->daw]);
        $this->stay('sooner', 10, 100, [$this->daw]);
        $this->stay('current-b', -10, 60, [$this->daw]);
        $this->stay('current-a', -30, 20, [$this->daw]);
        foreach (['later', 'sooner', 'current-b', 'current-a'] as $id) {
            $this->student($id, $id . '-s', $this->daw);
        }

        $view = $this->build($this->scope('diego', manage: [$this->daw]));

        self::assertSame(['current-a', 'current-b', 'sooner', 'later'], array_map(static fn ($c): string => $c->id, $view->stays));
    }

    public function testTheFunnelAddsUpTheStaysOfTheChosenMode(): void
    {
        $this->stay('current', -10, 80, [$this->daw]);
        $this->stay('upcoming', 30, 120, [$this->daw]);
        $this->student('current', 'a', $this->daw);
        $this->student('current', 'b', $this->daw);
        $this->student('upcoming', 'c', $this->daw);
        $this->position('p1', 'current', 'a', tutor: 't1', mentor: 'm1');

        $scope = $this->scope('diego', manage: [$this->daw]);

        $active   = $this->build($scope, null, DashboardBuilder::FUNNEL_ACTIVE)->funnel;
        $upcoming = $this->build($scope, null, DashboardBuilder::FUNNEL_UPCOMING)->funnel;

        self::assertSame(['key' => 'with_position', 'done' => 1, 'total' => 3], $active[0]);
        self::assertSame(['key' => 'with_position', 'done' => 0, 'total' => 1], $upcoming[0]);
    }

    // ── Cobertura por enseñanza ──────────────────────────────────────────────

    public function testCoverageIsOnlyShownToTheAdministrationAndToWhoHeadsAFamily(): void
    {
        $this->stay('s', -10, 80, [$this->daw, $this->smr]);
        $this->student('s', 'd1', $this->daw);
        $this->student('s', 's1', $this->smr);

        $coordinator = $this->build($this->scope('diego', manage: [$this->daw]));
        $head        = $this->build($this->scope('head-inf', manage: [$this->daw, $this->smr], headed: [$this->daw, $this->smr]));
        $admin       = $this->build($this->scope('admin', admin: true));

        self::assertSame([], $coordinator->coverage);
        self::assertCount(2, $head->coverage);
        self::assertCount(2, $admin->coverage);
    }

    public function testCoverageIsOrderedByRiskAndCountsTheProcess(): void
    {
        $this->stay('urgent', -5, 85, [$this->daw]);
        $this->stay('calm', 60, 150, [$this->smr]);
        $this->student('urgent', 'd1', $this->daw);
        $this->student('urgent', 'd2', $this->daw);
        $this->student('calm', 's1', $this->smr);
        $this->position('p1', 'urgent', 'd1', tutor: 't1', mentor: 'm1', state: TrainingPositionState::DONE, signed: true);

        $rows = $this->build($this->scope('admin', admin: true))->coverage;

        self::assertSame(['DAW', 'SMR'], array_map(static fn ($r): string => $r->programmeName, $rows));
        self::assertSame([2, 1, 1, 1, 1], [$rows[0]->students, $rows[0]->withoutPosition, $rows[0]->bothTutors, $rows[0]->registered, $rows[0]->signed]);
        self::assertSame(Severity::Critical, $rows[0]->risk);
        self::assertSame(Severity::Info, $rows[1]->risk);
    }

    // ── Reparto por docente ──────────────────────────────────────────────────

    public function testTheWorkloadShowsHowManyStudentsEachTeacherShouldHave(): void
    {
        $this->stay('s', -10, 80, [$this->daw], calculateByTeacher: true);
        foreach (['a', 'b', 'c', 'd'] as $id) {
            $this->student('s', $id, $this->daw, group: 'g1');
        }
        $this->position('p1', 's', 'a', tutor: 'laura', mentor: 'm1');
        $this->hours['s']['g1'] = ['laura' => 30, 'pedro' => 10];

        $view = $this->build(new ScopeFacts('laura', false, [], [], [new GroupRef('g1', 'DAW1A', 'prog-daw')], []));

        self::assertCount(1, $view->workload);
        $row = $view->workload[0];
        self::assertSame(['G1', 3, 1], [$row->groupName, $row->expected, $row->assigned], 'le corresponden 3 de 4 y tiene 1');
    }

    public function testNoWorkloadRowWithoutHoursOrTutorships(): void
    {
        $this->stay('s', -10, 80, [$this->daw], calculateByTeacher: true);
        $this->student('s', 'a', $this->daw, group: 'g1');
        $this->hours['s']['g1'] = ['pedro' => 10];

        $view = $this->build(new ScopeFacts('laura', false, [], [], [new GroupRef('g1', 'DAW1A', 'prog-daw')], []));

        self::assertSame([], $view->workload);
    }

    // ── Ayudantes ────────────────────────────────────────────────────────────

    /** @param list<ProgrammeRef> $programmes */
    private function stay(string $id, int $startOffset, int $endOffset, array $programmes, bool $calculateByTeacher = false): void
    {
        $this->stays[$id] = new StayFacts(
            $id,
            'Estancia ' . $id,
            $programmes,
            $this->today->modify(\sprintf('%+d days', $startOffset)),
            $this->today->modify(\sprintf('%+d days', $endOffset)),
            $calculateByTeacher,
            // Cada enseñanza de prueba aporta su nivel por defecto («lvl-<enseñanza>»).
            array_map(static fn (ProgrammeRef $p): string => 'lvl-' . $p->id, $programmes),
            array_combine(
                array_map(static fn (ProgrammeRef $p): string => 'lvl-' . $p->id, $programmes),
                array_map(static fn (ProgrammeRef $p): string => $p->name . ' · 2.º', $programmes),
            ),
        );
    }

    private function student(string $stayId, string $id, ProgrammeRef $programme, string $group = 'g', string $level = 'lvl'): void
    {
        $this->students[$stayId][$id] = new StudentFacts($id, [new LevelFacts($level . '-' . $programme->id, $programme->id, $group, strtoupper($group))]);
        if ($level !== 'lvl') {
            $this->students[$stayId][$id] = new StudentFacts($id, [new LevelFacts($level, $programme->id, $group, strtoupper($group))]);
        }
    }

    /** Consta en la ficha del estudiante que no dispone de NUSS. */
    private function missingNuss(string $stayId, string $id): void
    {
        $student = $this->students[$stayId][$id];
        $this->students[$stayId][$id] = new StudentFacts($student->id, $student->levels, true);
    }

    /** @param list<string> $levels */
    private function position(
        string $id,
        string $stayId,
        ?string $student,
        ?string $tutor = null,
        ?string $mentor = null,
        TrainingPositionState $state = TrainingPositionState::DRAFT,
        bool $signed = false,
        ?string $company = null,
        ?string $priorityLevel = null,
        ?string $priorityUntil = null,
        array $levels = [],
    ): void {
        $this->positions[] = new PositionFacts(
            $id,
            $stayId,
            $state,
            $signed,
            $student,
            $tutor,
            $mentor,
            $company,
            null,
            null,
            null,
            $priorityUntil !== null ? $this->today->modify($priorityUntil) : null,
            $priorityLevel,
            $student === null ? $levels : [],
        );
    }

    /** @param list<ProgrammeRef> $manage @param list<ProgrammeRef> $headed */
    private function scope(string $userId, bool $admin = false, array $manage = [], array $headed = []): ScopeFacts
    {
        $manageMap = [];
        foreach ($manage as $p) {
            $manageMap[$p->id] = $p->name;
        }

        return new ScopeFacts(
            $userId,
            $admin,
            $manageMap,
            array_map(static fn (ProgrammeRef $p): string => $p->id, $headed),
            [],
            [],
            array_map(static fn (ProgrammeRef $p): string => 'lvl-' . $p->id, $manage),
        );
    }

    private function build(ScopeFacts $scope, ?AttentionThresholds $thresholds = null, string $funnelMode = DashboardBuilder::FUNNEL_ACTIVE): DashboardView
    {
        $facts = new DashboardFacts($this->today, $scope, array_values($this->stays), $this->positions, $this->students, $this->hours);

        return (new DashboardBuilder())->build($facts, $thresholds ?? new AttentionThresholds(), $funnelMode);
    }

    private function find(DashboardView $view, string $key): ?Task
    {
        foreach ($view->tasks as $task) {
            if ($task->key === $key) {
                return $task;
            }
        }

        return null;
    }

    private function task(DashboardView $view, string $key): Task
    {
        $task = $this->find($view, $key);
        self::assertNotNull($task, "no hay ninguna tarea «{$key}»");

        return $task;
    }
}
