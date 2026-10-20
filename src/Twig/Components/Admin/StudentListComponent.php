<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\Student;
use App\Pagination\Paginator;
use App\Repository\GroupRepository;
use App\Repository\StudentRepository;
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
class StudentListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public EducationalCentre $centre;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public string $groupId = '';

    /** Filtro por ficha del curso: '', 'sin-nuss' o 'no-ffeoe'. */
    #[LiveProp(writable: true)]
    public string $profile = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public string $sort = '';

    #[LiveProp(writable: true)]
    public string $sortDir = 'asc';

    public function __construct(
        private readonly StudentRepository $students,
        private readonly \App\Repository\StudentYearProfileRepository $profiles,
        private readonly GroupRepository $groups,
        private readonly AppSettings $appSettings,
        private readonly TenantContext $tenantContext,
    ) {}

    public function mount(EducationalCentre $centre): void
    {
        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);
        $this->centre = $centre;
    }

    /** @return Group[] */
    public function getAvailableGroups(): array
    {
        return $this->groups->findByActiveYearOfCentreOrderedByName(
            $this->centre,
            $this->tenantContext->getViewYear($this->centre),
        );
    }

    /** @return Paginator<Student> */
    public function getPagination(): Paginator
    {
        $year = $this->tenantContext->getViewYear($this->centre);
        if ($year === null) {
            return new Paginator($this->students->findNoneQuery(), 1, (int) $this->appSettings->get('page.size'));
        }

        return new Paginator(
            $this->students->createByCentreFilteredQuery(
                $this->centre,
                trim($this->search),
                trim($this->groupId),
                $this->sort,
                $this->sortDir,
                $year,
                $this->profile,
            ),
            max(1, $this->page),
            (int) $this->appSettings->get('page.size'),
        );
    }

    /**
     * Fichas del curso del alumnado (una consulta), indexadas por id de estudiante.
     *
     * @return array<string, \App\Entity\StudentYearProfile>
     */
    public function getProfiles(): array
    {
        $year = $this->tenantContext->getViewYear($this->centre);

        return $year === null ? [] : $this->profiles->findByYear($year);
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->groupId !== '' || $this->profile !== '';
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
