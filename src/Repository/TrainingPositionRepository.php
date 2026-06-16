<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EducationalCentre;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
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
