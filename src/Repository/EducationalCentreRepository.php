<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

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
        return $this->createQueryBuilder('ec')
            ->where('ec.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByIdWithActiveYear(string $id): ?EducationalCentre
    {
        return $this->createQueryBuilder('ec')
            ->leftJoin('ec.activeAcademicYear', 'ay')
            ->addSelect('ay')
            ->where('ec.id = :id')
            ->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('ec')
            ->select('COUNT(ec.id)')
            ->getQuery()
            ->getSingleScalarResult();
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
    public function findAllWithActiveYear(): array
    {
        return $this->createQueryBuilder('ec')
            ->leftJoin('ec.activeAcademicYear', 'ay')
            ->addSelect('ay')
            ->orderBy('ec.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return Query<null, EducationalCentre> */
    public function createAllWithActiveYearQuery(): Query
    {
        return $this->createAllWithActiveYearFilteredQuery();
    }

    /** Columnas ordenables: clave pública => campo DQL (allowlist contra inyección). */
    private const SORTABLE = [
        'code' => 'ec.code',
        'name' => 'ec.name',
        'city' => 'ec.city',
    ];

    /** @return Query<null, EducationalCentre> */
    public function createAllWithActiveYearFilteredQuery(string $search = '', string $sort = '', string $sortDir = 'asc'): Query
    {
        $qb = $this->createQueryBuilder('ec')
            ->leftJoin('ec.activeAcademicYear', 'ay')
            ->addSelect('ay');

        $dir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';
        $qb->orderBy(self::SORTABLE[$sort] ?? 'ec.name', isset(self::SORTABLE[$sort]) ? $dir : 'ASC');

        if ($search !== '') {
            $q = '%' . $search . '%';
            $qb->where(
                $qb->expr()->orX(
                    'UNACCENT(LOWER(ec.name)) LIKE UNACCENT(LOWER(:q))',
                    'UNACCENT(LOWER(ec.code)) LIKE UNACCENT(LOWER(:q))',
                    'UNACCENT(LOWER(ec.city)) LIKE UNACCENT(LOWER(:q))',
                )
            )->setParameter('q', $q);
        }

        return $qb->getQuery();
    }

    /** Si $teacher puede trabajar en $centre: la misma regla que findAccessibleByTeacher(). */
    public function isAccessibleByTeacher(EducationalCentre $centre, Teacher $teacher): bool
    {
        if ($teacher->isAdmin()) {
            return true;
        }

        foreach ($this->findAccessibleByTeacher($teacher) as $accessible) {
            if ($accessible->getId()->equals($centre->getId())) {
                return true;
            }
        }

        return false;
    }

    /** @return EducationalCentre[] */
    public function findAccessibleByTeacher(Teacher $teacher): array
    {
        if ($teacher->isAdmin()) {
            return $this->findAllOrderedByName();
        }

        $merged = [];

        $tid = $teacher->getId();

        // Centres where teacher is listed as admin
        foreach ($this->createQueryBuilder('ec')
            ->join('ec.admins', 'a')
            ->where('a.id = :tid')
            ->setParameter('tid', $tid, 'uuid')
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
            ->leftJoin('g.teachers', 'gt')
            ->leftJoin('g.tutors', 'gtu')
            ->where('gt.id = :tid OR gtu.id = :tid')
            ->setParameter('tid', $tid, 'uuid')
            ->distinct()
            ->getQuery()
            ->getResult() as $group) {
            $ec = $group->getProgrammeYear()->getProgramme()->getAcademicYear()->getEducationalCentre();
            $merged[$ec->getId()->toRfc4122()] = $ec;
        }

        // Centres where teacher is a liaison for any company
        foreach ($this->getEntityManager()->createQueryBuilder()
            ->select('co')
            ->from(Company::class, 'co')
            ->join('co.liaisons', 'l')
            ->where('l.id = :tid')
            ->setParameter('tid', $tid, 'uuid')
            ->getQuery()
            ->getResult() as $company) {
            $ec = $company->getEducationalCentre();
            $merged[$ec->getId()->toRfc4122()] = $ec;
        }

        // Centres where teacher is head of a professional family
        foreach ($this->getEntityManager()->createQueryBuilder()
            ->select('pf')
            ->from(ProfessionalFamily::class, 'pf')
            ->join('pf.academicYear', 'ay')
            ->where('pf.head = :tid')
            ->setParameter('tid', $tid, 'uuid')
            ->getQuery()
            ->getResult() as $family) {
            $ec = $family->getAcademicYear()->getEducationalCentre();
            $merged[$ec->getId()->toRfc4122()] = $ec;
        }

        // Centres where teacher coordinates a programme (CompanyVoter/StayVoter ya les dan acceso a la sección)
        foreach ($this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from(Programme::class, 'p')
            ->join('p.coordinators', 'c')
            ->where('c.id = :tid')
            ->setParameter('tid', $tid, 'uuid')
            ->getQuery()
            ->getResult() as $programme) {
            $ec = $programme->getAcademicYear()->getEducationalCentre();
            $merged[$ec->getId()->toRfc4122()] = $ec;
        }

        $centres = array_values($merged);
        usort($centres, static fn(EducationalCentre $a, EducationalCentre $b) => strcmp($a->getName(), $b->getName()));

        return $centres;
    }
}
