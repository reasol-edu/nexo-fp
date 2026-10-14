<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Cambios de estado y de firma de un puesto formativo, compartidos por las acciones en línea
 * del detalle de la estancia, las acciones masivas y la pestaña «Firmas pendientes».
 * No hace flush ni comprueba permisos: eso corresponde a quien lo llama.
 */
final class PositionStateChanger
{
    public function __construct(
        #[Target('training_position')]
        private readonly WorkflowInterface $workflow,
    ) {}

    /**
     * Mueve el puesto a otro estado. Al salir de «Registrado» se retira la firma; `signed` solo
     * cuenta al pasar a «Registrado». Devuelve false si el estado no cambia o la máquina de
     * estados lo impide (por ejemplo, por falta de tutores).
     */
    public function transition(TrainingPosition $position, TrainingPositionState $target, bool $signed = false): bool
    {
        if ($position->getState() === $target) {
            return false;
        }

        $transition = 'to_' . strtolower($target->value);
        if (!$this->workflow->can($position, $transition)) {
            return false;
        }

        $this->workflow->apply($position, $transition);
        $position->setSigned($target === TrainingPositionState::DONE && $signed);

        return true;
    }

    /** Marca o quita la firma; solo tiene sentido en un puesto registrado en Séneca. */
    public function sign(TrainingPosition $position, bool $signed): bool
    {
        if ($position->getState() !== TrainingPositionState::DONE || $position->isSigned() === $signed) {
            return false;
        }

        $position->setSigned($signed);

        return true;
    }
}
