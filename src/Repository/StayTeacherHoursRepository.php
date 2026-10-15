<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Entity\Stay;
use App\Entity\StayTeacherHours;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StayTeacherHours>
 */
class StayTeacherHoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StayTeacherHours::class);
    }

    /**
     * Horas de la estancia indexadas por grupo y docente: `[groupId][teacherId] => horas`.
     *
     * @return array<string, array<string, int>>
     */
    public function findHoursByStay(Stay $stay): array
    {
        /** @var list<StayTeacherHours> $rows */
        $rows = $this->createQueryBuilder('h')
            ->addSelect('g', 't')
            ->join('h.group', 'g')
            ->join('h.teacher', 't')
            ->where('h.stay = :stay')
            ->setParameter('stay', $stay->getId(), 'uuid')
            ->getQuery()
            ->getResult();

        $byGroup = [];
        foreach ($rows as $row) {
            $byGroup[$row->getGroup()->getId()->toRfc4122()][$row->getTeacher()->getId()->toRfc4122()] = $row->getHours();
        }

        return $byGroup;
    }

    public function findOne(Stay $stay, Group $group, Teacher $teacher): ?StayTeacherHours
    {
        /** @var StayTeacherHours|null $row */
        $row = $this->findOneBy(['stay' => $stay, 'group' => $group, 'teacher' => $teacher]);

        return $row;
    }
}
