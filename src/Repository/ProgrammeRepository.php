<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Programme;
use App\Entity\ProfessionalFamily;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Programme>
 */
class ProgrammeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Programme::class);
    }

    /** @return Programme[] */
    public function findByFamilyOrderedByName(ProfessionalFamily $family): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.professionalFamily = :family')
            ->setParameter('family', $family->getId(), 'uuid')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFamilyAndId(ProfessionalFamily $family, string $id): ?Programme
    {
        return $this->createQueryBuilder('p')
            ->where('p.professionalFamily = :family')
            ->andWhere('p.id = :id')
            ->setParameter('family', $family->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
