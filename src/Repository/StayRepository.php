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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
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

        $this->addViewerFilter($qb, $viewer);

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
    public function findDashboardStats(AcademicYear $year, ?Teacher $viewer = null): array
    {
        $today = $this->clock->now()->setTime(0, 0, 0);

        $stayQb = $this->createQueryBuilder('s')
            ->select(
                'COUNT(s.id) AS total_stays',
                'SUM(CASE WHEN (s.startDate IS NULL OR s.startDate <= :today) AND (s.endDate IS NULL OR s.endDate >= :today) THEN 1 ELSE 0 END) AS current_stays',
                'SUM(CASE WHEN s.startDate IS NOT NULL AND s.startDate > :today THEN 1 ELSE 0 END) AS future_stays',
                'SUM(CASE WHEN s.endDate IS NOT NULL AND s.endDate < :today THEN 1 ELSE 0 END) AS past_stays',
            )
            ->where('s.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('today', $today);
        $this->addViewerFilter($stayQb, $viewer);

        /** @var array{total_stays: string, current_stays: string, future_stays: string, past_stays: string} $stayRow */
        $stayRow = $stayQb->getQuery()->getSingleResult();

        $em = $this->getEntityManager();

        $posQb = $em->createQueryBuilder()
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
            ->setParameter('s_done', TrainingPositionState::DONE->value);
        $this->addViewerFilter($posQb, $viewer);

        /** @var array{total_positions: string, occupied: string, signed: string, state_draft: string, state_pending: string, state_done: string, companies: string} $posRow */
        $posRow = $posQb->getQuery()->getSingleResult();

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
     * Position counters grouped by professional family, ordered by family name.
     *
     * @return list<array{family_name: string, total: int, occupied: int, signed: int}>
     */
    public function countPositionsByFamily(AcademicYear $year, ?Teacher $viewer = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select(
                'f.name AS family_name',
                'COUNT(tp.id) AS total',
                'SUM(CASE WHEN tp.student IS NOT NULL THEN 1 ELSE 0 END) AS occupied',
                'SUM(CASE WHEN tp.signed = :btrue THEN 1 ELSE 0 END) AS signed',
            )
            ->join('s.programme', 'p')
            ->join('p.professionalFamily', 'f')
            ->leftJoin('s.trainingPositions', 'tp')
            ->where('s.academicYear = :year')
            ->groupBy('f.id, f.name')
            ->orderBy('f.name', 'ASC')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('btrue', true);
        $this->addViewerFilter($qb, $viewer);

        /** @var list<array{family_name: string, total: string|int, occupied: string|int|null, signed: string|int|null}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(static fn (array $row): array => [
            'family_name' => $row['family_name'],
            'total'       => (int) $row['total'],
            'occupied'    => (int) $row['occupied'],
            'signed'      => (int) $row['signed'],
        ], $rows);
    }

    /**
     * Signature timestamps of all signed positions of the year, ascending.
     * Month grouping is done in PHP for portability across database engines.
     *
     * @return list<\DateTimeImmutable>
     */
    public function findSignedDatesForYear(AcademicYear $year, ?Teacher $viewer = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->select('tp.signedAt AS signed_at')
            ->join('s.trainingPositions', 'tp')
            ->where('s.academicYear = :year')
            ->andWhere('tp.signedAt IS NOT NULL')
            ->orderBy('tp.signedAt', 'ASC')
            ->setParameter('year', $year->getId(), 'uuid');
        $this->addViewerFilter($qb, $viewer);

        /** @var list<array{signed_at: \DateTimeImmutable}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(static fn (array $row): \DateTimeImmutable => $row['signed_at'], $rows);
    }

    /**
     * Stays not yet finished whose positions need attention, with per-stay counters.
     *
     * @return list<array{stay: Stay, free: int, missing_tutor: int, missing_mentor: int, done_unsigned: int}>
     */
    public function findPositionAlertsByStay(AcademicYear $year, ?Teacher $viewer = null): array
    {
        $today = $this->clock->now()->setTime(0, 0, 0);

        // tp.id IS NOT NULL guards against the LEFT JOIN row of stays with no positions
        $freeExpr          = 'SUM(CASE WHEN tp.id IS NOT NULL AND tp.student IS NULL THEN 1 ELSE 0 END)';
        $missingTutorExpr  = 'SUM(CASE WHEN tp.student IS NOT NULL AND tp.academicTutor IS NULL AND s.startDate <= :today THEN 1 ELSE 0 END)';
        $missingMentorExpr = 'SUM(CASE WHEN tp.student IS NOT NULL AND tp.workplaceMentor IS NULL AND s.startDate <= :today THEN 1 ELSE 0 END)';
        $doneUnsignedExpr  = 'SUM(CASE WHEN tp.state = :s_done AND tp.signed = :bfalse THEN 1 ELSE 0 END)';

        $qb = $this->createQueryBuilder('s')
            ->select('s')
            ->addSelect($freeExpr . ' AS free')
            ->addSelect($missingTutorExpr . ' AS missing_tutor')
            ->addSelect($missingMentorExpr . ' AS missing_mentor')
            ->addSelect($doneUnsignedExpr . ' AS done_unsigned')
            ->leftJoin('s.trainingPositions', 'tp')
            ->where('s.academicYear = :year')
            ->andWhere('s.endDate IS NULL OR s.endDate >= :today')
            ->groupBy('s.id')
            ->having("({$freeExpr} + {$missingTutorExpr} + {$missingMentorExpr} + {$doneUnsignedExpr}) > 0")
            ->orderBy('s.endDate', 'ASC')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('today', $today)
            ->setParameter('s_done', TrainingPositionState::DONE->value)
            ->setParameter('bfalse', false);

        $this->addViewerFilter($qb, $viewer);

        /** @var list<array{0: Stay, free: string|int, missing_tutor: string|int, missing_mentor: string|int, done_unsigned: string|int}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(static fn (array $row): array => [
            'stay'           => $row[0],
            'free'           => (int) $row['free'],
            'missing_tutor'  => (int) $row['missing_tutor'],
            'missing_mentor' => (int) $row['missing_mentor'],
            'done_unsigned'  => (int) $row['done_unsigned'],
        ], $rows);
    }

    /**
     * Enrolled students without an assigned position, per non-finished stay.
     *
     * @return list<array{stay: Stay, students_without_position: int}>
     */
    public function countStudentsWithoutPositionByStay(AcademicYear $year, ?Teacher $viewer = null): array
    {
        $today = $this->clock->now()->setTime(0, 0, 0);

        // COUNT(DISTINCT ...) on both sides neutralises the cartesian product of the two LEFT JOINs
        $expr = 'COUNT(DISTINCT st.id) - COUNT(DISTINCT IDENTITY(tp.student))';

        $qb = $this->createQueryBuilder('s')
            ->select('s')
            ->addSelect($expr . ' AS students_without_position')
            ->leftJoin('s.students', 'st')
            ->leftJoin('s.trainingPositions', 'tp')
            ->where('s.academicYear = :year')
            ->andWhere('s.endDate IS NULL OR s.endDate >= :today')
            ->groupBy('s.id')
            ->having($expr . ' > 0')
            ->orderBy('s.endDate', 'ASC')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('today', $today);

        $this->addViewerFilter($qb, $viewer);

        /** @var list<array{0: Stay, students_without_position: string|int}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(static fn (array $row): array => [
            'stay'                      => $row[0],
            'students_without_position' => (int) $row['students_without_position'],
        ], $rows);
    }

    /**
     * Returns current and upcoming stays ordered by start date, limited to $limit results.
     *
     * @return Stay[]
     */
    public function findActiveAndUpcoming(AcademicYear $year, ?Teacher $viewer = null, int $limit = 6): array
    {
        $today = $this->clock->now()->setTime(0, 0, 0);

        $qb = $this->createQueryBuilder('s')
            ->join('s.programme', 'p')->addSelect('p')
            ->join('p.professionalFamily', 'f')->addSelect('f')
            ->where('s.academicYear = :year')
            ->andWhere('s.endDate IS NULL OR s.endDate >= :today')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('today', $today)
            ->orderBy('s.startDate', 'ASC')
            ->setMaxResults($limit);

        $this->addViewerFilter($qb, $viewer);

        return $qb->getQuery()->getResult();
    }

    /**
     * Stays whose date range overlaps with [from, to] (NULL start/end = open-ended).
     * Eager-loads programme, family, and training positions for calendar rendering.
     *
     * @return list<Stay>
     */
    public function findOverlappingPeriod(
        AcademicYear $year,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?Teacher $viewer = null,
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->join('s.programme', 'p')->addSelect('p')
            ->join('p.professionalFamily', 'f')->addSelect('f')
            ->leftJoin('s.trainingPositions', 'tp')->addSelect('tp')
            ->where('s.academicYear = :year')
            ->andWhere('(s.startDate IS NULL OR s.startDate <= :to)')
            ->andWhere('(s.endDate IS NULL OR s.endDate >= :from)')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->orderBy('s.startDate', 'ASC')
            ->addOrderBy('s.name', 'ASC');
        $this->addViewerFilter($qb, $viewer);

        /** @var list<Stay> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
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

    private function addViewerFilter(QueryBuilder $qb, ?Teacher $viewer): void
    {
        if ($viewer === null || $viewer->isAdmin()) {
            return;
        }

        $qb->join('s.programme', 'vvp')
           ->join('vvp.professionalFamily', 'vvf')
           ->join('s.academicYear', 'vvay')
           ->join('vvay.educationalCentre', 'vvc');

        $qb->andWhere($qb->expr()->orX(
            'EXISTS(SELECT 1 FROM ' . EducationalCentre::class . ' vece JOIN vece.admins vcea WHERE vece = vvc AND vcea.id = :vViewer)',
            'EXISTS(SELECT 1 FROM ' . Programme::class . ' vprog JOIN vprog.coordinators vcrd WHERE vprog = vvp AND vcrd.id = :vViewer)',
            'vvf.head = :vViewer',
            'EXISTS(SELECT 1 FROM ' . Group::class . ' vg JOIN vg.programmeYear vgpy LEFT JOIN vg.teachers vgt WHERE vgpy.programme = vvp AND (:vViewer MEMBER OF vg.tutors OR vgt.id = :vViewer))',
            'EXISTS(SELECT 1 FROM App\Entity\TrainingPosition vtp JOIN vtp.workcenter vwc JOIN vwc.company vco JOIN vco.liaisons vli WHERE vtp.stay = s AND vli.id = :vViewer)',
        ))->setParameter('vViewer', $viewer->getId(), 'uuid');
    }
}
