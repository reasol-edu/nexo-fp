<?php

declare(strict_types=1);

namespace App\Tests\Integration\Workflow;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Entity\Worker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\WorkflowInterface;

class TrainingPositionWorkflowTest extends KernelTestCase
{
    private WorkflowInterface $workflow;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var WorkflowInterface $workflow */
        $workflow = self::getContainer()->get('state_machine.training_position');
        $this->workflow = $workflow;
    }

    private function withTutors(TrainingPosition $position): TrainingPosition
    {
        return $position
            ->setAcademicTutor(new Teacher(new PersonName('Ana', 'Tutora')))
            ->setWorkplaceMentor(new Worker(new PersonName('Carlos', 'Mentor')));
    }

    // ── guarda: salir de borrador exige ambos tutores ───────────────────────────

    public function testDraftWithoutTutorsCannotAdvance(): void
    {
        $position = new TrainingPosition();

        self::assertSame(TrainingPositionState::DRAFT, $position->getState());
        self::assertFalse($this->workflow->can($position, 'to_pending'));
        self::assertFalse($this->workflow->can($position, 'to_done'));
    }

    public function testDraftWithOnlyOneTutorCannotAdvance(): void
    {
        $position = (new TrainingPosition())->setAcademicTutor(new Teacher(new PersonName('Ana', 'Tutora')));

        self::assertFalse($this->workflow->can($position, 'to_pending'));
        self::assertFalse($this->workflow->can($position, 'to_done'));
    }

    public function testDraftWithBothTutorsCanAdvance(): void
    {
        $position = $this->withTutors(new TrainingPosition());

        self::assertTrue($this->workflow->can($position, 'to_pending'));
        self::assertTrue($this->workflow->can($position, 'to_done'));
    }

    // ── aplicación de transiciones ───────────────────────────────────────────────

    public function testApplyMovesDraftToPending(): void
    {
        $position = $this->withTutors(new TrainingPosition());

        $this->workflow->apply($position, 'to_pending');

        self::assertSame(TrainingPositionState::PENDING, $position->getState());
    }

    public function testApplyMovesDraftDirectlyToDone(): void
    {
        $position = $this->withTutors(new TrainingPosition());

        $this->workflow->apply($position, 'to_done');

        self::assertSame(TrainingPositionState::DONE, $position->getState());
    }

    // ── el retroceso a borrador no exige tutores ────────────────────────────────

    public function testCanReturnToDraftWithoutTutors(): void
    {
        $position = (new TrainingPosition())->setState(TrainingPositionState::DONE);

        self::assertTrue($this->workflow->can($position, 'to_draft'));
        self::assertFalse($this->workflow->can($position, 'to_pending'));
    }

    public function testDraftHasNoSelfTransition(): void
    {
        $position = $this->withTutors(new TrainingPosition());

        // to_draft sale de PENDING/DONE; un borrador no puede transicionar a sí mismo.
        self::assertFalse($this->workflow->can($position, 'to_draft'));
    }
}
