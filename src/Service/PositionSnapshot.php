<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Stay;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Repository\ProgrammeYearRepository;
use App\Repository\TeacherRepository;
use App\Repository\TrainingPositionRepository;
use App\Repository\WorkcenterRepository;

/**
 * Captura los datos de un puesto formativo antes de eliminarlo y, si procede, lo vuelve a crear
 * («Deshacer»). La captura solo contiene identificadores y valores simples; al restaurar se vuelve a
 * resolver cada referencia dentro de la estancia y de su centro, así que un dato manipulado no puede
 * enlazar con nada ajeno. No hace flush ni comprueba permisos.
 */
final class PositionSnapshot
{
    public function __construct(
        private readonly WorkcenterRepository $workcenters,
        private readonly ProgrammeYearRepository $programmeYears,
        private readonly TeacherRepository $teachers,
        private readonly TrainingPositionRepository $positions,
    ) {}

    /** @return array<string, mixed> */
    public function capture(TrainingPosition $position): array
    {
        return [
            'details'     => $position->getDetails(),
            'state'       => $position->getState()->value,
            'signed'      => $position->isSigned(),
            'student'     => $position->getStudent()?->getId()->toRfc4122(),
            'tutor'       => $position->getAcademicTutor()?->getId()->toRfc4122(),
            'mentor'      => $position->getWorkplaceMentor()?->getId()->toRfc4122(),
            'workcenter'  => $position->getWorkcenter()?->getId()->toRfc4122(),
            'levels'      => array_map(
                static fn ($py): string => $py->getId()->toRfc4122(),
                $position->getProgrammeYears()->toArray(),
            ),
            'priority'    => $position->getPriorityProgramme()?->getId()->toRfc4122(),
            'priorityEnd' => $position->getPriorityUntil()?->format('Y-m-d'),
            'start'       => $position->getStartDate()?->format('Y-m-d'),
            'end'         => $position->getEndDate()?->format('Y-m-d'),
        ];
    }

    /**
     * Crea de nuevo el puesto en la estancia. El estudiante, el tutor y el tutor de empresa solo se
     * recuperan si siguen siendo válidos (el estudiante, sin otro puesto en la estancia).
     *
     * @param array<string, mixed> $data
     */
    public function restore(Stay $stay, array $data): TrainingPosition
    {
        $position = (new TrainingPosition())
            ->setStay($stay)
            ->setDetails(\is_string($data['details'] ?? null) ? $data['details'] : null);

        $centre     = $stay->getAcademicYear()->getEducationalCentre();
        $workcenter = \is_string($data['workcenter'] ?? null) ? $this->workcenters->findByCentreAndId($centre, $data['workcenter']) : null;
        $position->setWorkcenter($workcenter);

        foreach ((array) ($data['levels'] ?? []) as $levelId) {
            if (\is_string($levelId) && ($level = $this->programmeYears->findByStayAndId($stay, $levelId)) !== null) {
                $position->addProgrammeYear($level);
            }
        }

        if (\is_string($data['priority'] ?? null)) {
            foreach ($stay->getProgrammes() as $programme) {
                if ($programme->getId()->toRfc4122() === $data['priority']) {
                    $position->setPriority($programme, $this->date($data['priorityEnd'] ?? null));
                }
            }
        }

        $position->setStartDate($this->date($data['start'] ?? null))->setEndDate($this->date($data['end'] ?? null));

        $student = null;
        if (\is_string($data['student'] ?? null)) {
            foreach ($stay->getStudents() as $candidate) {
                if ($candidate->getId()->toRfc4122() === $data['student']
                    && $this->positions->findByStayAndStudent($stay, $candidate) === null
                ) {
                    $student = $candidate;
                    break;
                }
            }
        }
        $position->setStudent($student);

        if ($student !== null) {
            if (\is_string($data['tutor'] ?? null)) {
                $position->setAcademicTutor($this->teachers->findById($data['tutor']));
            }
            if (\is_string($data['mentor'] ?? null) && $workcenter !== null) {
                foreach ($workcenter->getCompany()->getWorkers() as $worker) {
                    if ($worker->getId()->toRfc4122() === $data['mentor']) {
                        $position->setWorkplaceMentor($worker);
                        break;
                    }
                }
            }

            // Solo se recupera el estado si siguen estando los dos tutores que exige la máquina de estados.
            $state = TrainingPositionState::tryFrom((string) ($data['state'] ?? ''));
            if ($state !== null && $state !== TrainingPositionState::DRAFT
                && $position->getAcademicTutor() !== null && $position->getWorkplaceMentor() !== null
            ) {
                $position->setState($state);
                $position->setSigned($state === TrainingPositionState::DONE && (bool) ($data['signed'] ?? false));
            }
        }

        return $position;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false ? $date : null;
    }
}
