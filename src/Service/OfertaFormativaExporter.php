<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AcademicYear;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;

class OfertaFormativaExporter
{
    public function __construct(
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly ProgrammeYearRepository $levels,
        private readonly GroupRepository $groups,
    ) {}

    /** @return array<string, mixed> */
    public function export(AcademicYear $year): array
    {
        $data = [
            'exported_at'   => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'academic_year' => $year->getName(),
            'families'      => [],
        ];

        foreach ($this->families->findByAcademicYearFiltered($year) as $family) {
            $familyData = [
                'name'      => $family->getName(),
                'head'      => $family->getHead()?->getUsername(),
                'programmes' => [],
            ];

            foreach ($this->programmes->findByFamilyOrderedByName($family) as $programme) {
                $coords = array_map(
                    static fn($t) => $t->getUsername(),
                    $programme->getCoordinators()->toArray(),
                );
                sort($coords);

                $programmeData = [
                    'name'         => $programme->getName(),
                    'details'      => $programme->getDetails(),
                    'coordinators' => $coords,
                    'levels'       => [],
                ];

                foreach ($this->levels->findByProgrammeOrderedByName($programme) as $level) {
                    $levelData = [
                        'name'    => $level->getName(),
                        'details' => $level->getDetails(),
                        'groups'  => [],
                    ];

                    foreach ($this->groups->findByLevelOrderedByName($level) as $group) {
                        $teachers = array_map(
                            static fn($t) => $t->getUsername(),
                            $group->getTeachers()->toArray(),
                        );
                        sort($teachers);

                        $tutors = array_map(
                            static fn($t) => $t->getUsername(),
                            $group->getTutors()->toArray(),
                        );
                        sort($tutors);

                        $levelData['groups'][] = [
                            'name'     => $group->getName(),
                            'details'  => $group->getDetails(),
                            'teachers' => $teachers,
                            'tutors'   => $tutors,
                        ];
                    }

                    $programmeData['levels'][] = $levelData;
                }

                $familyData['programmes'][] = $programmeData;
            }

            $data['families'][] = $familyData;
        }

        return $data;
    }
}
