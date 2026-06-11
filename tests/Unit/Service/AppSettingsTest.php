<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\CentreSettingValue;
use App\Entity\EducationalCentre;
use App\Entity\GlobalSettingValue;
use App\Entity\SettingDefinition;
use App\Entity\SettingType;
use App\Entity\Teacher;
use App\Entity\TeacherSettingValue;
use App\Repository\CentreSettingValueRepository;
use App\Repository\GlobalSettingValueRepository;
use App\Repository\SettingDefinitionRepository;
use App\Repository\TeacherSettingValueRepository;
use App\Service\AppSettings;
use App\Service\TenantContextInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class AppSettingsTest extends TestCase
{
    // ── Priority: teacher > centre > global > default ─────────────────────────

    public function testTeacherValueTakesPriorityOverAll(): void
    {
        $def = $this->makeDef('page.size', SettingType::Integer, '20');

        $service = $this->makeService(
            defs:    ['page.size' => $def],
            globals: ['page.size' => $this->makeGlobalValue('50')],
            centres: ['page.size' => $this->makeCentreValue('30')],
            teachers: ['page.size' => $this->makeTeacherValue('10')],
        );

        self::assertSame(10, $service->get('page.size'));
    }

    public function testCentreValueTakesPriorityOverGlobalAndDefault(): void
    {
        $def = $this->makeDef('page.size', SettingType::Integer, '20');

        $service = $this->makeService(
            defs:    ['page.size' => $def],
            globals: ['page.size' => $this->makeGlobalValue('50')],
            centres: ['page.size' => $this->makeCentreValue('30')],
        );

        self::assertSame(30, $service->get('page.size'));
    }

    public function testGlobalValueTakesPriorityOverDefault(): void
    {
        $def = $this->makeDef('page.size', SettingType::Integer, '20');

        $service = $this->makeService(
            defs:    ['page.size' => $def],
            globals: ['page.size' => $this->makeGlobalValue('50')],
        );

        self::assertSame(50, $service->get('page.size'));
    }

    public function testFallsBackToDefaultWhenNoValueStored(): void
    {
        $def = $this->makeDef('page.size', SettingType::Integer, '20');

        $service = $this->makeService(defs: ['page.size' => $def]);

        self::assertSame(20, $service->get('page.size'));
    }

    // ── Type casting ──────────────────────────────────────────────────────────

    public function testBooleanTypeCastingTrue(): void
    {
        $def = $this->makeDef('email.notifications', SettingType::Boolean, 'true');

        $service = $this->makeService(defs: ['email.notifications' => $def]);

        self::assertTrue($service->get('email.notifications'));
    }

    public function testBooleanTypeCastingFalse(): void
    {
        $def = $this->makeDef('email.notifications', SettingType::Boolean, 'true');

        $service = $this->makeService(
            defs:    ['email.notifications' => $def],
            globals: ['email.notifications' => $this->makeGlobalValue('false')],
        );

        self::assertFalse($service->get('email.notifications'));
    }

    public function testIntegerTypeCasting(): void
    {
        $def = $this->makeDef('page.size', SettingType::Integer, '20');

        $service = $this->makeService(defs: ['page.size' => $def]);

        self::assertIsInt($service->get('page.size'));
        self::assertSame(20, $service->get('page.size'));
    }

    public function testStringTypeCasting(): void
    {
        $def = $this->makeDef('some.string', SettingType::String, 'hello');

        $service = $this->makeService(defs: ['some.string' => $def]);

        self::assertIsString($service->get('some.string'));
        self::assertSame('hello', $service->get('some.string'));
    }

    // ── Single load ───────────────────────────────────────────────────────────

    public function testRepositoriesAreCalledOnlyOnce(): void
    {
        $def  = $this->makeDef('page.size', SettingType::Integer, '20');
        $defs = $this->createMock(SettingDefinitionRepository::class);
        $defs->expects(self::once())->method('findAllIndexedByKey')->willReturn(['page.size' => $def]);

        $globals  = $this->createMock(GlobalSettingValueRepository::class);
        $globals->expects(self::once())->method('findAllIndexedByKey')->willReturn([]);

        $centres  = $this->createMock(CentreSettingValueRepository::class);
        $centres->expects(self::never())->method('findByCentreIndexedByKey');

        $teachers = $this->createMock(TeacherSettingValueRepository::class);
        $teachers->expects(self::never())->method('findByTeacherIndexedByKey');

        $tenant   = $this->createStub(TenantContextInterface::class);
        $tenant->method('getSelectedCentre')->willReturn(null);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $service = new AppSettings($defs, $globals, $centres, $teachers, $tenant, $security);

        $service->get('page.size');
        $service->get('page.size');
        $service->get('page.size');
    }

    public function testInvalidateForcesCacheReload(): void
    {
        $def = $this->makeDef('page.size', SettingType::Integer, '20');

        $defs = $this->createMock(SettingDefinitionRepository::class);
        $defs->expects(self::exactly(2))->method('findAllIndexedByKey')->willReturn(['page.size' => $def]);

        $globals  = $this->createStub(GlobalSettingValueRepository::class);
        $globals->method('findAllIndexedByKey')->willReturn([]);

        $centres  = $this->createStub(CentreSettingValueRepository::class);
        $teachers = $this->createStub(TeacherSettingValueRepository::class);

        $tenant   = $this->createStub(TenantContextInterface::class);
        $tenant->method('getSelectedCentre')->willReturn(null);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $service = new AppSettings($defs, $globals, $centres, $teachers, $tenant, $security);
        $service->get('page.size');
        $service->invalidate();
        $service->get('page.size');
    }

    // ── Unknown key ───────────────────────────────────────────────────────────

    public function testGetReturnsNullForUnknownKey(): void
    {
        $service = $this->makeService(defs: []);

        self::assertNull($service->get('nonexistent.key'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param array<string, SettingDefinition>   $defs
     * @param array<string, GlobalSettingValue>  $globals
     * @param array<string, CentreSettingValue>  $centres
     * @param array<string, TeacherSettingValue> $teachers
     */
    private function makeService(
        array $defs     = [],
        array $globals  = [],
        array $centres  = [],
        array $teachers = [],
        bool  $hasCentre  = false,
        bool  $hasTeacher = false,
    ): AppSettings {
        $defsRepo    = $this->createStub(SettingDefinitionRepository::class);
        $defsRepo->method('findAllIndexedByKey')->willReturn($defs);

        $globalRepo = $this->createStub(GlobalSettingValueRepository::class);
        $globalRepo->method('findAllIndexedByKey')->willReturn($globals);

        $centreRepo  = $this->createStub(CentreSettingValueRepository::class);
        $teacherRepo = $this->createStub(TeacherSettingValueRepository::class);

        $tenant   = $this->createStub(TenantContextInterface::class);
        $security = $this->createStub(Security::class);

        if ($centres !== [] || $hasCentre) {
            $centre = $this->createStub(EducationalCentre::class);
            $tenant->method('getSelectedCentre')->willReturn($centre);
            $centreRepo->method('findByCentreIndexedByKey')->willReturn($centres);
        } else {
            $tenant->method('getSelectedCentre')->willReturn(null);
        }

        if ($teachers !== [] || $hasTeacher) {
            $teacher = $this->createStub(Teacher::class);
            $security->method('getUser')->willReturn($teacher);
            $teacherRepo->method('findByTeacherIndexedByKey')->willReturn($teachers);
        } else {
            $security->method('getUser')->willReturn(null);
        }

        return new AppSettings($defsRepo, $globalRepo, $centreRepo, $teacherRepo, $tenant, $security);
    }

    private function makeDef(string $key, SettingType $type, string $default): SettingDefinition
    {
        return (new SettingDefinition())
            ->setKey($key)
            ->setType($type)
            ->setDefaultValue($default);
    }

    private function makeGlobalValue(string $value): GlobalSettingValue
    {
        return (new GlobalSettingValue())->setValue($value);
    }

    private function makeCentreValue(string $value): CentreSettingValue
    {
        return (new CentreSettingValue())->setValue($value);
    }

    private function makeTeacherValue(string $value): TeacherSettingValue
    {
        return (new TeacherSettingValue())->setValue($value);
    }
}
