<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function isTeacherInProgramme(Teacher $teacher, Programme $programme): bool
    {
        return $this->createQueryBuilder('g')
            ->select('1')
            ->join('g.programmeYear', 'py')
            ->leftJoin('g.teachers', 't')
            ->where('py.programme = :programme')
            ->andWhere(':teacher MEMBER OF g.tutors OR t.id = :teacher')
            ->setParameter('programme', $programme->getId(), 'uuid')
            ->setParameter('teacher', $teacher->getId(), 'uuid')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    /** @return Group[] */
    public function findByLevelOrderedByName(ProgrammeYear $level): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.programmeYear = :level')
            ->setParameter('level', $level->getId(), 'uuid')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByLevelAndId(ProgrammeYear $level, string $id): ?Group
    {
        return $this->createQueryBuilder('g')
            ->where('g.programmeYear = :level')
            ->andWhere('g.id = :id')
            ->setParameter('level', $level->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns all groups (with students eagerly loaded) that belong to ProgrammeYears
     * of the given programme. Ordered by level name → group name → student surname.
     *
     * @return Group[]
     */
    public function findByProgrammeWithStudents(Programme $programme): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.students', 's')->addSelect('s')
            ->join('g.programmeYear', 'py')
            ->where('py.programme = :programme')
            ->setParameter('programme', $programme->getId(), 'uuid')
            ->orderBy('py.name', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->addOrderBy('s.name.lastName', 'ASC')
            ->addOrderBy('s.name.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Group[] */
    public function findByActiveYearOfCentreOrderedByName(EducationalCentre $centre): array
    {
        if ($centre->getActiveAcademicYear() === null) {
            return [];
        }

        return $this->createQueryBuilder('g')
            ->join('g.programmeYear', 'py')
            ->join('py.programme', 'prog')
            ->join('prog.professionalFamily', 'f')
            ->where('f.academicYear = :year')
            ->setParameter('year', $centre->getActiveAcademicYear()->getId(), 'uuid')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns student and teacher counts for every group in the given academic year,
     * keyed by group UUID (RFC4122). Single query; avoids N+1 per group.
     *
     * @param  Group[] $groups  All groups in the year (used to normalise binary UUIDs from getScalarResult)
     * @return array<string, array{students: int, teachers: int}>
     */
    public function findCountsByAcademicYear(\App\Entity\AcademicYear $year, array $groups): array
    {
        if ($groups === []) {
            return [];
        }

        $rows = $this->getEntityManager()
            ->createQuery('
                SELECT g.id AS gid,
                       COUNT(DISTINCT s.id) AS students,
                       COUNT(DISTINCT t.id) AS teachers
                FROM App\Entity\Group g
                JOIN g.programmeYear py
                JOIN py.programme prog
                JOIN prog.professionalFamily f
                LEFT JOIN g.students s
                LEFT JOIN g.teachers t
                WHERE f.academicYear = :year
                GROUP BY g.id
            ')
            ->setParameter('year', $year->getId(), 'uuid')
            ->getScalarResult();

        // getScalarResult() returns UUIDs in binary form on MySQL.
        // Build a lookup map so either representation normalises to RFC4122.
        $uuidNorm = [];
        foreach ($groups as $group) {
            $rfc = $group->getId()->toRfc4122();
            $uuidNorm[$rfc]                        = $rfc;
            $uuidNorm[$group->getId()->toBinary()]  = $rfc;
        }
        $normalize = static fn (mixed $raw): string =>
            $uuidNorm[(string) $raw] ?? (string) $raw;

        $map = [];
        foreach ($rows as $row) {
            $map[$normalize($row['gid'])] = [
                'students' => (int) $row['students'],
                'teachers' => (int) $row['teachers'],
            ];
        }

        return $map;
    }

    /**
     * Returns groups for the centre's active year with programme and level data eagerly loaded,
     * sorted by programme family → programme → level → group name.
     *
     * @return Group[]
     */
    public function findByActiveYearOfCentreWithProgramme(EducationalCentre $centre): array
    {
        if ($centre->getActiveAcademicYear() === null) {
            return [];
        }

        return $this->createQueryBuilder('g')
            ->join('g.programmeYear', 'py')->addSelect('py')
            ->join('py.programme', 'prog')->addSelect('prog')
            ->join('prog.professionalFamily', 'f')
            ->where('f.academicYear = :year')
            ->setParameter('year', $centre->getActiveAcademicYear()->getId(), 'uuid')
            ->orderBy('prog.name', 'ASC')
            ->addOrderBy('py.name', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
