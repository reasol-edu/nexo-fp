<?php

declare(strict_types=1);

namespace App\Pagination;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

/**
 * Paginador ligero basado en Doctrine\ORM\Tools\Pagination\Paginator.
 *
 * @template T of object
 */
final class Paginator
{
    /** @var DoctrinePaginator<T> */
    private readonly DoctrinePaginator $paginator;
    private readonly int $totalItems;

    /**
     * @param Query<null, T> $query
     */
    public function __construct(
        Query $query,
        private readonly int $currentPage,
        private readonly int $pageSize,
    ) {
        $firstResult = max(0, ($currentPage - 1) * $pageSize);

        $query
            ->setFirstResult($firstResult)
            ->setMaxResults($pageSize);

        /** @var DoctrinePaginator<T> $paginator */
        $paginator = new DoctrinePaginator($query);
        $this->paginator  = $paginator;
        $this->totalItems = count($paginator);
    }

    /** @return DoctrinePaginator<T> */
    public function getItems(): DoctrinePaginator
    {
        return $this->paginator;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->totalItems / $this->pageSize));
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getFirstItemIndex(): int
    {
        return $this->totalItems === 0 ? 0 : ($this->currentPage - 1) * $this->pageSize + 1;
    }

    public function getLastItemIndex(): int
    {
        return min($this->currentPage * $this->pageSize, $this->totalItems);
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getTotalPages();
    }

    public function getPreviousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    public function getNextPage(): int
    {
        return min($this->getTotalPages(), $this->currentPage + 1);
    }

    /**
     * Devuelve la secuencia de páginas a mostrar en la barra de navegación.
     * Los valores null representan un separador "…".
     *
     * @return array<int, int|null>
     */
    public function getPageRange(): array
    {
        $total   = $this->getTotalPages();
        $current = $this->currentPage;

        if ($total <= 1) {
            return [1];
        }

        $delta = 2; // páginas a mostrar alrededor de la actual

        /** @var list<int> $inner */
        $inner = [];
        for ($i = max(2, $current - $delta); $i <= min($total - 1, $current + $delta); $i++) {
            $inner[] = $i;
        }

        $pages = [1];

        if ($inner !== [] && $inner[0] > 2) {
            $pages[] = null; // ellipsis inicial
        }

        foreach ($inner as $page) {
            $pages[] = $page;
        }

        $lastInner = count($inner) > 0 ? $inner[count($inner) - 1] : null;
        if ($lastInner !== null && $lastInner < $total - 1) {
            $pages[] = null; // ellipsis final
        }

        $pages[] = $total;

        return $pages;
    }
}







