<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Workcenter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Workcenter>
 */
class WorkcenterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workcenter::class);
    }

    /** @return Workcenter[] */
    public function findByCompanyOrderedByName(Company $company): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.company = :company')
            ->setParameter('company', $company->getId(), 'uuid')
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCompanyAndId(Company $company, string $id): ?Workcenter
    {
        return $this->createQueryBuilder('w')
            ->where('w.company = :company')
            ->andWhere('w.id = :id')
            ->setParameter('company', $company->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
