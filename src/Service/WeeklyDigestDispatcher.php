<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\EducationalCentreRepository;
use App\Repository\TeacherRepository;

/**
 * Envía a cada docente con correo, una vez por semana, un único resumen con lo que tiene
 * pendiente en el curso activo de cada centro (los mismos avisos que la campana de la
 * aplicación). No envía nada a quien no tiene pendientes.
 */
final class WeeklyDigestDispatcher
{
    public function __construct(
        private readonly EducationalCentreRepository $centres,
        private readonly TeacherRepository $teachers,
        private readonly PendingTasksProvider $pending,
        private readonly StayNotifier $notifier,
    ) {}

    /** @return array{recipients: int} */
    public function dispatch(): array
    {
        $sent = 0;

        foreach ($this->centres->findAllWithActiveYear() as $centre) {
            $year = $centre->getActiveAcademicYear();
            if ($year === null) {
                continue;
            }

            foreach ($this->teachers->findWithEmailByAcademicYear($year) as $teacher) {
                $items = $this->pending->findPendingForTeacher($year, $teacher);
                if ($items !== [] && $this->notifier->sendWeeklyDigest($teacher, $centre, $items)) {
                    ++$sent;
                }
            }
        }

        return ['recipients' => $sent];
    }
}
