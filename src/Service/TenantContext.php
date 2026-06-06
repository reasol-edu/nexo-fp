<?php

namespace App\Service;

use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Repository\EducationalCentreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class TenantContext
{
    private const SESSION_KEY = 'tenant.centre_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EducationalCentreRepository $centres,
        private readonly EntityManagerInterface $em,
    ) {}

    public function isSelected(): bool
    {
        return $this->requestStack->getSession()->has(self::SESSION_KEY);
    }

    public function getSelectedCentre(): ?EducationalCentre
    {
        $id = $this->requestStack->getSession()->get(self::SESSION_KEY);
        if (!\is_string($id)) {
            return null;
        }

        $centre = $this->centres->findByIdWithActiveYear($id);

        // Ensure activeAcademicYear is not stale from a prior identity-map load
        // (e.g. the subscriber loaded the centre without the JOIN in the same request)
        if ($centre !== null && $this->em->getUnitOfWork()->isInIdentityMap($centre)) {
            $this->em->refresh($centre);
        }

        return $centre;
    }

    public function selectCentre(EducationalCentre $centre): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $centre->getId()->toRfc4122());
    }

    public function canSwitchCentre(Teacher $teacher): bool
    {
        return \count($this->centres->findAccessibleByTeacher($teacher)) > 1;
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }
}
