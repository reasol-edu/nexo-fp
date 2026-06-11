<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Entity\Teacher;
use App\Pagination\Paginator;
use App\Repository\TeacherRepository;
use App\Service\AppSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class TeacherListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly TeacherRepository $teachers,
        private readonly AppSettings $appSettings,
    ) {}

    public function mount(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    /** @return Paginator<Teacher> */
    public function getPagination(): Paginator
    {
        return new Paginator(
            $this->teachers->createFilteredOrderedByNameQuery(trim($this->search)),
            max(1, $this->page),
            (int) $this->appSettings->get('page.size'),
        );
    }

    #[LiveAction]
    public function setPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }
}
