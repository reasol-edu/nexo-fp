<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Pagination\Paginator;
use App\Repository\TeacherRepository;
use App\Security\Voter\EducationalCentreVoter;
use App\Service\AppSettings;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class TeacherCentreListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public EducationalCentre $centre;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly TeacherRepository $teachers,
        private readonly AppSettings $appSettings,
        private readonly TenantContext $tenantContext,
    ) {}

    public function mount(EducationalCentre $centre): void
    {
        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);
        $this->centre = $centre;
    }

    /** @return Paginator<Teacher> */
    public function getPagination(): Paginator
    {
        $year = $this->tenantContext->getViewYear($this->centre);
        if ($year === null) {
            return new Paginator($this->teachers->findNoneQuery(), 1, (int) $this->appSettings->get('page.size'));
        }

        return new Paginator(
            $this->teachers->createByAcademicYearFilteredQuery(
                $year,
                trim($this->search),
            ),
            max(1, $this->page),
            (int) $this->appSettings->get('page.size'),
        );
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
