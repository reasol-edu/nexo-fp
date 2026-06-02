<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /** @return Query<null, Company> */
    public function createByCentreOrderedByNameQuery(EducationalCentre $centre): Query
    {
        return $this->createQueryBuilder('c')
            ->where('c.educationalCentre = :centre')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->orderBy('c.name', 'ASC')
            ->getQuery();
    }

    public function findByIdAndCentre(string $id, EducationalCentre $centre): ?Company
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->andWhere('c.educationalCentre = :centre')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByVatNumberAndCentre(string $vatNumber, EducationalCentre $centre): ?Company
    {
        return $this->createQueryBuilder('c')
            ->where('c.vatNumber = :vatNumber')
            ->andWhere('c.educationalCentre = :centre')
            ->setParameter('vatNumber', $vatNumber)
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasLiaisonInCentre(Teacher $teacher, EducationalCentre $centre): bool
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.liaisons', 'l')
            ->where('l = :teacher')
            ->andWhere('c.educationalCentre = :centre')
            ->setParameter('teacher', $teacher)
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
