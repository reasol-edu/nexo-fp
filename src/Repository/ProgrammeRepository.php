<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AcademicYear;
use App\Entity\Programme;
use App\Entity\ProfessionalFamily;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Programme>
 */
class ProgrammeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Programme::class);
    }

    /** @return Programme[] */
    /** @return Programme[] */
    public function findByAcademicYearOrderedByFamilyAndName(AcademicYear $year): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.professionalFamily', 'f')
            ->where('p.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid')
            ->orderBy('f.name', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByAcademicYearAndId(AcademicYear $year, string $id): ?Programme
    {
        return $this->createQueryBuilder('p')
            ->where('p.academicYear = :year')
            ->andWhere('p.id = :id')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Programme[] */
    public function findByFamilyOrderedByName(ProfessionalFamily $family): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.professionalFamily = :family')
            ->setParameter('family', $family->getId(), 'uuid')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFamilyAndId(ProfessionalFamily $family, string $id): ?Programme
    {
        return $this->createQueryBuilder('p')
            ->where('p.professionalFamily = :family')
            ->andWhere('p.id = :id')
            ->setParameter('family', $family->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isCoordinatorOf(Teacher $teacher, Programme $programme): bool
    {
        return $this->createQueryBuilder('p')
            ->select('1')
            ->join('p.coordinators', 'c')
            ->where('p.id = :programme')
            ->andWhere('c.id = :teacher')
            ->setParameter('programme', $programme->getId(), 'uuid')
            ->setParameter('teacher', $teacher->getId(), 'uuid')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }
}
