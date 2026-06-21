<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @param array{
     *   dateFrom?: string,
     *   dateTo?: string,
     *   userId?: string,
     *   centreId?: string,
     *   yearId?: string,
     *   actionType?: string,
     *   sort?: string,
     *   sortDir?: string,
     * } $filters
     * @return Query<null, ActivityLog>
     */
    public function createFilteredQuery(array $filters): Query
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.activeUser', 'u')
            ->leftJoin('l.realUser', 'r')
            ->leftJoin('l.academicYear', 'y')
            ->leftJoin('y.educationalCentre', 'c')
            ->addSelect('u', 'r', 'y', 'c');

        if (!empty($filters['dateFrom'])) {
            try {
                $from = new \DateTimeImmutable($filters['dateFrom']);
                $qb->andWhere('l.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
            } catch (\Exception) {
            }
        }

        if (!empty($filters['dateTo'])) {
            try {
                $to = new \DateTimeImmutable($filters['dateTo']);
                $qb->andWhere('l.createdAt <= :dateTo')->setParameter('dateTo', $to);
            } catch (\Exception) {
            }
        }

        if (!empty($filters['userId'])) {
            $qb->andWhere('u.id = :userId')->setParameter('userId', $filters['userId']);
        }

        if (!empty($filters['centreId'])) {
            $qb->andWhere('c.id = :centreId')->setParameter('centreId', $filters['centreId']);
        }

        if (!empty($filters['yearId'])) {
            $qb->andWhere('y.id = :yearId')->setParameter('yearId', $filters['yearId']);
        }

        if (!empty($filters['actionType'])) {
            $qb->andWhere('l.actionType = :actionType')->setParameter('actionType', $filters['actionType']);
        }

        $allowedSorts = ['createdAt' => 'l.createdAt', 'ip' => 'l.ip', 'actionType' => 'l.actionType'];
        $sort    = $allowedSorts[$filters['sort'] ?? ''] ?? 'l.createdAt';
        $sortDir = strtoupper($filters['sortDir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy($sort, $sortDir)->addOrderBy('l.id', $sortDir);

        return $qb->getQuery();
    }

    /** @return list<string> */
    public function findDistinctActionTypes(): array
    {
        /** @var list<array{actionType: string}> $rows */
        $rows = $this->createQueryBuilder('l')
            ->select('DISTINCT l.actionType')
            ->orderBy('l.actionType', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'actionType');
    }

    public function countToday(): int
    {
        $today = new \DateTimeImmutable('today');

        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
