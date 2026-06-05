<?php

declare(strict_types=1);

namespace App\Tests\Integration\Pagination;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Pagination\Paginator;
use App\Tests\Integration\RepositoryTestCase;

class PaginatorTest extends RepositoryTestCase
{
    /** Crea y persiste $n profesores con usernames teacher.1 … teacher.n. */
    private function seedTeachers(int $n): void
    {
        $teachers = [];
        for ($i = 1; $i <= $n; $i++) {
            $teachers[] = (new Teacher(new PersonName('Test', "Teacher{$i}")))->setUsername("teacher.{$i}");
        }
        $this->persist(...$teachers);
    }

    private function allTeachersQuery(): \Doctrine\ORM\Query
    {
        return $this->em->createQueryBuilder()
            ->select('t')
            ->from(Teacher::class, 't')
            ->orderBy('t.name.lastName', 'ASC')
            ->getQuery();
    }

    // ── totales ───────────────────────────────────────────────────────────────

    public function testGetTotalItemsReturnsCorrectCount(): void
    {
        $this->seedTeachers(7);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 3);

        self::assertSame(7, $paginator->getTotalItems());
    }

    public function testGetTotalPagesRoundsUp(): void
    {
        $this->seedTeachers(7);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 3);

        self::assertSame(3, $paginator->getTotalPages()); // ceil(7/3) = 3
    }

    public function testGetTotalPagesIsOneForEmptyResultSet(): void
    {
        $paginator = new Paginator($this->allTeachersQuery(), 1, 10);

        self::assertSame(1, $paginator->getTotalPages());
        self::assertSame(0, $paginator->getTotalItems());
    }

    // ── página actual ─────────────────────────────────────────────────────────

    public function testGetCurrentPageReturnsRequestedPage(): void
    {
        $this->seedTeachers(5);

        $paginator = new Paginator($this->allTeachersQuery(), 2, 2);

        self::assertSame(2, $paginator->getCurrentPage());
    }

    public function testGetPageSizeReturnsConfiguredSize(): void
    {
        $this->seedTeachers(5);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 4);

        self::assertSame(4, $paginator->getPageSize());
    }

    // ── índices de elementos ──────────────────────────────────────────────────

    public function testGetFirstItemIndexOnFirstPage(): void
    {
        $this->seedTeachers(10);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 3);

        self::assertSame(1, $paginator->getFirstItemIndex());
    }

    public function testGetFirstItemIndexOnSecondPage(): void
    {
        $this->seedTeachers(10);

        $paginator = new Paginator($this->allTeachersQuery(), 2, 3);

        self::assertSame(4, $paginator->getFirstItemIndex()); // (2-1)*3 + 1 = 4
    }

    public function testGetLastItemIndexOnLastPartialPage(): void
    {
        $this->seedTeachers(7);

        $paginator = new Paginator($this->allTeachersQuery(), 3, 3); // página 3 tiene 1 elemento

        self::assertSame(7, $paginator->getLastItemIndex());
    }

    public function testGetFirstItemIndexIsZeroForEmptyResultSet(): void
    {
        $paginator = new Paginator($this->allTeachersQuery(), 1, 5);

        self::assertSame(0, $paginator->getFirstItemIndex());
    }

    // ── navegación ────────────────────────────────────────────────────────────

    public function testHasPreviousPageReturnsFalseOnFirstPage(): void
    {
        $this->seedTeachers(5);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 2);

        self::assertFalse($paginator->hasPreviousPage());
    }

    public function testHasPreviousPageReturnsTrueOnSecondPage(): void
    {
        $this->seedTeachers(5);

        $paginator = new Paginator($this->allTeachersQuery(), 2, 2);

        self::assertTrue($paginator->hasPreviousPage());
    }

    public function testHasNextPageReturnsTrueWhenMorePagesExist(): void
    {
        $this->seedTeachers(5);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 2);

        self::assertTrue($paginator->hasNextPage());
    }

    public function testHasNextPageReturnsFalseOnLastPage(): void
    {
        $this->seedTeachers(4);

        $paginator = new Paginator($this->allTeachersQuery(), 2, 2);

        self::assertFalse($paginator->hasNextPage());
    }

    public function testGetPreviousPageDecrementsByOne(): void
    {
        $this->seedTeachers(6);

        $paginator = new Paginator($this->allTeachersQuery(), 3, 2);

        self::assertSame(2, $paginator->getPreviousPage());
    }

    public function testGetNextPageIncrementsByOne(): void
    {
        $this->seedTeachers(6);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 2);

        self::assertSame(2, $paginator->getNextPage());
    }

    // ── rango de páginas ──────────────────────────────────────────────────────

    public function testGetPageRangeReturnsSinglePageForOnePageResult(): void
    {
        $this->seedTeachers(3);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 10);

        self::assertSame([1], $paginator->getPageRange());
    }

    public function testGetPageRangeReturnsAllPagesWhenFewPages(): void
    {
        // 8 items / pageSize=2 → 4 páginas. Con delta=2 y página central (2),
        // el rango inner cubre páginas 2 y 3 sin ellipsis → [1, 2, 3, 4].
        $this->seedTeachers(8);

        $paginator = new Paginator($this->allTeachersQuery(), 2, 2); // página 2 de 4

        self::assertSame([1, 2, 3, 4], $paginator->getPageRange());
    }

    public function testGetPageRangeContainsNullEllipsisForManyPages(): void
    {
        $this->seedTeachers(20);

        $paginator = new Paginator($this->allTeachersQuery(), 1, 1); // 20 páginas

        $range = $paginator->getPageRange();

        // Rango comienza en 1 y termina en 20; debe haber al menos un null (ellipsis)
        self::assertSame(1, $range[0]);
        self::assertSame(20, end($range));
        self::assertContains(null, $range);
    }

    public function testGetPageRangeCurrentPageIsAlwaysIncluded(): void
    {
        $this->seedTeachers(20);

        $paginator = new Paginator($this->allTeachersQuery(), 10, 1); // página 10 de 20

        $range = $paginator->getPageRange();

        self::assertContains(10, $range);
    }

    // ── resultados paginados ──────────────────────────────────────────────────

    public function testGetItemsReturnsCorrectSliceForPage(): void
    {
        $this->seedTeachers(5);

        $paginator = new Paginator($this->allTeachersQuery(), 2, 2);

        // DoctrinePaginator::count() devuelve el total, no los items de la página;
        // hay que iterar para obtener los items efectivos.
        self::assertCount(2, iterator_to_array($paginator->getItems()));
    }

    public function testGetItemsReturnsPartialSliceOnLastPage(): void
    {
        $this->seedTeachers(5);

        $paginator = new Paginator($this->allTeachersQuery(), 3, 2); // página 3: 1 elemento

        self::assertCount(1, iterator_to_array($paginator->getItems()));
    }
}
