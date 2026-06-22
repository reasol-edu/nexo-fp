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

    /**
     * Number of levels per programme, keyed by programme UUID (RFC4122). Single grouped query.
     *
     * @param  Programme[] $programmes
     * @return array<string, int>
     */
    public function countByProgramme(array $programmes): array
    {
        if ($programmes === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('py')
            ->select('IDENTITY(py.programme) AS pid', 'COUNT(py.id) AS cnt')
            ->where('py.programme IN (:programmes)')
            ->setParameter('programmes', $programmes)
            ->groupBy('py.programme')
            ->getQuery()
            ->getScalarResult();

        $uuidNorm = [];
        foreach ($programmes as $programme) {
            $rfc = $programme->getId()->toRfc4122();
            $uuidNorm[$rfc]                          = $rfc;
            $uuidNorm[$programme->getId()->toBinary()] = $rfc;
        }

        $map = [];
        foreach ($rows as $row) {
            $key = $uuidNorm[(string) $row['pid']] ?? (string) $row['pid'];
            $map[$key] = (int) $row['cnt'];
        }

        return $map;
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
