<?php

namespace App\Service;

use App\Entity\EducationalCentre;
use App\Repository\EducationalCentreRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class TenantContext
{
    private const SESSION_KEY = 'tenant.centre_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EducationalCentreRepository $centres,
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

        return $this->centres->findByIdWithActiveYear($id);
    }

    public function selectCentre(EducationalCentre $centre): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $centre->getId()->toRfc4122());
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }
}
