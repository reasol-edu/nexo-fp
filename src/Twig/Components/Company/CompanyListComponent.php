<?php

declare(strict_types=1);

namespace App\Twig\Components\Company;

use App\Entity\Company;
use App\Pagination\Paginator;
use App\Repository\CompanyRepository;
use App\Security\Voter\CompanyVoter;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly TenantContext $tenantContext,
        #[Autowire(env: 'int:APP_PAGE_SIZE')] private readonly int $pageSize,
    ) {}

    public function mount(): void
    {
        $centre = $this->tenantContext->getSelectedCentre();
        if ($centre === null) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted(CompanyVoter::SECTION, $centre);
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
            $this->companies->createByCentreFilteredQuery($centre, trim($this->search)),
            max(1, $this->page),
            $this->pageSize,
        );
    }

    #[LiveAction]
    public function setPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }
}
