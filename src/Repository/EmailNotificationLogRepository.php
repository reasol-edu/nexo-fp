<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EducationalCentre;
use App\Entity\EmailNotificationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailNotificationLog>
 */
class EmailNotificationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailNotificationLog::class);
    }

    /**
     * @param ?EducationalCentre $centre null = todos los centros (y los correos sin centro)
     * @param array{
     *   search?: string,
     *   eventKey?: string,
     *   status?: string,
     *   dateFrom?: string,
     *   dateTo?: string,
     * } $filters
     *
     * @return Query<null, EmailNotificationLog>
     */
    public function createFilteredQuery(?EducationalCentre $centre, array $filters = []): Query
    {
        $qb = $this->createQueryBuilder('l')
            ->addSelect('r', 'c')
            ->leftJoin('l.recipient', 'r')
            ->leftJoin('l.educationalCentre', 'c')
            ->orderBy('l.sentAt', 'DESC')
            ->addOrderBy('l.id', 'DESC');

        if ($centre !== null) {
            $qb->andWhere('l.educationalCentre = :centre')
                ->setParameter('centre', $centre->getId(), 'uuid');
        }

        $search = $filters['search'] ?? '';
        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'UNACCENT(LOWER(l.recipientName)) LIKE UNACCENT(LOWER(:search))',
                    'UNACCENT(LOWER(l.recipientEmail)) LIKE UNACCENT(LOWER(:search))',
                    'UNACCENT(LOWER(l.subject)) LIKE UNACCENT(LOWER(:search))',
                )
            )->setParameter('search', '%' . $search . '%');
        }

        if (!empty($filters['eventKey'])) {
            $qb->andWhere('l.eventKey = :eventKey')->setParameter('eventKey', $filters['eventKey']);
        }

        if (($filters['status'] ?? '') === 'success') {
            $qb->andWhere('l.success = true');
        } elseif (($filters['status'] ?? '') === 'failed') {
            $qb->andWhere('l.success = false');
        }

        if (!empty($filters['dateFrom'])) {
            try {
                $qb->andWhere('l.sentAt >= :dateFrom')->setParameter('dateFrom', new \DateTimeImmutable($filters['dateFrom']));
            } catch (\Exception) {
            }
        }

        if (!empty($filters['dateTo'])) {
            try {
                $qb->andWhere('l.sentAt <= :dateTo')->setParameter('dateTo', new \DateTimeImmutable($filters['dateTo']));
            } catch (\Exception) {
            }
        }

        return $qb->getQuery();
    }

    /** @return list<string> */
    public function findDistinctEventKeys(?EducationalCentre $centre): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('DISTINCT l.eventKey')
            ->orderBy('l.eventKey', 'ASC');

        if ($centre !== null) {
            $qb->where('l.educationalCentre = :centre')->setParameter('centre', $centre->getId(), 'uuid');
        }

        /** @var list<array{eventKey: string}> $rows */
        $rows = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $row): string => $row['eventKey'], $rows);
    }

    /** Elimina las entradas anteriores a la fecha dada. Devuelve cuántas. */
    public function deleteOlderThan(\DateTimeImmutable $cutoff): int
    {
        return $this->createQueryBuilder('l')
            ->delete()
            ->where('l.sentAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
