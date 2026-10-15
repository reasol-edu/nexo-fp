<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Entity\Workcenter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Workcenter>
 */
class WorkcenterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workcenter::class);
    }

    /**
     * Nombre de la empresa y del centro de trabajo de los indicados («Empresa — Centro»), como mucho 200.
     * Se compara de uno en uno con tipo «uuid» porque `IN (:ids)` falla con ids binarios.
     *
     * @param list<string> $ids
     * @return array<string, string> id (RFC 4122) → «Empresa — Centro»
     */
    public function findLabelsByIds(array $ids): array
    {
        $ids = \array_slice(array_values(array_unique($ids)), 0, 200);
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('w')->select('w.id AS id', 'w.name AS name', 'c.name AS company')->join('w.company', 'c');
        $or = $qb->expr()->orX();
        foreach ($ids as $i => $id) {
            if (\Symfony\Component\Uid\Uuid::isValid($id)) {
                $or->add('w.id = :id' . $i);
                $qb->setParameter('id' . $i, $id, 'uuid');
            }
        }
        if ($or->count() === 0) {
            return [];
        }

        /** @var list<array{id: mixed, name: string, company: string}> $rows */
        $rows = $qb->where($or)->getQuery()->getResult();

        $labels = [];
        foreach ($rows as $row) {
            $labels[(string) $row['id']] = $row['company'] . ' — ' . $row['name'];
        }

        return $labels;
    }

    /** @return Workcenter[] */
    public function findByCompanyOrderedByName(Company $company): array
    {
        return $this->createQueryBuilder('w')
            ->where('w.company = :company')
            ->setParameter('company', $company->getId(), 'uuid')
            ->orderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCompanyAndId(Company $company, string $id): ?Workcenter
    {
        return $this->createQueryBuilder('w')
            ->where('w.company = :company')
            ->andWhere('w.id = :id')
            ->setParameter('company', $company->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Workcenter[] */
    public function findByCentreOrdered(EducationalCentre $centre): array
    {
        return $this->createQueryBuilder('w')
            ->join('w.company', 'co')
            ->addSelect('co')
            ->where('co.educationalCentre = :centre')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->orderBy('co.name', 'ASC')
            ->addOrderBy('w.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Workcenter[] */
    public function findByCentreAndLiaisonOrdered(EducationalCentre $centre, Teacher $liaison): array
    {
        return $this->createQueryBuilder('w')
            ->join('w.company', 'co')
            ->join('co.liaisons', 'l')
            ->addSelect('co')
            ->where('co.educationalCentre = :centre')
            ->andWhere('l.id = :liaison')
            ->orderBy('co.name', 'ASC')
            ->addOrderBy('w.name', 'ASC')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->setParameter('liaison', $liaison->getId(), 'uuid')
            ->getQuery()
            ->getResult();
    }

    public function findByCentreAndId(EducationalCentre $centre, string $id): ?Workcenter
    {
        return $this->createQueryBuilder('w')
            ->join('w.company', 'co')
            ->where('co.educationalCentre = :centre')
            ->andWhere('w.id = :id')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
