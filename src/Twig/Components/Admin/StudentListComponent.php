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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly StudentRepository $students,
        private readonly GroupRepository $groups,
        #[Autowire(env: 'int:APP_PAGE_SIZE')] private readonly int $pageSize,
    ) {}

    public function mount(EducationalCentre $centre): void
    {
        $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);
        $this->centre = $centre;
    }

    /** @return Group[] */
    public function getAvailableGroups(): array
    {
        return $this->groups->findByActiveYearOfCentreOrderedByName($this->centre);
    }

    /** @return Paginator<Student> */
    public function getPagination(): Paginator
    {
        if ($this->centre->getActiveAcademicYear() === null) {
            return new Paginator($this->students->findNoneQuery(), 1, $this->pageSize);
        }

        return new Paginator(
            $this->students->createByCentreFilteredQuery(
                $this->centre,
                trim($this->search),
                trim($this->groupId),
            ),
            max(1, $this->page),
            $this->pageSize,
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
