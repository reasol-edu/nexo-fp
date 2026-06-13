<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Worker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Worker>
 */
class WorkerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Worker::class);
    }

    public function findByNationalIdNumber(string $nationalIdNumber): ?Worker
    {
        return $this->findOneBy(['nationalIdNumber' => $nationalIdNumber]);
    }

    /**
     * Carga en una sola consulta los trabajadores de las empresas dadas,
     * agrupados por UUID de empresa y ordenados por apellidos y nombre.
     *
     * @param  list<Company>                $companies
     * @return array<string, list<Worker>>
     */
    public function findGroupedByCompanies(array $companies): array
    {
        if ($companies === []) {
            return [];
        }

        /** @var list<Company> $rows */
        $rows = $this->getEntityManager()->createQuery(
            'SELECT c, w
             FROM ' . Company::class . ' c
             JOIN c.workers w
             WHERE c IN (:companies)
             ORDER BY w.name.lastName ASC, w.name.firstName ASC'
        )->setParameter('companies', $companies)->getResult();

        $grouped = [];
        foreach ($rows as $company) {
            $grouped[$company->getId()->toRfc4122()] = $company->getWorkers()->toArray();
        }

        return $grouped;
    }
}
