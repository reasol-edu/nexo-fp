<?php

namespace App\Repository;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<EducationalCentre>
 */
class EducationalCentreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EducationalCentre::class);
    }

    public function findByCode(string $code): ?EducationalCentre
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findById(string $id): ?EducationalCentre
    {
        return $this->find(Uuid::fromRfc4122($id));
    }

    public function findByIdWithActiveYear(string $id): ?EducationalCentre
    {
        return $this->createQueryBuilder('ec')
            ->leftJoin('ec.activeAcademicYear', 'ay')
            ->addSelect('ay')
            ->where('ec.id = :id')
            ->setParameter('id', Uuid::fromRfc4122($id))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return EducationalCentre[] */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('ec')
            ->orderBy('ec.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return EducationalCentre[] */
    public function findAccessibleByTeacher(Teacher $teacher): array
    {
        if ($teacher->isAdmin()) {
            return $this->findAllOrderedByName();
        }

        $merged = [];

        // Centres where teacher is listed as admin
        foreach ($this->createQueryBuilder('ec')
            ->join('ec.admins', 'a')
            ->where('a = :teacher')
            ->setParameter('teacher', $teacher)
            ->getQuery()
            ->getResult() as $centre) {
            $merged[$centre->getId()->toRfc4122()] = $centre;
        }

        // Centres via group membership — navigate from Group since ManyToOne associations are unidirectional
        foreach ($this->getEntityManager()->createQueryBuilder()
            ->select('g')
            ->from(Group::class, 'g')
            ->join('g.programmeYear', 'py')
            ->join('py.programme', 'prog')
            ->join('prog.academicYear', 'ay')
            ->where(':teacher MEMBER OF g.teachers OR g.tutor = :teacher')
            ->setParameter('teacher', $teacher)
            ->getQuery()
            ->getResult() as $group) {
            $ec = $group->getProgrammeYear()->getProgramme()->getAcademicYear()->getEducationalCentre();
            $merged[$ec->getId()->toRfc4122()] = $ec;
        }

        $centres = array_values($merged);
        usort($centres, static fn(EducationalCentre $a, EducationalCentre $b) => strcmp($a->getName(), $b->getName()));

        return $centres;
    }
}
