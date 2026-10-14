<?php

namespace App\Repository;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AcademicYear>
 */
class AcademicYearRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AcademicYear::class);
    }

    public function findById(string $id): ?AcademicYear
    {
        return $this->createQueryBuilder('ay')
            ->where('ay.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return AcademicYear[] */
    public function findByCentreOrderedByName(EducationalCentre $centre): array
    {
        return $this->createQueryBuilder('ay')
            ->where('ay.educationalCentre = :centre')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->orderBy('ay.name', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Datos que cuelgan de un curso y se perderían al eliminarlo: oferta formativa (familias, enseñanzas,
     * niveles y grupos) y estancias con sus puestos. La pertenencia de docentes al curso no cuenta: se
     * retira con él.
     *
     * @return array{families: int, programmes: int, levels: int, groups: int, stays: int, positions: int}
     */
    public function countContent(AcademicYear $year): array
    {
        $em    = $this->getEntityManager();
        $count = static fn (string $dql) => (int) $em->createQuery($dql)
            ->setParameter('year', $year->getId(), 'uuid')->getSingleScalarResult();

        return [
            'families'   => $count('SELECT COUNT(f.id) FROM App\Entity\ProfessionalFamily f WHERE f.academicYear = :year'),
            'programmes' => $count('SELECT COUNT(p.id) FROM App\Entity\Programme p WHERE p.academicYear = :year'),
            'levels'     => $count('SELECT COUNT(l.id) FROM App\Entity\ProgrammeYear l JOIN l.programme p WHERE p.academicYear = :year'),
            'groups'     => $count('SELECT COUNT(g.id) FROM App\Entity\Group g JOIN g.programmeYear l JOIN l.programme p WHERE p.academicYear = :year'),
            'stays'      => $count('SELECT COUNT(s.id) FROM App\Entity\Stay s WHERE s.academicYear = :year'),
            'positions'  => $count('SELECT COUNT(tp.id) FROM App\Entity\TrainingPosition tp JOIN tp.stay s WHERE s.academicYear = :year'),
        ];
    }

    public function existsByCentreAndName(EducationalCentre $centre, string $name): bool
    {
        return $this->createQueryBuilder('ay')
            ->select('1')
            ->where('ay.educationalCentre = :centre')
            ->andWhere('ay.name = :name')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }

    public function findByCentreAndId(EducationalCentre $centre, string $yearId): ?AcademicYear
    {
        return $this->createQueryBuilder('ay')
            ->where('ay.educationalCentre = :centre')
            ->andWhere('ay.id = :id')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->setParameter('id', $yearId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
