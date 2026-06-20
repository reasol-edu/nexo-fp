<?php

declare(strict_types=1);

namespace App\Twig\Components\Company;

use App\Entity\Company;
use App\Pagination\Paginator;
use App\Repository\CompanyRepository;
use App\Security\Voter\CompanyVoter;
use App\Service\AppSettings;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class CompanyListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public string $sort = '';

    #[LiveProp(writable: true)]
    public string $sortDir = 'asc';

    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly TenantContext $tenantContext,
        private readonly AppSettings $appSettings,
    ) {}

    public function mount(): void
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(CompanyVoter::SECTION, $centre);
    }

    public function getCentreId(): string
    {
        return $this->tenantContext->getSelectedCentre()?->getId()->toRfc4122() ?? '';
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '';
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    /** @return Paginator<Company> */
    public function getPagination(): Paginator
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        return new Paginator(
            $this->companies->createByCentreFilteredQuery($centre, trim($this->search), $this->sort, $this->sortDir),
            max(1, $this->page),
            (int) $this->appSettings->get('page.size'),
        );
    }

    #[LiveAction]
    public function setPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }

    #[LiveAction]
    public function sortBy(#[LiveArg] string $column): void
    {
        if ($this->sort === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->sortDir = 'asc';
        }
        $this->page = 1;
    }
}
