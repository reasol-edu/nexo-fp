<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Pagination\Paginator;
use App\Repository\ActivityLogRepository;
use App\Repository\AcademicYearRepository;
use App\Repository\EducationalCentreRepository;
use App\Repository\TeacherRepository;
use App\Service\AppSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ActivityLogListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $dateFrom = '';

    #[LiveProp(writable: true)]
    public string $dateTo = '';

    #[LiveProp(writable: true)]
    public string $userId = '';

    #[LiveProp(writable: true)]
    public string $centreId = '';

    #[LiveProp(writable: true)]
    public string $yearId = '';

    #[LiveProp(writable: true)]
    public string $actionType = '';

    #[LiveProp(writable: true)]
    public string $sort = 'createdAt';

    #[LiveProp(writable: true)]
    public string $sortDir = 'desc';

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly ActivityLogRepository $logs,
        private readonly EducationalCentreRepository $centres,
        private readonly AcademicYearRepository $years,
        private readonly TeacherRepository $teachers,
        private readonly AppSettings $appSettings,
    ) {}

    public function mount(): void
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
    }

    /** @return Paginator<\App\Entity\ActivityLog> */
    public function getPagination(): Paginator
    {
        return new Paginator(
            $this->logs->createFilteredQuery([
                'dateFrom'   => $this->dateFrom,
                'dateTo'     => $this->dateTo,
                'userId'     => $this->userId,
                'centreId'   => $this->centreId,
                'yearId'     => $this->yearId,
                'actionType' => $this->actionType,
                'sort'       => $this->sort,
                'sortDir'    => $this->sortDir,
            ]),
            max(1, $this->page),
            (int) $this->appSettings->get('page.size'),
        );
    }

    /** @return EducationalCentre[] */
    public function getCentres(): array
    {
        return $this->centres->findAllOrderedByName();
    }

    /** @return AcademicYear[] */
    public function getYearsForSelectedCentre(): array
    {
        if ($this->centreId === '') {
            return [];
        }

        $centre = $this->centres->find($this->centreId);
        if ($centre === null) {
            return [];
        }

        return $this->years->findByCentreOrderedByName($centre);
    }

    public function getSelectedUser(): ?Teacher
    {
        if ($this->userId === '') {
            return null;
        }
        return $this->teachers->find($this->userId);
    }

    /** @return list<string> */
    public function getDistinctActionTypes(): array
    {
        return $this->logs->findDistinctActionTypes();
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
            $this->sort    = $column;
            $this->sortDir = 'desc';
        }
        $this->page = 1;
    }

    #[LiveAction]
    public function quickRange(#[LiveArg] string $range): void
    {
        $now = new \DateTimeImmutable();

        [$from, $to] = match ($range) {
            'last_hour'  => [$now->modify('-1 hour'),  $now],
            'last_24h'   => [$now->modify('-24 hours'), $now],
            'last_week'  => [$now->modify('-7 days'),  $now],
            'last_month' => [$now->modify('-30 days'), $now],
            default      => [null, null],
        };

        $this->dateFrom = $from?->format('Y-m-d\TH:i') ?? '';
        $this->dateTo   = $to?->format('Y-m-d\TH:i') ?? '';
        $this->page     = 1;
    }

    #[LiveAction]
    public function clearFilters(): void
    {
        $this->dateFrom   = '';
        $this->dateTo     = '';
        $this->userId     = '';
        $this->centreId   = '';
        $this->yearId     = '';
        $this->actionType = '';
        $this->page       = 1;
    }

    public function updatedCentreId(): void
    {
        $this->yearId = '';
        $this->page   = 1;
    }

    public function updatedUserId(): void     { $this->page = 1; }
    public function updatedActionType(): void { $this->page = 1; }
    public function updatedDateFrom(): void   { $this->page = 1; }
    public function updatedDateTo(): void     { $this->page = 1; }
}
