<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfessionalFamily>
 */
class ProfessionalFamilyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfessionalFamily::class);
    }

    public function isFamilyHeadOfProgramme(Teacher $teacher, Programme $programme): bool
    {
        return $this->createQueryBuilder('pf')
            ->select('1')
            ->join(Programme::class, 'p', 'WITH', 'p.professionalFamily = pf')
            ->where('p.id = :programme')
            ->andWhere('pf.head = :teacher')
            ->setParameter('programme', $programme->getId(), 'uuid')
            ->setParameter('teacher', $teacher->getId(), 'uuid')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    public function isFamilyHeadInCentre(Teacher $teacher, EducationalCentre $centre): bool
    {
        return (int) $this->createQueryBuilder('pf')
            ->select('COUNT(pf.id)')
            ->join('pf.academicYear', 'ay')
            ->where('pf.head = :teacher')
            ->andWhere('ay.educationalCentre = :centre')
            ->setParameter('teacher', $teacher->getId(), 'uuid')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /** @return Query<null, ProfessionalFamily> */
    public function createByAcademicYearFilteredQuery(AcademicYear $year, string $search = ''): Query
    {
        $qb = $this->createQueryBuilder('pf')
            ->where('pf.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid')
            ->orderBy('pf.name', 'ASC');

        if ($search !== '') {
            $qb->andWhere('LOWER(pf.name) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery();
    }

    public function findByYearAndId(AcademicYear $year, string $id): ?ProfessionalFamily
    {
        return $this->createQueryBuilder('pf')
            ->where('pf.academicYear = :year')
            ->andWhere('pf.id = :id')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return ProfessionalFamily[] */
    public function findByAcademicYearFiltered(AcademicYear $year, string $search = ''): array
    {
        $qb = $this->createQueryBuilder('pf')
            ->where('pf.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid')
            ->orderBy('pf.name', 'ASC');

        if ($search !== '') {
            $qb->andWhere('LOWER(pf.name) LIKE LOWER(:search)')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Query<null, ProfessionalFamily> */
    public function findNoneQuery(): Query
    {
        return $this->createQueryBuilder('pf')->where('1 = 0')->getQuery();
    }
}
