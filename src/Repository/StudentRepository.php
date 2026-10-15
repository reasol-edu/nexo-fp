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
     * Nombre completo («Nombre Apellidos») de los estudiantes indicados, como mucho 200. Se compara de uno en
     * uno con tipo «uuid» porque `IN (:ids)` falla con ids binarios (MySQL, SQLite).
     *
     * @param list<string> $ids
     * @return array<string, string> id (RFC 4122) → nombre
     */
    public function findNamesByIds(array $ids): array
    {
        $ids = \array_slice(array_values(array_unique($ids)), 0, 200);
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('s')->select('s.id AS id', 's.name.firstName AS firstName', 's.name.lastName AS lastName');
        $or = $qb->expr()->orX();
        foreach ($ids as $i => $id) {
            if (\Symfony\Component\Uid\Uuid::isValid($id)) {
                $or->add('s.id = :id' . $i);
                $qb->setParameter('id' . $i, $id, 'uuid');
            }
        }
        if ($or->count() === 0) {
            return [];
        }

        /** @var list<array{id: mixed, firstName: string, lastName: string}> $rows */
        $rows = $qb->where($or)->getQuery()->getResult();

        $names = [];
        foreach ($rows as $row) {
            $names[(string) $row['id']] = trim($row['firstName'] . ' ' . $row['lastName']);
        }

        return $names;
    }

    /** Columnas ordenables: clave pública => campo DQL (allowlist contra inyección). */
    private const SORTABLE = [
        'nie' => 's.studentId',
        'name' => 's.name.lastName',
    ];

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
        string $sort = '',
        string $sortDir = 'asc',
        ?AcademicYear $year = null,
    ): Query {
        $year ??= $centre->getActiveAcademicYear();
        $qb = $this->createQueryBuilder('s')
            ->distinct()
            ->join('s.groups', 'g')
            ->join('g.programmeYear', 'py')
            ->join('py.programme', 'prog')
            ->join('prog.professionalFamily', 'f')
            ->join('f.academicYear', 'ay')
            ->where('ay = :activeYear')
            ->setParameter('activeYear', $year->getId(), 'uuid');

        $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        if (isset(self::SORTABLE[$sort])) {
            $qb->orderBy(self::SORTABLE[$sort], $dir);
            if ($sort === 'name') {
                $qb->addOrderBy('s.name.firstName', $dir);
            }
        } else {
            $qb->orderBy('s.name.lastName', 'ASC')
               ->addOrderBy('s.name.firstName', 'ASC');
        }

        if ($groupId !== '') {
            $qb->andWhere('g.id = :groupId')
               ->setParameter('groupId', $groupId, 'uuid');
        }

        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'UNACCENT(LOWER(s.studentId)) LIKE UNACCENT(LOWER(:search))',
                    'UNACCENT(LOWER(s.name.firstName)) LIKE UNACCENT(LOWER(:search))',
                    'UNACCENT(LOWER(s.name.lastName)) LIKE UNACCENT(LOWER(:search))',
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

    public function countByActiveYear(EducationalCentre $centre, ?Teacher $viewer = null, ?AcademicYear $year = null): int
    {
        $year ??= $centre->getActiveAcademicYear();
        if ($year === null) {
            return 0;
        }

        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.id)')
            ->join('s.groups', 'g')
            ->join('g.programmeYear', 'py')
            ->join('py.programme', 'prog')
            ->join('prog.professionalFamily', 'f')
            ->where('f.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid');

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
