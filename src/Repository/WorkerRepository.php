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

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('c', 'w')
            ->from(Company::class, 'c')
            ->join('c.workers', 'w')
            ->orderBy('w.name.lastName', 'ASC')
            ->addOrderBy('w.name.firstName', 'ASC');

        // El identificador de Company es un UUID; el binding de entidades en una
        // cláusula IN no resuelve correctamente, así que se compara por id.
        $conds = [];
        foreach ($companies as $i => $company) {
            $conds[]                   = "c.id = :cid_{$i}";
            $qb->setParameter("cid_{$i}", $company->getId(), 'uuid');
        }
        $qb->where(implode(' OR ', $conds));

        /** @var list<Company> $rows */
        $rows = $qb->getQuery()->getResult();

        $grouped = [];
        foreach ($rows as $company) {
            $grouped[$company->getId()->toRfc4122()] = $company->getWorkers()->toArray();
        }

        return $grouped;
    }
}
