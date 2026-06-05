<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\PersonName;
use App\Entity\Worker;
use App\Repository\WorkerRepository;
use App\Tests\Integration\RepositoryTestCase;

class WorkerRepositoryTest extends RepositoryTestCase
{
    private WorkerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var WorkerRepository $repo */
        $repo       = self::getContainer()->get(WorkerRepository::class);
        $this->repo = $repo;
    }

    public function testFindByNationalIdNumberReturnsWorker(): void
    {
        $worker = $this->makeWorker('12345678A');
        $this->persist($worker);

        $result = $this->repo->findByNationalIdNumber('12345678A');

        self::assertNotNull($result);
        self::assertSame('12345678A', $result->getNationalIdNumber());
    }

    public function testFindByNationalIdNumberReturnsNullForUnknown(): void
    {
        $worker = $this->makeWorker('12345678A');
        $this->persist($worker);

        self::assertNull($this->repo->findByNationalIdNumber('99999999Z'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeWorker(string $nationalId): Worker
    {
        return (new Worker(new PersonName('Ana', 'Garcia')))
            ->setNationalIdNumber($nationalId);
    }
}
