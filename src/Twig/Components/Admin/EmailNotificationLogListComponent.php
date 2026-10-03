<?php

declare(strict_types=1);

namespace App\Twig\Components\Admin;

use App\Entity\EducationalCentre;
use App\Entity\EmailNotificationLog;
use App\Pagination\Paginator;
use App\Repository\EmailNotificationLogRepository;
use App\Security\Voter\EducationalCentreVoter;
use App\Service\AppSettings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Listado filtrable del registro de correos. Con un centro muestra solo los suyos (administración
 * del centro); sin centro muestra todos, incluidos los que no pertenecen a ningún centro
 * (administración global).
 */
#[AsLiveComponent]
class EmailNotificationLogListComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public ?EducationalCentre $centre = null;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public string $eventKey = '';

    #[LiveProp(writable: true)]
    public string $status = '';

    #[LiveProp(writable: true)]
    public string $dateFrom = '';

    #[LiveProp(writable: true)]
    public string $dateTo = '';

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly EmailNotificationLogRepository $logs,
        private readonly AppSettings $appSettings,
        private readonly ClockInterface $clock,
    ) {}

    public function mount(?EducationalCentre $centre = null): void
    {
        if ($centre === null) {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        } else {
            $this->denyAccessUnlessGranted(EducationalCentreVoter::SECTION, $centre);
        }

        $this->centre = $centre;
    }

    /** @return Paginator<EmailNotificationLog> */
    public function getPagination(): Paginator
    {
        return new Paginator(
            $this->logs->createFilteredQuery($this->centre, [
                'search'   => trim($this->search),
                'eventKey' => $this->eventKey,
                'status'   => $this->status,
                'dateFrom' => $this->dateFrom,
                'dateTo'   => $this->dateTo,
            ]),
            max(1, $this->page),
            (int) $this->appSettings->get('page.size'),
        );
    }

    /** @return list<string> */
    public function getDistinctEventKeys(): array
    {
        return $this->logs->findDistinctEventKeys($this->centre);
    }

    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->eventKey !== '' || $this->status !== ''
            || $this->dateFrom !== '' || $this->dateTo !== '';
    }

    #[LiveAction]
    public function setPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }

    #[LiveAction]
    public function quickRange(#[LiveArg] string $range): void
    {
        $now = $this->clock->now();

        [$from, $to] = match ($range) {
            'last_24h'   => [$now->modify('-24 hours'), $now],
            'last_week'  => [$now->modify('-7 days'), $now],
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
        $this->search   = '';
        $this->eventKey = '';
        $this->status   = '';
        $this->dateFrom = '';
        $this->dateTo   = '';
        $this->page     = 1;
    }

    public function updatedSearch(): void   { $this->page = 1; }
    public function updatedEventKey(): void { $this->page = 1; }
    public function updatedStatus(): void   { $this->page = 1; }
    public function updatedDateFrom(): void { $this->page = 1; }
    public function updatedDateTo(): void   { $this->page = 1; }
}
