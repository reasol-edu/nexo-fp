<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProgrammeYear>
 */
class ProgrammeYearRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProgrammeYear::class);
    }

    /** @return ProgrammeYear[] */
    public function findByProgrammeOrderedByName(Programme $programme): array
    {
        return $this->createQueryBuilder('py')
            ->where('py.programme = :programme')
            ->setParameter('programme', $programme->getId(), 'uuid')
            ->orderBy('py.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByProgrammeAndId(Programme $programme, string $id): ?ProgrammeYear
    {
        return $this->createQueryBuilder('py')
            ->where('py.programme = :programme')
            ->andWhere('py.id = :id')
            ->setParameter('programme', $programme->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
