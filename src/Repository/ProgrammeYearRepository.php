<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
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

        // `IN (:lista)` con entidades convierte los ids a texto y no coincide con los ids binarios
        // (MySQL, o SQLite creado con migraciones): se compara uno a uno con tipo 'uuid' explícito.
        $qb = $this->createQueryBuilder('py')
            ->select('IDENTITY(py.programme) AS pid', 'COUNT(py.id) AS cnt')
            ->groupBy('py.programme');
        $conditions = [];
        foreach (array_values($programmes) as $i => $item) {
            $conditions[] = 'py.programme = :programmes_' . $i;
            $qb->setParameter('programmes_' . $i, $item->getId(), 'uuid');
        }
        $rows = $qb->where(implode(' OR ', $conditions))
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

    /**
     * Niveles de todas las enseñanzas de la estancia, ordenados por enseñanza y nivel.
     *
     * @return list<ProgrammeYear>
     */
    public function findByStayOrderedByName(Stay $stay): array
    {
        return $this->createQueryBuilder('py')
            ->join('py.programme', 'p')->addSelect('p')
            ->where('EXISTS(SELECT 1 FROM ' . Stay::class . ' xs JOIN xs.programmes xp WHERE xs.id = :stay AND xp = p)')
            ->setParameter('stay', $stay->getId(), 'uuid')
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('py.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Un nivel por id, siempre que pertenezca a una enseñanza de la estancia. */
    public function findByStayAndId(Stay $stay, string $id): ?ProgrammeYear
    {
        return $this->createQueryBuilder('py')
            ->join('py.programme', 'p')
            ->where('py.id = :id')
            ->andWhere('EXISTS(SELECT 1 FROM ' . Stay::class . ' xs JOIN xs.programmes xp WHERE xs.id = :stay AND xp = p)')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('stay', $stay->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
