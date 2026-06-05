<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\CompanyAudit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompanyAudit>
 */
class CompanyAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompanyAudit::class);
    }

    /**
     * @return CompanyAudit[]
     */
    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.company = :company')
            ->setParameter('company', $company->getId(), 'uuid')
            ->orderBy('a.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
