<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AcademicYear;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Repository\StayRepository;
use App\Repository\TrainingPositionRepository;
use Symfony\Component\Clock\ClockInterface;

final class PendingTasksProvider
{
    private const SIGNATURE_DUE_DAYS = 14;

    public function __construct(
        private readonly StayRepository $stayRepository,
        private readonly TrainingPositionRepository $trainingPositionRepository,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Merges position alerts and unassigned-student counters into a single
     * per-stay list ordered by end date (soonest first).
     *
     * @return list<array{stay: Stay, free: int, missing_tutor: int, missing_mentor: int, done_unsigned: int, students_without_position: int}>
     */
    public function findAlertsByStay(AcademicYear $year, ?Teacher $viewer): array
    {
        $alerts = [];

        foreach ($this->stayRepository->findPositionAlertsByStay($year, $viewer) as $row) {
            $alerts[$row['stay']->getId()->toRfc4122()] = $row + ['students_without_position' => 0];
        }

        foreach ($this->stayRepository->countStudentsWithoutPositionByStay($year, $viewer) as $row) {
            $id = $row['stay']->getId()->toRfc4122();
            if (isset($alerts[$id])) {
                $alerts[$id]['students_without_position'] = $row['students_without_position'];
            } else {
                $alerts[$id] = [
                    'stay'                      => $row['stay'],
                    'free'                      => 0,
                    'missing_tutor'             => 0,
                    'missing_mentor'            => 0,
                    'done_unsigned'             => 0,
                    'students_without_position' => $row['students_without_position'],
                ];
            }
        }

        $alerts = array_values($alerts);
        usort(
            $alerts,
            static fn (array $a, array $b): int => $a['stay']->getEndDate() <=> $b['stay']->getEndDate()
        );

        return $alerts;
    }

    /**
     * Typed pending items for the notification bell, ordered by stay end date.
     *
     * Types: signature_due (positions tutored by the teacher, unsigned, stay
     * ending within the next days), free_positions, missing_tutor,
     * missing_mentor and students_without_position (derived from the alerts).
     *
     * @return list<array{type: string, stay: Stay, count: int}>
     */
    public function findPendingForTeacher(AcademicYear $year, Teacher $teacher): array
    {
        $items = [];
        $today = $this->clock->now()->setTime(0, 0, 0);

        $positions = $this->trainingPositionRepository->findUnsignedByTutorWithStayEndingBetween(
            $teacher,
            $today,
            $today->modify(\sprintf('+%d days', self::SIGNATURE_DUE_DAYS)),
        );

        $byStay = [];
        foreach ($positions as $position) {
            $stay = $position->getStay();
            $id   = $stay->getId()->toRfc4122();
            $byStay[$id] ??= ['type' => 'signature_due', 'stay' => $stay, 'count' => 0];
            ++$byStay[$id]['count'];
        }
        $items = array_values($byStay);

        $typeMap = [
            'free'                      => 'free_positions',
            'missing_tutor'             => 'missing_tutor',
            'missing_mentor'            => 'missing_mentor',
            'students_without_position' => 'students_without_position',
        ];

        foreach ($this->findAlertsByStay($year, $teacher) as $alert) {
            foreach ($typeMap as $key => $type) {
                if ($alert[$key] > 0) {
                    $items[] = ['type' => $type, 'stay' => $alert['stay'], 'count' => $alert[$key]];
                }
            }
        }

        usort(
            $items,
            static fn (array $a, array $b): int => $a['stay']->getEndDate() <=> $b['stay']->getEndDate()
        );

        return $items;
    }
}
