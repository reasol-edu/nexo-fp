<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\EducationalCentre;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Pagination\Paginator;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\StayRepository;
use App\Repository\TrainingPositionRepository;
use App\Security\Voter\EducationalCentreVoter;
use App\Service\AppSettings;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class StayListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public EducationalCentre $centre;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public string $familyId = '';

    #[LiveProp(writable: true)]
    public string $programmeId = '';

    #[LiveProp(writable: true)]
    public bool $showCurrent = true;

    #[LiveProp(writable: true)]
    public bool $showFuture = true;

    #[LiveProp(writable: true)]
    public bool $showPast = true;

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public string $tab = 'stays';

    #[LiveProp(writable: true)]
    public int $pendingPage = 1;

    #[LiveProp(writable: true)]
    public string $pendingSort = 'startDate';

    #[LiveProp(writable: true)]
    public string $pendingSortDir = 'ASC';

    /** @var Paginator<Stay>|null */
    private ?Paginator $paginationCache = null;
    /** @var \App\Entity\Stay[]|null */
    private ?array $itemsCache = null;
    /** @var array<string, mixed>|null */
    private ?array $statsCache = null;

    /** @var Paginator<TrainingPosition>|null */
    private ?Paginator $pendingPaginationCache = null;
    /** @var TrainingPosition[]|null */
    private ?array $pendingItemsCache = null;

    public function mount(): void
    {
        if (mb_strlen($this->search) > 255) {
            $this->search = mb_substr($this->search, 0, 255);
        }
        if ($this->familyId !== '' && !Uuid::isValid($this->familyId)) {
            $this->familyId = '';
        }
        if ($this->programmeId !== '' && !Uuid::isValid($this->programmeId)) {
            $this->programmeId = '';
        }

        // Validar que los UUIDs de familia/enseñanza pertenecen al centro activo.
        // Sin esto, los filtros funcionan como un oráculo de confirmación: un UUID
        // válido pero ajeno al centro devuelve 0 filas (inofensivo), pero un UUID
        // del centro activo se acepta. Limpiamos aquí para que el frontend solo
        // pueda mantener IDs que efectivamente vea en sus selectores.
        // (isset: el componente se construye sin centre en algunos tests unitarios;
        // en uso real el LiveProp obligatorio ya está hidratado al entrar aquí.)
        if (isset($this->centre)) {
            $year = $this->tenantContext->getViewYear($this->centre);
            if ($year === null) {
                $this->familyId = '';
                $this->programmeId = '';
            } else {
                if ($this->familyId !== '' && $this->families->findByYearAndId($year, $this->familyId) === null) {
                    $this->familyId = '';
                    $this->programmeId = '';
                }
                if ($this->programmeId !== '' && $this->programmes->findByAcademicYearAndId($year, $this->programmeId) === null) {
                    $this->programmeId = '';
                }
            }
        }

        $this->page = max(1, min($this->page, 9999));
        $this->pendingPage = max(1, min($this->pendingPage, 9999));
        if (!in_array($this->tab, ['stays', 'pending'], true)) {
            $this->tab = 'stays';
        }
        if (!in_array($this->pendingSort, ['startDate', 'student', 'workcenter'], true)) {
            $this->pendingSort = 'startDate';
        }
        if (!in_array($this->pendingSortDir, ['ASC', 'DESC'], true)) {
            $this->pendingSortDir = 'ASC';
        }
    }

    public function __construct(
        private readonly StayRepository $stays,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly AppSettings $appSettings,
        private readonly TrainingPositionRepository $positions,
        private readonly TenantContext $tenantContext,
    ) {}

    /** @return Paginator<Stay> */
    public function getPagination(): Paginator
    {
        if ($this->paginationCache !== null) {
            return $this->paginationCache;
        }

        $year = $this->tenantContext->getViewYear($this->centre);

        $periods = [];
        if ($this->showCurrent) {
            $periods[] = 'current';
        }
        if ($this->showFuture) {
            $periods[] = 'future';
        }
        if ($this->showPast) {
            $periods[] = 'past';
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        $query = $year !== null
            ? $this->stays->createByCentreFilteredQuery($year, $this->search, $this->familyId, $this->programmeId, $periods, $viewer)
            : $this->stays->findNoneQuery();

        $pagination = new Paginator($query, $this->page, (int) $this->appSettings->get('page.size'));

        $lastPage = max(1, $pagination->getTotalPages());
        if ($this->page > $lastPage) {
            $this->page = $lastPage;
            $pagination = new Paginator($query, $this->page, (int) $this->appSettings->get('page.size'));
        }

        $this->paginationCache = $pagination;

        return $this->paginationCache;
    }

    /** @return \App\Entity\Stay[] */
    public function getItems(): array
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        $this->itemsCache = iterator_to_array($this->getPagination()->getItems(), false);
        $this->statsCache = $this->stays->findStatsForStays($this->itemsCache);

        return $this->itemsCache;
    }

    /** @return array<string, mixed> */
    public function getStats(): array
    {
        $this->getItems();

        return $this->statsCache ?? [];
    }

    /** @return Paginator<TrainingPosition> */
    public function getPendingPagination(): Paginator
    {
        if ($this->pendingPaginationCache !== null) {
            return $this->pendingPaginationCache;
        }

        $year = $this->tenantContext->getViewYear($this->centre);

        $periods = [];
        if ($this->showCurrent) {
            $periods[] = 'current';
        }
        if ($this->showFuture) {
            $periods[] = 'future';
        }
        if ($this->showPast) {
            $periods[] = 'past';
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        $query = $year !== null
            ? $this->positions->createPendingSignaturesQuery(
                $year, $this->search, $this->familyId, $this->programmeId,
                $periods, $this->pendingSort, $this->pendingSortDir, $viewer,
            )
            : $this->positions->findNoneQuery();

        $pagination = new Paginator($query, $this->pendingPage, (int) $this->appSettings->get('page.size'));

        $lastPage = max(1, $pagination->getTotalPages());
        if ($this->pendingPage > $lastPage) {
            $this->pendingPage = $lastPage;
            $pagination = new Paginator($query, $this->pendingPage, (int) $this->appSettings->get('page.size'));
        }

        $this->pendingPaginationCache = $pagination;

        return $this->pendingPaginationCache;
    }

    /** @return TrainingPosition[] */
    public function getPendingItems(): array
    {
        if ($this->pendingItemsCache !== null) {
            return $this->pendingItemsCache;
        }

        $this->pendingItemsCache = iterator_to_array($this->getPendingPagination()->getItems(), false);

        return $this->pendingItemsCache;
    }

    public function getPendingCount(): int
    {
        return $this->getPendingPagination()->getTotalItems();
    }

    /** @return \App\Entity\ProfessionalFamily[] */
    public function getAvailableFamilies(): array
    {
        $year = $this->tenantContext->getViewYear($this->centre);
        if ($year === null) {
            return [];
        }

        $user = $this->getUser();

        if (!$user instanceof Teacher || $this->isGranted(EducationalCentreVoter::SECTION, $this->centre)) {
            return $this->families->findByAcademicYearFiltered($year, '');
        }

        return $this->families->findByAcademicYearVisibleToTeacher($year, $user);
    }

    /** @return \App\Entity\Programme[] */
    public function getAvailableProgrammes(): array
    {
        $year = $this->tenantContext->getViewYear($this->centre);
        if ($year === null) {
            return [];
        }

        $user = $this->getUser();

        if (!$user instanceof Teacher || $this->isGranted(EducationalCentreVoter::SECTION, $this->centre)) {
            return $this->programmes->findByAcademicYearFilteredByFamily($year, $this->familyId);
        }

        return $this->programmes->findByAcademicYearVisibleToTeacher($year, $user, $this->familyId);
    }

    #[LiveAction]
    public function resetPage(): void
    {
        $this->page = 1;
        $this->pendingPage = 1;
    }

    #[LiveAction]
    public function changeFamilyFilter(): void
    {
        $this->programmeId = '';
        $this->page = 1;
        $this->pendingPage = 1;
    }

    #[LiveAction]
    public function clearFilters(): void
    {
        $this->search = '';
        $this->familyId = '';
        $this->programmeId = '';
        $this->showCurrent = true;
        $this->showFuture = true;
        $this->showPast = true;
        $this->page = 1;
        $this->pendingPage = 1;
    }

    #[LiveAction]
    public function switchTab(#[LiveArg] string $tab): void
    {
        if (in_array($tab, ['stays', 'pending'], true)) {
            $this->tab = $tab;
        }
    }

    #[LiveAction]
    public function setPendingPage(#[LiveArg] int $page): void
    {
        $this->pendingPage = max(1, $page);
    }

    #[LiveAction]
    public function setPendingSort(#[LiveArg] string $sort): void
    {
        if (!in_array($sort, ['startDate', 'student', 'workcenter'], true)) {
            return;
        }
        if ($this->pendingSort === $sort) {
            $this->pendingSortDir = $this->pendingSortDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            $this->pendingSort = $sort;
            $this->pendingSortDir = 'ASC';
        }
        $this->pendingPage = 1;
    }

    #[LiveAction]
    public function setPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->familyId !== ''
            || $this->programmeId !== ''
            || !$this->showCurrent
            || !$this->showFuture
            || !$this->showPast;
    }
}
