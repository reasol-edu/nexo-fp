<?php

namespace App\Repository;

use App\Entity\EducationalCentre;
use App\Entity\ProfessionalFamily;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfessionalFamily>
 */
class ProfessionalFamilyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfessionalFamily::class);
    }

    public function isFamilyHeadInCentre(Teacher $teacher, EducationalCentre $centre): bool
    {
        return (int) $this->createQueryBuilder('pf')
            ->select('COUNT(pf.id)')
            ->join('pf.academicYear', 'ay')
            ->where('pf.head = :teacher')
            ->andWhere('ay.educationalCentre = :centre')
            ->setParameter('teacher', $teacher)
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
