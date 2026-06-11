<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\EducationalCentre;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Pagination\Paginator;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\StayRepository;
use App\Security\Voter\EducationalCentreVoter;
use App\Service\AppSettings;
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

    /** @var Paginator<Stay>|null */
    private ?Paginator $paginationCache = null;
    /** @var \App\Entity\Stay[]|null */
    private ?array $itemsCache = null;
    /** @var array<string, mixed>|null */
    private ?array $statsCache = null;

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
        $this->page = max(1, min($this->page, 9999));
    }

    public function __construct(
        private readonly StayRepository $stays,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        private readonly AppSettings $appSettings,
    ) {}

    /** @return Paginator<Stay> */
    public function getPagination(): Paginator
    {
        if ($this->paginationCache !== null) {
            return $this->paginationCache;
        }

        $year = $this->centre->getActiveAcademicYear();

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

        $this->paginationCache = new Paginator($query, $this->page, (int) $this->appSettings->get('page.size'));

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

    /** @return \App\Entity\ProfessionalFamily[] */
    public function getAvailableFamilies(): array
    {
        $year = $this->centre->getActiveAcademicYear();
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
        $year = $this->centre->getActiveAcademicYear();
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
    }

    #[LiveAction]
    public function changeFamilyFilter(): void
    {
        $this->programmeId = '';
        $this->page = 1;
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

    #[LiveAction]
    public function setPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }
}
