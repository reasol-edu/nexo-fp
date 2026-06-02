<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Entity\EducationalCentre;
use App\Pagination\Paginator;
use App\Repository\ProfessionalFamilyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ProfessionalFamilyListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public EducationalCentre $centre;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly ProfessionalFamilyRepository $families,
        #[Autowire(env: 'int:APP_PAGE_SIZE')] private readonly int $pageSize,
    ) {}

    public function mount(EducationalCentre $centre): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->centre = $centre;
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function getPagination(): Paginator
    {
        $year = $this->centre->getActiveAcademicYear();

        return new Paginator(
            $year !== null
                ? $this->families->createByAcademicYearFilteredQuery($year, trim($this->search))
                : $this->families->findNoneQuery(),
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
