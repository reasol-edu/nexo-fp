<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\EducationalCentre;
use App\Pagination\Paginator;
use App\Repository\ProfessionalFamilyRepository;
use App\Repository\ProgrammeRepository;
use App\Repository\StayRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    public int $page = 1;

    private ?Paginator $paginationCache = null;
    /** @var \App\Entity\Stay[]|null */
    private ?array $itemsCache = null;
    /** @var array<string, mixed>|null */
    private ?array $statsCache = null;

    public function __construct(
        private readonly StayRepository $stays,
        private readonly ProfessionalFamilyRepository $families,
        private readonly ProgrammeRepository $programmes,
        #[Autowire(env: 'int:APP_PAGE_SIZE')] private readonly int $pageSize,
    ) {}

    public function getPagination(): Paginator
    {
        if ($this->paginationCache !== null) {
            return $this->paginationCache;
        }

        $year = $this->centre->getActiveAcademicYear();
        $query = $year !== null
            ? $this->stays->createByCentreFilteredQuery($year, $this->search, $this->familyId, $this->programmeId)
            : $this->stays->findNoneQuery();

        $this->paginationCache = new Paginator($query, $this->page, $this->pageSize);

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

        return $this->families->findByAcademicYearFiltered($year, '');
    }

    /** @return \App\Entity\Programme[] */
    public function getAvailableProgrammes(): array
    {
        $year = $this->centre->getActiveAcademicYear();
        if ($year === null) {
            return [];
        }

        return $this->programmes->findByAcademicYearOrderedByFamilyAndName($year);
    }

    #[LiveAction]
    public function resetPage(): void
    {
        $this->page = 1;
    }

    #[LiveAction]
    public function setPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }
}
