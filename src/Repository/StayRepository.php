<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPositionState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;

/**
 * @extends ServiceEntityRepository<Stay>
 */
class StayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly ClockInterface $clock)
    {
        parent::__construct($registry, Stay::class);
    }

    /**
     * @param list<'current'|'future'|'past'> $periods
     * @return Query<null, Stay>
     */
    public function createByCentreFilteredQuery(
        AcademicYear $year,
        string $search = '',
        string $familyId = '',
        string $programmeId = '',
        array $periods = ['current', 'future', 'past'],
        ?Teacher $viewer = null,
    ): Query {
        $qb = $this->createQueryBuilder('s')
            ->join('s.programme', 'p')
            ->join('p.professionalFamily', 'f')
            ->where('s.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid')
            ->orderBy('s.endDate', 'DESC')
            ->addOrderBy('s.startDate', 'DESC')
            ->addOrderBy('f.name', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->addOrderBy('s.name', 'ASC');

        if ($search !== '') {
            $q = '%' . $search . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(s.name) LIKE LOWER(:q)',
                    'LOWER(p.name) LIKE LOWER(:q)',
                )
            )->setParameter('q', $q);
        }

        if ($familyId !== '') {
            $qb->andWhere('f.id = :familyId')
               ->setParameter('familyId', $familyId, 'uuid');
        }

        if ($programmeId !== '') {
            $qb->andWhere('p.id = :programmeId')
               ->setParameter('programmeId', $programmeId, 'uuid');
        }

        $allPeriods = ['current', 'future', 'past'];
        $activePeriods = array_values(array_intersect($periods, $allPeriods));

        if ($activePeriods === []) {
            $qb->andWhere('1 = 0');
        } elseif (count($activePeriods) < 3) {
            $today = $this->clock->now()->setTime(0, 0, 0);
            $qb->setParameter('today', $today);

            $orConditions = $qb->expr()->orX();

            if (in_array('past', $activePeriods, true)) {
                $orConditions->add('s.endDate IS NOT NULL AND s.endDate < :today');
            }
            if (in_array('future', $activePeriods, true)) {
                $orConditions->add('s.startDate IS NOT NULL AND s.startDate > :today');
            }
            if (in_array('current', $activePeriods, true)) {
                $orConditions->add(
                    '(s.endDate IS NULL OR s.endDate >= :today) AND (s.startDate IS NULL OR s.startDate <= :today)'
                );
            }

            $qb->andWhere($orConditions);
        }

        if ($viewer !== null && !$viewer->isAdmin()) {
            $qb
                ->join('s.academicYear', 'vay')
                ->join('vay.educationalCentre', 'vc');

            $qb->andWhere($qb->expr()->orX(
                'EXISTS(SELECT 1 FROM ' . EducationalCentre::class . ' vece JOIN vece.admins vcea WHERE vece = vc AND vcea.id = :vViewer)',
                'EXISTS(SELECT 1 FROM ' . Programme::class . ' vprog JOIN vprog.coordinators vcrd WHERE vprog = p AND vcrd.id = :vViewer)',
                'f.head = :vViewer',
                'EXISTS(SELECT 1 FROM ' . Group::class . ' vg JOIN vg.programmeYear vgpy LEFT JOIN vg.teachers vgt WHERE vgpy.programme = p AND (vg.tutor = :vViewer OR vgt.id = :vViewer))',
                'EXISTS(SELECT 1 FROM App\Entity\TrainingPosition vtp JOIN vtp.workcenter vwc JOIN vwc.company vco JOIN vco.liaisons vli WHERE vtp.stay = s AND vli.id = :vViewer)',
            ))->setParameter('vViewer', $viewer->getId(), 'uuid');
        }

        return $qb->getQuery();
    }

    /** @return Query<null, Stay> */
    public function findNoneQuery(): Query
    {
        return $this->createQueryBuilder('s')
            ->where('1 = 0')
            ->getQuery();
    }

    public function existsByNameAndYear(string $name, AcademicYear $year, ?Stay $exclude = null): bool
    {
        $qb = $this->createQueryBuilder('s')
            ->select('1')
            ->where('s.name = :name')
            ->andWhere('s.academicYear = :year')
            ->setParameter('name', $name)
            ->setParameter('year', $year->getId(), 'uuid')
            ->setMaxResults(1);

        if ($exclude !== null) {
            $qb->andWhere('s.id != :exclude')
               ->setParameter('exclude', $exclude->getId(), 'uuid');
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    public function findById(string $id): ?Stay
    {
        return $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Aggregated dashboard stats for an academic year in two queries.
     *
     * @return array{
     *     total_stays: int, current_stays: int, future_stays: int, past_stays: int,
     *     total_positions: int, occupied: int, free: int, signed: int,
     *     state_draft: int, state_pending: int, state_done: int, companies: int
     * }
     */
    public function findDashboardStats(AcademicYear $year): array
    {
        $today = $this->clock->now()->setTime(0, 0, 0);

        /** @var array{total_stays: string, current_stays: string, future_stays: string, past_stays: string} $stayRow */
        $stayRow = $this->createQueryBuilder('s')
            ->select(
                'COUNT(s.id) AS total_stays',
                'SUM(CASE WHEN (s.startDate IS NULL OR s.startDate <= :today) AND (s.endDate IS NULL OR s.endDate >= :today) THEN 1 ELSE 0 END) AS current_stays',
                'SUM(CASE WHEN s.startDate IS NOT NULL AND s.startDate > :today THEN 1 ELSE 0 END) AS future_stays',
                'SUM(CASE WHEN s.endDate IS NOT NULL AND s.endDate < :today THEN 1 ELSE 0 END) AS past_stays',
            )
            ->where('s.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleResult();

        $em = $this->getEntityManager();

        /** @var array{total_positions: string, occupied: string, signed: string, state_draft: string, state_pending: string, state_done: string, companies: string} $posRow */
        $posRow = $em->createQueryBuilder()
            ->select(
                'COUNT(tp.id) AS total_positions',
                'SUM(CASE WHEN tp.student IS NOT NULL THEN 1 ELSE 0 END) AS occupied',
                'SUM(CASE WHEN tp.signed = :btrue THEN 1 ELSE 0 END) AS signed',
                'SUM(CASE WHEN tp.state = :s_draft THEN 1 ELSE 0 END) AS state_draft',
                'SUM(CASE WHEN tp.state = :s_pending THEN 1 ELSE 0 END) AS state_pending',
                'SUM(CASE WHEN tp.state = :s_done THEN 1 ELSE 0 END) AS state_done',
                'COUNT(DISTINCT IDENTITY(wc.company)) AS companies',
            )
            ->from(Stay::class, 's')
            ->leftJoin('s.trainingPositions', 'tp')
            ->leftJoin('tp.workcenter', 'wc')
            ->where('s.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('btrue', true)
            ->setParameter('s_draft', TrainingPositionState::DRAFT->value)
            ->setParameter('s_pending', TrainingPositionState::PENDING->value)
            ->setParameter('s_done', TrainingPositionState::DONE->value)
            ->getQuery()
            ->getSingleResult();

        $total = (int) $posRow['total_positions'];
        $occupied = (int) $posRow['occupied'];

        return [
            'total_stays'    => (int) $stayRow['total_stays'],
            'current_stays'  => (int) $stayRow['current_stays'],
            'future_stays'   => (int) $stayRow['future_stays'],
            'past_stays'     => (int) $stayRow['past_stays'],
            'total_positions'=> $total,
            'occupied'       => $occupied,
            'free'           => $total - $occupied,
            'signed'         => (int) $posRow['signed'],
            'state_draft'    => (int) $posRow['state_draft'],
            'state_pending'  => (int) $posRow['state_pending'],
            'state_done'     => (int) $posRow['state_done'],
            'companies'      => (int) $posRow['companies'],
        ];
    }

    /**
     * Returns current and upcoming stays ordered by start date, limited to $limit results.
     *
     * @return Stay[]
     */
    public function findActiveAndUpcoming(AcademicYear $year, int $limit = 6): array
    {
        $today = $this->clock->now()->setTime(0, 0, 0);

        return $this->createQueryBuilder('s')
            ->join('s.programme', 'p')->addSelect('p')
            ->join('p.professionalFamily', 'f')->addSelect('f')
            ->where('s.academicYear = :year')
            ->andWhere('s.endDate IS NULL OR s.endDate >= :today')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('today', $today)
            ->orderBy('s.startDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns aggregated stats for a list of stays using two queries
     * (one for training position stats, one for student counts) to avoid N+1.
     *
     * @param  Stay[] $stays
     * @return array<string, array{students: int, total_positions: int, occupied: int, free: int, signed: int, state_draft: int, state_pending: int, state_done: int}>
     */
    public function findStatsForStays(array $stays): array
    {
        if ($stays === []) {
            return [];
        }

        $em = $this->getEntityManager();

        // s IN (:array_of_entities) produces incorrect SQL for binary UUIDs on MySQL.
        // Use individual OR conditions with explicit 'uuid' type instead.
        $posQb = $em->createQueryBuilder()
            ->select(
                's.id AS stayId',
                'COUNT(tp.id) AS total',
                'COUNT(DISTINCT IDENTITY(tp.student)) AS students_with_position',
                'SUM(CASE WHEN tp.student IS NOT NULL THEN 1 ELSE 0 END) AS occupied',
                'SUM(CASE WHEN tp.signed = :btrue THEN 1 ELSE 0 END) AS signed_count',
                'SUM(CASE WHEN tp.state = :s_draft THEN 1 ELSE 0 END) AS state_draft',
                'SUM(CASE WHEN tp.state = :s_pending THEN 1 ELSE 0 END) AS state_pending',
                'SUM(CASE WHEN tp.state = :s_done THEN 1 ELSE 0 END) AS state_done',
                'COUNT(DISTINCT IDENTITY(wc.company)) AS companies',
            )
            ->from(Stay::class, 's')
            ->leftJoin('s.trainingPositions', 'tp')
            ->leftJoin('tp.workcenter', 'wc')
            ->groupBy('s.id')
            ->setParameter('btrue', true)
            ->setParameter('s_draft', TrainingPositionState::DRAFT->value)
            ->setParameter('s_pending', TrainingPositionState::PENDING->value)
            ->setParameter('s_done', TrainingPositionState::DONE->value);
        $posConds = [];
        foreach ($stays as $i => $stay) {
            $posConds[] = "s.id = :sid_{$i}";
            $posQb->setParameter("sid_{$i}", $stay->getId(), 'uuid');
        }
        $positionRows = $posQb->where(implode(' OR ', $posConds))->getQuery()->getScalarResult();

        $stQb = $em->createQueryBuilder()
            ->select('s.id AS stayId', 'COUNT(st.id) AS cnt')
            ->from(Stay::class, 's')
            ->leftJoin('s.students', 'st')
            ->groupBy('s.id');
        $stConds = [];
        foreach ($stays as $i => $stay) {
            $stConds[] = "s.id = :sid_{$i}";
            $stQb->setParameter("sid_{$i}", $stay->getId(), 'uuid');
        }
        $studentRows = $stQb->where(implode(' OR ', $stConds))->getQuery()->getScalarResult();

        // getScalarResult() returns UUIDs in binary form on MySQL.
        // Build a lookup map so any representation normalises to RFC4122.
        $uuidNorm = [];
        foreach ($stays as $stay) {
            $rfc = $stay->getId()->toRfc4122();
            $uuidNorm[$rfc]                      = $rfc;
            $uuidNorm[$stay->getId()->toBinary()] = $rfc;
        }
        $normalize = static fn (mixed $raw): string =>
            $uuidNorm[(string) $raw] ?? (string) $raw;

        $studentMap = [];
        foreach ($studentRows as $row) {
            $studentMap[$normalize($row['stayId'])] = (int) $row['cnt'];
        }

        $stats = [];
        foreach ($positionRows as $row) {
            $id    = $normalize($row['stayId']);
            $total = (int) $row['total'];
            $occ   = (int) $row['occupied'];
            $stats[$id] = [
                'students'              => $studentMap[$id] ?? 0,
                'students_with_position'=> (int) $row['students_with_position'],
                'total_positions'       => $total,
                'occupied'              => $occ,
                'free'                  => $total - $occ,
                'companies'             => (int) $row['companies'],
                'signed'                => (int) $row['signed_count'],
                'state_draft'           => (int) $row['state_draft'],
                'state_pending'         => (int) $row['state_pending'],
                'state_done'            => (int) $row['state_done'],
            ];
        }

        // Fallback for stays with no positions or students (should not happen with LEFT JOIN, but defensive)
        foreach ($stays as $stay) {
            $id = $stay->getId()->toRfc4122();
            if (!isset($stats[$id])) {
                $stats[$id] = [
                    'students'               => $studentMap[$id] ?? 0,
                    'students_with_position' => 0,
                    'total_positions'        => 0,
                    'occupied'               => 0,
                    'free'                   => 0,
                    'companies'              => 0,
                    'signed'                 => 0,
                    'state_draft'            => 0,
                    'state_pending'          => 0,
                    'state_done'             => 0,
                ];
            }
        }

        return $stats;
    }
}
