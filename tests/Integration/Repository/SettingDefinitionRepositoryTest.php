<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\SettingDefinition;
use App\Entity\SettingType;
use App\Repository\SettingDefinitionRepository;
use App\Tests\Integration\RepositoryTestCase;

class SettingDefinitionRepositoryTest extends RepositoryTestCase
{
    private SettingDefinitionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SettingDefinitionRepository $repo */
        $repo       = self::getContainer()->get(SettingDefinitionRepository::class);
        $this->repo = $repo;
    }

    // ── findAllIndexedByKey ───────────────────────────────────────────────────

    public function testFindAllIndexedByKeyReturnsEmptyArrayWhenNoDefinitions(): void
    {
        self::assertSame([], $this->repo->findAllIndexedByKey());
    }

    public function testFindAllIndexedByKeyIsKeyedBySettingKey(): void
    {
        $def = $this->makeDefinition('page.size', SettingType::Integer, '20');
        $this->persist($def);

        $result = $this->repo->findAllIndexedByKey();

        self::assertArrayHasKey('page.size', $result);
        self::assertSame('20', $result['page.size']->getDefaultValue());
    }

    public function testFindAllIndexedByKeyReturnsMultipleDefinitions(): void
    {
        $this->persist(
            $this->makeDefinition('page.size', SettingType::Integer, '20'),
            $this->makeDefinition('email.notifications', SettingType::Boolean, 'true'),
        );

        $result = $this->repo->findAllIndexedByKey();

        self::assertCount(2, $result);
        self::assertArrayHasKey('page.size', $result);
        self::assertArrayHasKey('email.notifications', $result);
    }

    // ── findByScope ───────────────────────────────────────────────────────────

    public function testFindByScopeGlobalReturnsOnlyGlobalDefs(): void
    {
        $this->persist(
            $this->makeDefinition('email.notifications', SettingType::Boolean, 'true', global: true),
            $this->makeDefinition('page.size', SettingType::Integer, '20', teacher: true),
        );

        $result = $this->repo->findByScope('global');

        self::assertCount(1, $result);
        self::assertSame('email.notifications', $result[0]->getKey());
    }

    public function testFindByScopeCentreReturnsOnlyCentreDefs(): void
    {
        $this->persist(
            $this->makeDefinition('email.notifications', SettingType::Boolean, 'true', centre: true),
            $this->makeDefinition('page.size', SettingType::Integer, '20', teacher: true),
        );

        $result = $this->repo->findByScope('centre');

        self::assertCount(1, $result);
        self::assertSame('email.notifications', $result[0]->getKey());
    }

    public function testFindByScopeTeacherReturnsOnlyTeacherDefs(): void
    {
        $this->persist(
            $this->makeDefinition('email.notifications', SettingType::Boolean, 'true', global: true, centre: true, teacher: true),
            $this->makeDefinition('page.size', SettingType::Integer, '20', teacher: true),
        );

        $result = $this->repo->findByScope('teacher');

        self::assertCount(2, $result);
    }

    public function testFindByScopeThrowsOnUnknownScope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->findByScope('unknown');
    }

    public function testFindByScopeReturnsOrderedByKey(): void
    {
        $this->persist(
            $this->makeDefinition('z.setting', SettingType::Boolean, 'true', global: true),
            $this->makeDefinition('a.setting', SettingType::Boolean, 'false', global: true),
        );

        $result = $this->repo->findByScope('global');

        self::assertSame('a.setting', $result[0]->getKey());
        self::assertSame('z.setting', $result[1]->getKey());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeDefinition(
        string $key,
        SettingType $type,
        string $default,
        bool $global  = false,
        bool $centre  = false,
        bool $teacher = false,
    ): SettingDefinition {
        return (new SettingDefinition())
            ->setKey($key)
            ->setType($type)
            ->setDefaultValue($default)
            ->setGlobalScope($global)
            ->setCentreScope($centre)
            ->setTeacherScope($teacher);
    }
}
