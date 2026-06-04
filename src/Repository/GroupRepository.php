<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    /** @return Group[] */
    public function findByLevelOrderedByName(ProgrammeYear $level): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.programmeYear = :level')
            ->setParameter('level', $level->getId(), 'uuid')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByLevelAndId(ProgrammeYear $level, string $id): ?Group
    {
        return $this->createQueryBuilder('g')
            ->where('g.programmeYear = :level')
            ->andWhere('g.id = :id')
            ->setParameter('level', $level->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns all groups (with students eagerly loaded) that belong to ProgrammeYears
     * of the given programme. Ordered by level name → group name → student surname.
     *
     * @return Group[]
     */
    public function findByProgrammeWithStudents(Programme $programme): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.students', 's')->addSelect('s')
            ->join('g.programmeYear', 'py')
            ->where('py.programme = :programme')
            ->setParameter('programme', $programme->getId(), 'uuid')
            ->orderBy('py.name', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->addOrderBy('s.name.lastName', 'ASC')
            ->addOrderBy('s.name.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Group[] */
    public function findByActiveYearOfCentreOrderedByName(EducationalCentre $centre): array
    {
        if ($centre->getActiveAcademicYear() === null) {
            return [];
        }

        return $this->createQueryBuilder('g')
            ->join('g.programmeYear', 'py')
            ->join('py.programme', 'prog')
            ->join('prog.professionalFamily', 'f')
            ->where('f.academicYear = :year')
            ->setParameter('year', $centre->getActiveAcademicYear()->getId(), 'uuid')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
