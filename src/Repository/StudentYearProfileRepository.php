<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AcademicYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\StudentYearProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StudentYearProfile>
 */
class StudentYearProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StudentYearProfile::class);
    }

    public function findByStudentAndYear(Student $student, AcademicYear $year): ?StudentYearProfile
    {
        return $this->createQueryBuilder('p')
            ->where('p.student = :student')
            ->andWhere('p.academicYear = :year')
            ->setParameter('student', $student->getId(), 'uuid')
            ->setParameter('year', $year->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Las fichas del curso de todo el alumnado matriculado en la estancia (una consulta),
     * indexadas por el id (RFC 4122) del estudiante. Quien no tiene ficha no aparece.
     *
     * @return array<string, StudentYearProfile>
     */
    public function findByStay(Stay $stay): array
    {
        /** @var list<StudentYearProfile> $profiles */
        $profiles = $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from(StudentYearProfile::class, 'p')
            ->join(Stay::class, 's', 'WITH', 's.id = :stay')
            ->join('s.students', 'st')
            ->where('p.student = st')
            ->andWhere('p.academicYear = s.academicYear')
            ->setParameter('stay', $stay->getId(), 'uuid')
            ->getQuery()
            ->getResult();

        return $this->indexByStudent($profiles);
    }

    /**
     * Todas las fichas del curso (una consulta), indexadas por el id (RFC 4122) del estudiante.
     *
     * @return array<string, StudentYearProfile>
     */
    public function findByYear(AcademicYear $year): array
    {
        /** @var list<StudentYearProfile> $profiles */
        $profiles = $this->createQueryBuilder('p')
            ->addSelect('st')
            ->join('p.student', 'st')
            ->where('p.academicYear = :year')
            ->setParameter('year', $year->getId(), 'uuid')
            ->getQuery()
            ->getResult();

        return $this->indexByStudent($profiles);
    }

    /**
     * Estudiantes del curso que realizan la FFEOE y de los que consta que no tienen NUSS (una consulta).
     *
     * @return array<string, true> ids (RFC 4122) de estudiante
     */
    public function findMissingNussStudentIds(AcademicYear $year): array
    {
        /** @var list<array{id: mixed}> $rows */
        $rows = $this->createQueryBuilder('p')
            ->select('st.id AS id')
            ->join('p.student', 'st')
            ->where('p.academicYear = :year')
            ->andWhere('p.takesFfeoe = true')
            ->andWhere('p.hasNuss = false')
            ->setParameter('year', $year->getId(), 'uuid')
            ->getQuery()
            ->getArrayResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = $row['id'];
            $ids[$id instanceof \Symfony\Component\Uid\Uuid ? $id->toRfc4122() : (string) $id] = true;
        }

        return $ids;
    }

    /**
     * @param list<StudentYearProfile> $profiles
     *
     * @return array<string, StudentYearProfile>
     */
    private function indexByStudent(array $profiles): array
    {
        $map = [];
        foreach ($profiles as $profile) {
            $map[$profile->getStudent()->getId()->toRfc4122()] = $profile;
        }

        return $map;
    }
}
