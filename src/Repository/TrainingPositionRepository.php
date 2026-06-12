<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TrainingPosition>
 */
class TrainingPositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingPosition::class);
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
     * Puestos con estudiante asignado y sin firmar de estancias que terminan
     * exactamente en la fecha dada (para recordatorios idempotentes con cron diario).
     *
     * @return array<int, TrainingPosition>
     */
    public function findUnsignedWithStayEndingOn(\DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('tp')
            ->join('tp.stay', 's')->addSelect('s')
            ->join('tp.student', 'st')->addSelect('st')
            ->leftJoin('tp.workcenter', 'wc')->addSelect('wc')
            ->leftJoin('wc.company', 'co')->addSelect('co')
            ->leftJoin('tp.academicTutor', 'at')->addSelect('at')
            ->where('s.endDate = :endDate')
            ->andWhere('tp.signed = :bfalse')
            ->setParameter('endDate', $endDate, Types::DATE_IMMUTABLE)
            ->setParameter('bfalse', false)
            ->orderBy('st.name.lastName', 'ASC')
            ->addOrderBy('st.name.firstName', 'ASC')
            ->getQuery()
            ->getResult();
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
