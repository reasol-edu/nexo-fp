<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Clock\ClockInterface;

/**
 * @extends ServiceEntityRepository<TrainingPosition>
 */
class TrainingPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly ClockInterface $clock)
    {
        parent::__construct($registry, TrainingPosition::class);
    }

    /**
     * Puestos en estado DONE sin firmar, con los mismos filtros de período,
     * familia, enseñanza y búsqueda que el listado de estancias. La búsqueda
     * incluye nombre de estancia, enseñanza y nombre del estudiante.
     *
     * @param list<'current'|'future'|'past'> $periods
     * @param 'startDate'|'student'|'workcenter' $sort
     * @param 'ASC'|'DESC' $sortDir
     * @return Query<null, TrainingPosition>
     */
    public function createPendingSignaturesQuery(
        AcademicYear $year,
        string $search = '',
        string $familyId = '',
        string $programmeId = '',
        array $periods = ['current', 'future', 'past'],
        string $sort = 'startDate',
        string $sortDir = 'ASC',
        ?Teacher $viewer = null,
    ): Query {
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('tp')
            ->join('tp.stay', 's')
            ->join('s.programme', 'p')
            ->join('p.professionalFamily', 'f')
            ->leftJoin('tp.student', 'st')
            ->leftJoin('tp.workcenter', 'wc')
            ->leftJoin('wc.company', 'co')
            ->leftJoin('tp.academicTutor', 'at')
            ->where('s.academicYear = :year')
            ->andWhere('tp.state = :s_done')
            ->andWhere('tp.signed = :bfalse')
            ->setParameter('year', $year->getId(), 'uuid')
            ->setParameter('s_done', TrainingPositionState::DONE->value)
            ->setParameter('bfalse', false);

        if ($search !== '') {
            $q = '%' . $search . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(s.name) LIKE LOWER(:q)',
                    'LOWER(p.name) LIKE LOWER(:q)',
                    'LOWER(st.name.lastName) LIKE LOWER(:q)',
                    'LOWER(st.name.firstName) LIKE LOWER(:q)',
                    'EXISTS(SELECT 1 FROM App\Entity\Group sg JOIN sg.programmeYear sgpy WHERE sgpy MEMBER OF tp.programmeYears AND LOWER(sg.name) LIKE LOWER(:q))',
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

        $allPeriods    = ['current', 'future', 'past'];
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

        match ($sort) {
            'student'    => $qb->orderBy('st.name.lastName', $sortDir)
                               ->addOrderBy('st.name.firstName', $sortDir)
                               ->addOrderBy('s.startDate', 'ASC'),
            'workcenter' => $qb->orderBy('co.name', $sortDir)
                               ->addOrderBy('wc.name', $sortDir)
                               ->addOrderBy('s.startDate', 'ASC'),
            default      => $qb->orderBy('s.startDate', $sortDir)
                               ->addOrderBy('st.name.lastName', 'ASC')
                               ->addOrderBy('st.name.firstName', 'ASC'),
        };

        $this->addViewerFilter($qb, $viewer);

        return $qb->getQuery();
    }

    /** @return Query<null, TrainingPosition> */
    public function findNoneQuery(): Query
    {
        return $this->createQueryBuilder('tp')
            ->where('1 = 0')
            ->getQuery();
    }

    public function findByIdAndStay(string $id, Stay $stay): ?TrainingPosition
    {
        return $this->createQueryBuilder('tp')
            ->leftJoin('tp.programmeYears', 'py')->addSelect('py')
            ->where('tp.id = :id')
            ->andWhere('tp.stay = :stay')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('stay', $stay->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Puestos sin firmar con estudiante asignado cuyo tutor académico es el
     * docente dado y cuya estancia termina dentro del intervalo (ambos incluidos).
     *
     * @return array<int, TrainingPosition>
     */
    public function findUnsignedByTutorWithStayEndingBetween(
        Teacher $tutor,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        return $this->createQueryBuilder('tp')
            ->join('tp.stay', 's')->addSelect('s')
            ->join('tp.student', 'st')->addSelect('st')
            ->where('tp.academicTutor = :tutor')
            ->andWhere('tp.signed = :bfalse')
            ->andWhere('s.endDate >= :from')
            ->andWhere('s.endDate <= :to')
            ->setParameter('tutor', $tutor->getId(), 'uuid')
            ->setParameter('bfalse', false)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('to', $to, Types::DATE_IMMUTABLE)
            ->orderBy('s.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Puestos registrados (estado DONE) y sin firmar, con estudiante asignado,
     * de un centro, cuya estancia comienza en o antes de la fecha límite (sin
     * tope inferior: incluye estancias ya comenzadas mientras sigan sin firmar).
     * Carga tutores, centro de trabajo, enseñanza, coordinadores y jefatura de
     * familia para resolver los destinatarios sin consultas adicionales.
     *
     * @return array<int, TrainingPosition>
     */
    public function findRegisteredUnsignedStartingWithinForCentre(
        EducationalCentre $centre,
        \DateTimeImmutable $limitStartDate,
    ): array {
        return $this->createQueryBuilder('tp')
            ->join('tp.stay', 's')->addSelect('s')
            ->join('tp.student', 'st')->addSelect('st')
            ->leftJoin('tp.workcenter', 'wc')->addSelect('wc')
            ->leftJoin('wc.company', 'co')->addSelect('co')
            ->leftJoin('tp.academicTutor', 'at')->addSelect('at')
            ->leftJoin('tp.workplaceMentor', 'wm')->addSelect('wm')
            ->join('s.programme', 'p')->addSelect('p')
            ->leftJoin('p.coordinators', 'pc')->addSelect('pc')
            ->join('p.professionalFamily', 'pf')->addSelect('pf')
            ->leftJoin('pf.head', 'ph')->addSelect('ph')
            ->join('p.academicYear', 'ay')
            ->where('tp.state = :s_done')
            ->andWhere('tp.signed = :bfalse')
            ->andWhere('s.startDate <= :limit')
            ->andWhere('ay.educationalCentre = :centre')
            ->setParameter('s_done', TrainingPositionState::DONE->value)
            ->setParameter('bfalse', false)
            ->setParameter('limit', $limitStartDate, Types::DATE_IMMUTABLE)
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->orderBy('s.startDate', 'ASC')
            ->addOrderBy('st.name.lastName', 'ASC')
            ->addOrderBy('st.name.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function addViewerFilter(QueryBuilder $qb, ?Teacher $viewer): void
    {
        if ($viewer === null || $viewer->isAdmin()) {
            return;
        }

        // 's' = stay alias, 'p' = programme alias, 'f' = family alias already in qb
        $qb->join('s.academicYear', 'vvay')
           ->join('vvay.educationalCentre', 'vvc');

        $qb->andWhere($qb->expr()->orX(
            'EXISTS(SELECT 1 FROM ' . EducationalCentre::class . ' vece JOIN vece.admins vcea WHERE vece = vvc AND vcea.id = :vViewer)',
            'EXISTS(SELECT 1 FROM ' . Programme::class . ' vprog JOIN vprog.coordinators vcrd WHERE vprog = p AND vcrd.id = :vViewer)',
            'f.head = :vViewer',
            'EXISTS(SELECT 1 FROM ' . Group::class . ' vg JOIN vg.programmeYear vgpy LEFT JOIN vg.teachers vgt WHERE vgpy.programme = p AND (:vViewer MEMBER OF vg.tutors OR vgt.id = :vViewer))',
            'EXISTS(SELECT 1 FROM App\Entity\TrainingPosition vtp JOIN vtp.workcenter vwc JOIN vwc.company vco JOIN vco.liaisons vli WHERE vtp.stay = s AND vli.id = :vViewer)',
        ))->setParameter('vViewer', $viewer->getId(), 'uuid');
    }

    /** @return array<int, TrainingPosition> */
    public function findByStayOrdered(Stay $stay): array
    {
        return $this->createQueryBuilder('tp')
            ->leftJoin('tp.student', 'st')->addSelect('st')
            ->leftJoin('tp.workcenter', 'wc')->addSelect('wc')
            ->leftJoin('wc.company', 'co')->addSelect('co')
            ->leftJoin('tp.academicTutor', 'at')->addSelect('at')
            ->leftJoin('tp.workplaceMentor', 'wm')->addSelect('wm')
            ->leftJoin('tp.programmeYears', 'py')->addSelect('py')
            ->where('tp.stay = :stay')
            ->setParameter('stay', $stay->getId(), 'uuid')
            ->orderBy('co.name', 'ASC')
            ->addOrderBy('wc.name', 'ASC')
            ->addOrderBy('st.name.lastName', 'ASC')
            ->addOrderBy('st.name.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
