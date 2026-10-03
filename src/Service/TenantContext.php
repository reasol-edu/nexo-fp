<?php

namespace App\Service;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Repository\AcademicYearRepository;
use App\Repository\EducationalCentreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Uid\Uuid;

final class TenantContext implements TenantContextInterface
{
    private const SESSION_KEY      = 'tenant.centre_id';
    private const SESSION_YEAR_KEY = 'tenant.year_id';
    private const REQUEST_ATTRIBUTE = '_tenant.resolved_centre';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EducationalCentreRepository $centres,
        private readonly AcademicYearRepository $years,
        private readonly EntityManagerInterface $em,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function isSelected(): bool
    {
        return $this->requestStack->getSession()->has(self::SESSION_KEY);
    }

    public function getSelectedCentre(): ?EducationalCentre
    {
        $session = $this->requestStack->getSession();
        $id      = $session->get(self::SESSION_KEY);

        // Un valor mal formado (una cookie de una versión anterior, una sesión truncada) equivale a
        // «sin centro»: no debe llegar al repositorio, donde el tipo uuid de DBAL fallaría al convertirlo
        // y convertiría una sesión obsoleta en un error 500.
        if (!\is_string($id) || !Uuid::isValid($id)) {
            return null;
        }

        // Se resuelve una sola vez por petición (se consulta muchas veces: subscriber, controladores,
        // Twig). Va en la petición y no en el servicio para no arrastrarlo entre peticiones.
        $request = $this->requestStack->getCurrentRequest();
        $cached  = $request?->attributes->get(self::REQUEST_ATTRIBUTE);
        if (\is_array($cached) && $cached['id'] === $id) {
            return $cached['centre'];
        }

        $centre = $this->centres->findByIdWithActiveYear($id);

        // Ensure activeAcademicYear is not stale from a prior identity-map load
        // (e.g. the subscriber loaded the centre without the JOIN in the same request)
        if ($centre !== null && $this->em->getUnitOfWork()->isInIdentityMap($centre)) {
            $this->em->refresh($centre);
        }

        // El centro solo se comprobaba al elegirlo: se vuelve a comprobar en cada petición para que un
        // docente al que se le retira el acceso (o un administrador que pierde el rol) con la sesión
        // abierta deje de ver sus datos enseguida y vuelva al selector de centro (TenantContextSubscriber).
        $user = $this->tokenStorage->getToken()?->getUser();
        if ($centre !== null && $user instanceof Teacher && !$this->centres->isAccessibleByTeacher($centre, $user)) {
            $session->remove(self::SESSION_KEY);
            $session->remove(self::SESSION_YEAR_KEY);
            $centre = null;
        }

        $request?->attributes->set(self::REQUEST_ATTRIBUTE, ['id' => $id, 'centre' => $centre]);

        return $centre;
    }

    public function selectCentre(EducationalCentre $centre): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $centre->getId()->toRfc4122());
        $this->forgetResolvedCentre();
        $this->clearYear();
    }

    public function getViewYear(EducationalCentre $centre): ?AcademicYear
    {
        $id = $this->requestStack->getSession()->get(self::SESSION_YEAR_KEY);
        if (!\is_string($id) || !Uuid::isValid($id)) {
            return $centre->getActiveAcademicYear();
        }

        $year = $this->years->findByCentreAndId($centre, $id);

        return $year ?? $centre->getActiveAcademicYear();
    }

    public function selectYear(AcademicYear $year): void
    {
        $this->requestStack->getSession()->set(self::SESSION_YEAR_KEY, $year->getId()->toRfc4122());
    }

    public function clearYear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_YEAR_KEY);
    }

    public function isViewingNonActiveYear(EducationalCentre $centre): bool
    {
        $activeYear = $centre->getActiveAcademicYear();
        if ($activeYear === null) {
            return false;
        }

        $viewYear = $this->getViewYear($centre);
        if ($viewYear === null) {
            return false;
        }

        return $viewYear->getId()->toRfc4122() !== $activeYear->getId()->toRfc4122();
    }

    public function canSwitchCentre(Teacher $teacher): bool
    {
        return \count($this->centres->findAccessibleByTeacher($teacher)) > 1;
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
        $this->forgetResolvedCentre();
    }

    private function forgetResolvedCentre(): void
    {
        $this->requestStack->getCurrentRequest()?->attributes->remove(self::REQUEST_ATTRIBUTE);
    }
}
