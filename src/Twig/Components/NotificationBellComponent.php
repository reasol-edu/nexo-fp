<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Stay;
use App\Entity\Teacher;
use App\Service\PendingTasksProvider;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class NotificationBellComponent extends AbstractController
{
    use DefaultActionTrait;

    public const MAX_ITEMS = 8;

    /** @var list<array{type: string, stay: Stay, count: int}>|null */
    private ?array $itemsCache = null;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PendingTasksProvider $pendingTasksProvider,
    ) {}

    /** @return list<array{type: string, stay: Stay, count: int}> */
    public function getItems(): array
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        $year = $this->tenantContext->getSelectedCentre()?->getActiveAcademicYear();
        $user = $this->getUser();

        if ($year === null || !$user instanceof Teacher) {
            return $this->itemsCache = [];
        }

        return $this->itemsCache = $this->pendingTasksProvider->findPendingForTeacher($year, $user);
    }

    public function getTotal(): int
    {
        return \count($this->getItems());
    }

    /** @return list<array{type: string, stay: Stay, count: int}> */
    public function getVisibleItems(): array
    {
        return \array_slice($this->getItems(), 0, self::MAX_ITEMS);
    }
}
