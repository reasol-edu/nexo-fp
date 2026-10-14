<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AcademicYear;
use App\Repository\GroupRepository;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\ProgrammeYearRepository;
use App\Repository\StayRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Elimina un curso académico con todo lo que cuelga de él: estancias (con sus puestos), grupos, niveles,
 * enseñanzas, familias profesionales y la pertenencia de docentes. El alumnado y los docentes no se borran:
 * solo dejan de estar en los grupos y en el curso. Todo ocurre en una transacción; si algo falla, no se
 * borra nada.
 *
 * No comprueba permisos ni que el curso no sea el activo: eso corresponde a quien lo llama.
 */
final class AcademicYearEraser
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StayRepository $stays,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly ProgrammeYearRepository $levels,
        private readonly GroupRepository $groups,
    ) {}

    public function erase(AcademicYear $year): void
    {
        $this->em->wrapInTransaction(function () use ($year): void {
            // Primero las estancias: sus enseñanzas están protegidas (RESTRICT) mientras haya estancias.
            foreach ($this->stays->findByYear($year) as $stay) {
                $this->em->remove($stay); // sus puestos se eliminan con ella (orphanRemoval)
            }
            $this->em->flush();

            $familiesList = $this->families->findByAcademicYearFiltered($year);
            foreach ($familiesList as $family) {
                foreach ($this->programmes->findByFamilyOrderedByName($family) as $programme) {
                    foreach ($this->levels->findByProgrammeOrderedByName($programme) as $level) {
                        foreach ($this->groups->findByLevelOrderedByName($level) as $group) {
                            // La relación con el alumnado la posee el estudiante: hay que soltarla a mano.
                            foreach ($group->getStudents()->toArray() as $student) {
                                $student->removeGroup($group);
                            }
                            $this->em->remove($group);
                        }
                        $this->em->flush();
                        $this->em->remove($level);
                    }
                    $this->em->flush();
                    $this->em->remove($programme);
                }
                $this->em->flush();
                $this->em->remove($family);
            }
            $this->em->flush();

            $this->em->remove($year); // retira también la pertenencia de docentes
            $this->em->flush();
        });
    }
}
