<?php

namespace App\Repository;

use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Teacher>
 */
class TeacherRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Teacher::class);
    }

    public function findById(string $id): ?Teacher
    {
        return $this->createQueryBuilder('t')
            ->where('t.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Teacher[] */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name.lastName', 'ASC')
            ->addOrderBy('t.name.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByUsername(string $username): ?Teacher
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Teacher) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /** @return Teacher[] */
    public function search(string $query, int $limit = 10): array
    {
        $q = '%' . $query . '%';

        return $this->createQueryBuilder('t')
            ->where('LOWER(t.name.firstName) LIKE LOWER(:q)')
            ->orWhere('LOWER(t.name.lastName) LIKE LOWER(:q)')
            ->orWhere('LOWER(t.username) LIKE LOWER(:q)')
            ->setParameter('q', $q)
            ->orderBy('t.name.lastName', 'ASC')
            ->addOrderBy('t.name.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.active = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAdmins(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.admin = true')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
