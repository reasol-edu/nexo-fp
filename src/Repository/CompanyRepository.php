<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Stay;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /** @return Query<null, Company> */
    public function createByCentreOrderedByNameQuery(EducationalCentre $centre): Query
    {
        return $this->createByCentreFilteredQuery($centre);
    }

    /** @return Query<null, Company> */
    public function createByCentreFilteredQuery(EducationalCentre $centre, string $search = ''): Query
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.educationalCentre = :centre')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->orderBy('c.name', 'ASC');

        if ($search !== '') {
            $q = '%' . $search . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(c.name) LIKE LOWER(:q)',
                    'LOWER(c.vatNumber) LIKE LOWER(:q)',
                    'LOWER(c.city) LIKE LOWER(:q)',
                )
            )->setParameter('q', $q);
        }

        return $qb->getQuery();
    }

    public function findByIdAndCentre(string $id, EducationalCentre $centre): ?Company
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->andWhere('c.educationalCentre = :centre')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByVatNumberAndCentre(string $vatNumber, EducationalCentre $centre): ?Company
    {
        return $this->createQueryBuilder('c')
            ->where('c.vatNumber = :vatNumber')
            ->andWhere('c.educationalCentre = :centre')
            ->setParameter('vatNumber', $vatNumber)
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasLiaisonInCentre(Teacher $teacher, EducationalCentre $centre): bool
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.liaisons', 'l')
            ->where('l.id = :teacher')
            ->andWhere('c.educationalCentre = :centre')
            ->setParameter('teacher', $teacher->getId(), 'uuid')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function hasLiaisonPositionInStay(Teacher $teacher, Stay $stay): bool
    {
        return (int) $this->getEntityManager()
            ->createQuery('
                SELECT COUNT(tp.id)
                FROM App\Entity\TrainingPosition tp
                JOIN tp.workcenter wc
                JOIN wc.company c
                JOIN c.liaisons l
                WHERE l.id = :teacherId AND tp.stay = :stay
            ')
            ->setParameter('teacherId', $teacher->getId(), 'uuid')
            ->setParameter('stay', $stay->getId(), 'uuid')
            ->getSingleScalarResult() > 0;
    }
}
