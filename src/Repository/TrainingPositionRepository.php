<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Stay;
use App\Entity\TrainingPosition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
