<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EducationalCentre;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Student>
 */
class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }

    /**
     * Paginated query: students in groups belonging to the centre's active academic year.
     * Supports text search (NIE, first name, last name) and optional group filter.
     *
     * @return Query<null, Student>
     */
    public function createByCentreFilteredQuery(
        EducationalCentre $centre,
        string $search = '',
        string $groupId = '',
    ): Query {
        $qb = $this->createQueryBuilder('s')
            ->distinct()
            ->join('s.groups', 'g')
            ->join('g.programmeYear', 'py')
            ->join('py.programme', 'prog')
            ->join('prog.professionalFamily', 'f')
            ->join('f.academicYear', 'ay')
            ->where('ay = :activeYear')
            ->setParameter('activeYear', $centre->getActiveAcademicYear()->getId(), 'uuid')
            ->orderBy('s.name.lastName', 'ASC')
            ->addOrderBy('s.name.firstName', 'ASC');

        if ($groupId !== '') {
            $qb->andWhere('g.id = :groupId')
               ->setParameter('groupId', $groupId, 'uuid');
        }

        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    's.studentId LIKE :search',
                    's.name.firstName LIKE :search',
                    's.name.lastName LIKE :search',
                )
            )->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery();
    }

    /** @return Query<null, Student> */
    public function findNoneQuery(): Query
    {
        return $this->createQueryBuilder('s')
            ->where('1 = 0')
            ->getQuery();
    }

    public function findById(string $id): ?Student
    {
        return $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByStudentId(string $studentId): ?Student
    {
        return $this->findOneBy(['studentId' => $studentId]);
    }
}
