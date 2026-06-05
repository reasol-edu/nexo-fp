<?php

declare(strict_types=1);

namespace App\Autocomplete;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\Teacher;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\UX\Autocomplete\EntityAutocompleterInterface;

/**
 * @implements EntityAutocompleterInterface<Teacher>
 */
#[AutoconfigureTag('ux.entity_autocompleter', ['alias' => 'teacher_liaison'])]
class TeacherLiaisonAutocompleter implements EntityAutocompleterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenantContext,
    ) {}

    public function getEntityClass(): string
    {
        return Teacher::class;
    }

    public function createFilteredQueryBuilder(EntityRepository $repository, string $query): QueryBuilder
    {
        $centre = $this->tenantContext->getSelectedCentre();

        $qb = $repository->createQueryBuilder('t');

        if ($centre === null) {
            return $qb->where('1 = 0');
        }

        $searchFilter = $qb->expr()->orX(
            'LOWER(t.name.firstName) LIKE LOWER(:q)',
            'LOWER(t.name.lastName) LIKE LOWER(:q)',
            'LOWER(t.username) LIKE LOWER(:q)',
        );

        // Subquery 1: equipo directivo del centro
        $isAdmin = $qb->expr()->exists(
            $this->em->createQueryBuilder()
                ->select('1')
                ->from(EducationalCentre::class, 'ec')
                ->join('ec.admins', 'a')
                ->where('a = t')
                ->andWhere('ec.id = :centre')
                ->getDQL()
        );

        // Subquery 2: docentes de grupos del centro (vía programmeYear → programme → academicYear)
        $isGroupTeacher = $qb->expr()->exists(
            $this->em->createQueryBuilder()
                ->select('1')
                ->from(Group::class, 'g')
                ->join('g.teachers', 'gt')
                ->join('g.programmeYear', 'py')
                ->join('py.programme', 'p')
                ->join('p.academicYear', 'ay')
                ->where('gt = t')
                ->andWhere('ay.educationalCentre = :centre')
                ->getDQL()
        );

        return $qb
            ->where($searchFilter)
            ->andWhere($qb->expr()->orX($isAdmin, $isGroupTeacher))
            ->setParameter('q', '%' . $query . '%')
            ->setParameter('centre', $centre->getId(), 'uuid')
            ->orderBy('t.name.lastName', 'ASC')
            ->addOrderBy('t.name.firstName', 'ASC');
    }

    public function getLabel(object $entity): string
    {
        return $entity->getName()->getLastName() . ', ' . $entity->getName()->getFirstName();
    }

    public function getValue(object $entity): mixed
    {
        return $entity->getId()->toRfc4122();
    }

    public function getAttributes(object $entity): array
    {
        return [];
    }

    public function isGranted(Security $security): bool
    {
        return $security->isGranted('IS_AUTHENTICATED_FULLY');
    }

    public function getGroupBy(): mixed
    {
        return null;
    }
}
