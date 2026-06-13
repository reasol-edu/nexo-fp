<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\TrainingPosition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * Un puesto solo puede salir del borrador cuando tiene asignados tanto el
 * tutor dual docente como el tutor dual de empresa.
 */
final class TrainingPositionGuardSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.training_position.guard.to_pending' => 'requireBothTutors',
            'workflow.training_position.guard.to_done'    => 'requireBothTutors',
        ];
    }

    /**
     * @param GuardEvent<TrainingPosition> $event
     */
    public function requireBothTutors(GuardEvent $event): void
    {
        $position = $event->getSubject();

        if ($position->getAcademicTutor() === null || $position->getWorkplaceMentor() === null) {
            $event->setBlocked(true, 'stays.error.state_requires_tutors');
        }
    }
}
