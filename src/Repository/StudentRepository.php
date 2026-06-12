<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\Programme;
use App\Entity\Student;
use App\Entity\Teacher;
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

    /**
     * Same filters as createByCentreFilteredQuery, but returns the full list with
     * the groups collection fetch-joined (separate alias so the group filter does
     * not truncate the hydrated collection).
     *
     * @return list<Student>
     */
    public function findByCentreFilteredWithGroups(
        EducationalCentre $centre,
        string $search = '',
        string $groupId = '',
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->distinct()
            ->join('s.groups', 'g')
            ->join('g.programmeYear', 'py')
            ->join('py.programme', 'prog')
            ->join('prog.professionalFamily', 'f')
            ->join('f.academicYear', 'ay')
            ->leftJoin('s.groups', 'sg')->addSelect('sg')
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

        return $qb->getQuery()->getResult();
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

    public function countByActiveYear(EducationalCentre $centre, ?Teacher $viewer = null): int
    {
        if ($centre->getActiveAcademicYear() === null) {
            return 0;
        }

        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.id)')
            ->join('s.groups', 'g')
            ->join('g.programmeYear', 'py')
            ->join('py.programme', 'prog')
            ->join('prog.professionalFamily', 'f')
            ->where('f.academicYear = :year')
            ->setParameter('year', $centre->getActiveAcademicYear()->getId(), 'uuid');

        if ($viewer !== null && !$viewer->isAdmin()) {
            $qb->andWhere($qb->expr()->orX(
                'EXISTS(SELECT 1 FROM ' . AcademicYear::class . ' vay JOIN vay.educationalCentre vvec JOIN vvec.admins vadm WHERE vay.id = :year AND vadm.id = :viewer)',
                'f.head = :viewer',
                'EXISTS(SELECT 1 FROM ' . Programme::class . ' vp JOIN vp.coordinators vc WHERE vp = prog AND vc.id = :viewer)',
                'EXISTS(SELECT 1 FROM ' . Group::class . ' vg JOIN vg.programmeYear vgpy WHERE vgpy.programme = prog AND (:viewer MEMBER OF vg.tutors OR :viewer MEMBER OF vg.teachers))',
            ))->setParameter('viewer', $viewer->getId(), 'uuid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Quick search by name / NIE for the global search palette.
     *
     * @return list<Student>
     */
    public function searchByCentre(EducationalCentre $centre, string $q, int $limit = 5): array
    {
        /** @var list<Student> $result */
        $result = $this->createByCentreFilteredQuery($centre, $q)
            ->setMaxResults($limit)
            ->getResult();

        return $result;
    }
}
