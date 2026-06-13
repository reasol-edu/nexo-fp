<?php

namespace App\Twig\Components;

use App\Entity\Stay;
use App\Entity\Teacher;
use App\Repository\StayRepository;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class StayCalendarComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public int $year = 0;

    #[LiveProp(writable: true)]
    public int $month = 0;

    /** @var list<array<string, mixed>>|null */
    private ?array $weeksCache = null;

    private const FAMILY_COLORS = [
        'bg-plum-100 text-plum-700',
        'bg-sky-100 text-sky-700',
        'bg-emerald-100 text-emerald-700',
        'bg-amber-100 text-amber-700',
        'bg-rose-100 text-rose-700',
        'bg-indigo-100 text-indigo-700',
        'bg-teal-100 text-teal-700',
        'bg-orange-100 text-orange-700',
    ];

    public function __construct(
        private readonly StayRepository $stayRepository,
        private readonly TenantContext $tenantContext,
        private readonly TranslatorInterface $translator,
    ) {}

    public function mount(): void
    {
        $today = new \DateTimeImmutable();
        if ($this->year < 2000 || $this->year > 2100) {
            $this->year = (int) $today->format('Y');
        }
        if ($this->month < 1 || $this->month > 12) {
            $this->month = (int) $today->format('n');
        }
    }

    #[LiveAction]
    public function previousMonth(): void
    {
        $d = (new \DateTimeImmutable())->setDate($this->year, $this->month, 1)->modify('-1 month');
        $this->year  = (int) $d->format('Y');
        $this->month = (int) $d->format('n');
    }

    #[LiveAction]
    public function nextMonth(): void
    {
        $d = (new \DateTimeImmutable())->setDate($this->year, $this->month, 1)->modify('+1 month');
        $this->year  = (int) $d->format('Y');
        $this->month = (int) $d->format('n');
    }

    #[LiveAction]
    public function goToday(): void
    {
        $today       = new \DateTimeImmutable();
        $this->year  = (int) $today->format('Y');
        $this->month = (int) $today->format('n');
    }

    public function getMonthLabel(): string
    {
        return $this->translator->trans('month.' . $this->month, [], 'calendar') . ' ' . $this->year;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getWeeks(): array
    {
        if ($this->weeksCache === null) {
            $this->weeksCache = $this->computeWeeks();
        }

        return $this->weeksCache;
    }

    public function isToday(\DateTimeImmutable $day): bool
    {
        return $day->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');
    }

    public function isCurrentMonth(\DateTimeImmutable $day): bool
    {
        return (int) $day->format('n') === $this->month
            && (int) $day->format('Y') === $this->year;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function computeWeeks(): array
    {
        $centre = $this->tenantContext->getSelectedCentre();
        $year   = $centre?->getActiveAcademicYear();
        if ($centre === null || $year === null) {
            return [];
        }

        $user   = $this->getUser();
        $viewer = $user instanceof Teacher ? $user : null;

        $firstDay  = (new \DateTimeImmutable())->setDate($this->year, $this->month, 1)->setTime(0, 0, 0);
        $lastDay   = $firstDay->modify('last day of this month');
        $startDow  = (int) $firstDay->format('N'); // 1=Mon
        $gridStart = $firstDay->modify('-' . ($startDow - 1) . ' days');
        $endDow    = (int) $lastDay->format('N');
        $gridEnd   = $lastDay->modify('+' . (7 - $endDow) . ' days');

        $stays = $this->stayRepository->findOverlappingPeriod($year, $gridStart, $gridEnd, $viewer);

        $stayMeta = [];
        foreach ($stays as $stay) {
            $family   = $stay->getProgramme()->getProfessionalFamily()->getName();
            $colorIdx = abs(crc32($family)) % count(self::FAMILY_COLORS);
            $unsigned = 0;
            foreach ($stay->getTrainingPositions() as $tp) {
                if (!$tp->isSigned()) {
                    ++$unsigned;
                }
            }
            $stayMeta[$stay->getId()->toRfc4122()] = [
                'colorClass'   => self::FAMILY_COLORS[$colorIdx],
                'unsignedCount' => $unsigned,
            ];
        }

        $weeks  = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $weekStart = $cursor;
            $weekEnd   = $cursor->modify('+6 days');
            $segments  = $this->buildSegments($stays, $weekStart, $weekEnd, $stayMeta);

            $days = [];
            $d    = $weekStart;
            for ($i = 0; $i < 7; $i++) {
                $days[] = $d;
                $d      = $d->modify('+1 day');
            }

            $maxLane = -1;
            foreach ($segments as $seg) {
                if ($seg['lane'] > $maxLane) {
                    $maxLane = $seg['lane'];
                }
            }

            $weeks[] = ['days' => $days, 'segments' => $segments, 'maxLane' => $maxLane];
            $cursor  = $cursor->modify('+7 days');
        }

        return $weeks;
    }

    /**
     * @param  list<Stay>                 $stays
     * @param  array<string, mixed>       $stayMeta
     * @return list<array<string, mixed>>
     */
    private function buildSegments(
        array $stays,
        \DateTimeImmutable $weekStart,
        \DateTimeImmutable $weekEnd,
        array $stayMeta,
    ): array {
        $segments = [];
        foreach ($stays as $stay) {
            $stayStart = $stay->getStartDate();
            $stayEnd   = $stay->getEndDate();

            if ($stayEnd < $weekStart || $stayStart > $weekEnd) {
                continue;
            }

            $segStart = $stayStart > $weekStart ? $stayStart : $weekStart;
            $segEnd   = $stayEnd   < $weekEnd   ? $stayEnd   : $weekEnd;
            $colStart = (int) $segStart->format('N');
            $colSpan  = (int) $segEnd->format('N') - $colStart + 1;
            $meta     = $stayMeta[$stay->getId()->toRfc4122()];

            $segments[] = [
                'stay'          => $stay,
                'colStart'      => $colStart,
                'colSpan'       => $colSpan,
                'isStart'       => $segStart == $stayStart,
                'isEnd'         => $segEnd   == $stayEnd,
                'colorClass'    => $meta['colorClass'],
                'unsignedCount' => ($segEnd == $stayEnd) ? $meta['unsignedCount'] : 0,
                'lane'          => 0,
            ];
        }

        usort($segments, static fn (array $a, array $b): int =>
            $a['colStart'] <=> $b['colStart'] ?: $b['colSpan'] <=> $a['colSpan']
        );

        $occupied = [];
        foreach ($segments as &$seg) {
            $lane = 0;
            while (true) {
                $fits = true;
                for ($col = $seg['colStart']; $col < $seg['colStart'] + $seg['colSpan']; $col++) {
                    if (in_array($col, $occupied[$lane] ?? [], true)) {
                        $fits = false;
                        break;
                    }
                }
                if ($fits) {
                    break;
                }
                $lane++;
            }
            $seg['lane'] = $lane;
            for ($col = $seg['colStart']; $col < $seg['colStart'] + $seg['colSpan']; $col++) {
                $occupied[$lane][] = $col;
            }
        }
        unset($seg);

        return $segments;
    }
}
