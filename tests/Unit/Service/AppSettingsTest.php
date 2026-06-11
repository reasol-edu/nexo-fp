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

    // ── getForTeacher: cascade teacher → global → default (no centre) ────────

    public function testGetForTeacherUsesTeacherValueFirst(): void
    {
        $def     = $this->makeDef('email.notifications', SettingType::Boolean, 'true');
        $teacher = $this->createStub(Teacher::class);

        $teacherRepo = $this->createStub(TeacherSettingValueRepository::class);
        $teacherRepo->method('findByTeacherIndexedByKey')
            ->willReturn(['email.notifications' => $this->makeTeacherValue('false')]);

        $service = $this->makeService(
            defs:    ['email.notifications' => $def],
            globals: ['email.notifications' => $this->makeGlobalValue('true')],
        );

        // Replace teacherRepo with one that returns a specific teacher value
        $service = $this->makeServiceWithTeacherRepo(
            defs:        ['email.notifications' => $def],
            globals:     ['email.notifications' => $this->makeGlobalValue('true')],
            teacherRepo: $teacherRepo,
        );

        self::assertFalse($service->getForTeacher('email.notifications', $teacher));
    }

    public function testGetForTeacherFallsBackToGlobalValue(): void
    {
        $def     = $this->makeDef('email.notifications', SettingType::Boolean, 'true');
        $teacher = $this->createStub(Teacher::class);

        $teacherRepo = $this->createStub(TeacherSettingValueRepository::class);
        $teacherRepo->method('findByTeacherIndexedByKey')->willReturn([]);

        $service = $this->makeServiceWithTeacherRepo(
            defs:        ['email.notifications' => $def],
            globals:     ['email.notifications' => $this->makeGlobalValue('false')],
            teacherRepo: $teacherRepo,
        );

        self::assertFalse($service->getForTeacher('email.notifications', $teacher));
    }

    public function testGetForTeacherFallsBackToDefault(): void
    {
        $def     = $this->makeDef('page.size', SettingType::Integer, '25');
        $teacher = $this->createStub(Teacher::class);

        $teacherRepo = $this->createStub(TeacherSettingValueRepository::class);
        $teacherRepo->method('findByTeacherIndexedByKey')->willReturn([]);

        $service = $this->makeServiceWithTeacherRepo(
            defs:        ['page.size' => $def],
            globals:     [],
            teacherRepo: $teacherRepo,
        );

        self::assertSame(25, $service->getForTeacher('page.size', $teacher));
    }

    public function testGetForTeacherIgnoresCentreValue(): void
    {
        $def     = $this->makeDef('email.notifications', SettingType::Boolean, 'true');
        $teacher = $this->createStub(Teacher::class);

        $teacherRepo = $this->createStub(TeacherSettingValueRepository::class);
        $teacherRepo->method('findByTeacherIndexedByKey')->willReturn([]);

        // Centre value present but should be ignored
        $service = $this->makeServiceWithTeacherRepo(
            defs:        ['email.notifications' => $def],
            globals:     [],
            teacherRepo: $teacherRepo,
            centres:     ['email.notifications' => $this->makeCentreValue('false')],
        );

        // Default is 'true'; centre value ('false') must be ignored
        self::assertTrue($service->getForTeacher('email.notifications', $teacher));
    }

    public function testGetForTeacherReturnsNullForUnknownKey(): void
    {
        $teacher = $this->createStub(Teacher::class);

        $teacherRepo = $this->createStub(TeacherSettingValueRepository::class);
        $teacherRepo->method('findByTeacherIndexedByKey')->willReturn([]);

        $service = $this->makeServiceWithTeacherRepo(defs: [], globals: [], teacherRepo: $teacherRepo);

        self::assertNull($service->getForTeacher('nonexistent.key', $teacher));
    }

    public function testSharedBaseCacheReducesQueries(): void
    {
        $def     = $this->makeDef('page.size', SettingType::Integer, '20');
        $teacher = $this->createStub(Teacher::class);

        $defs = $this->createMock(SettingDefinitionRepository::class);
        $defs->expects(self::once())->method('findAllIndexedByKey')->willReturn(['page.size' => $def]);

        $globals = $this->createMock(GlobalSettingValueRepository::class);
        $globals->expects(self::once())->method('findAllIndexedByKey')->willReturn([]);

        $teacherRepo = $this->createStub(TeacherSettingValueRepository::class);
        $teacherRepo->method('findByTeacherIndexedByKey')->willReturn([]);

        $tenant   = $this->createStub(TenantContextInterface::class);
        $tenant->method('getSelectedCentre')->willReturn(null);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $centreRepo = $this->createStub(CentreSettingValueRepository::class);

        $service = new AppSettings($defs, $globals, $centreRepo, $teacherRepo, $tenant, $security);

        // get() triggers ensureBaseLoaded(); getForTeacher() must reuse the same cache
        $service->get('page.size');
        $service->getForTeacher('page.size', $teacher);
    }

    // ── Lock: global locked overrides all ─────────────────────────────────────

    public function testGetRespectsGlobalLock(): void
    {
        $def = $this->makeDef('email.notifications', SettingType::Boolean, 'true');

        $service = $this->makeService(
            defs:    ['email.notifications' => $def],
            globals: ['email.notifications' => $this->makeGlobalValue('false', locked: true)],
            centres: ['email.notifications' => $this->makeCentreValue('true')],
            teachers: ['email.notifications' => $this->makeTeacherValue('true')],
        );

        // Global lock must override both centre and teacher values
        self::assertFalse($service->get('email.notifications'));
    }

    public function testGetRespectsCentreLock(): void
    {
        $def = $this->makeDef('email.notifications', SettingType::Boolean, 'true');

        $service = $this->makeService(
            defs:    ['email.notifications' => $def],
            globals: ['email.notifications' => $this->makeGlobalValue('true')],
            centres: ['email.notifications' => $this->makeCentreValue('false', locked: true)],
            teachers: ['email.notifications' => $this->makeTeacherValue('true')],
        );

        // Centre lock must override teacher value (global is not locked)
        self::assertFalse($service->get('email.notifications'));
    }

    public function testGetForTeacherRespectsGlobalLock(): void
    {
        $def     = $this->makeDef('email.notifications', SettingType::Boolean, 'true');
        $teacher = $this->createStub(Teacher::class);

        $teacherRepo = $this->createStub(TeacherSettingValueRepository::class);
        $teacherRepo->method('findByTeacherIndexedByKey')
            ->willReturn(['email.notifications' => $this->makeTeacherValue('true')]);

        $service = $this->makeServiceWithTeacherRepo(
            defs:        ['email.notifications' => $def],
            globals:     ['email.notifications' => $this->makeGlobalValue('false', locked: true)],
            teacherRepo: $teacherRepo,
        );

        // Global lock must override teacher value in getForTeacher()
        self::assertFalse($service->getForTeacher('email.notifications', $teacher));
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

    /**
     * @param array<string, SettingDefinition>   $defs
     * @param array<string, GlobalSettingValue>  $globals
     * @param array<string, CentreSettingValue>  $centres
     */
    private function makeServiceWithTeacherRepo(
        array $defs,
        array $globals,
        TeacherSettingValueRepository $teacherRepo,
        array $centres = [],
    ): AppSettings {
        $defsRepo   = $this->createStub(SettingDefinitionRepository::class);
        $defsRepo->method('findAllIndexedByKey')->willReturn($defs);

        $globalRepo = $this->createStub(GlobalSettingValueRepository::class);
        $globalRepo->method('findAllIndexedByKey')->willReturn($globals);

        $centreRepo = $this->createStub(CentreSettingValueRepository::class);
        $tenant     = $this->createStub(TenantContextInterface::class);

        if ($centres !== []) {
            $centre = $this->createStub(EducationalCentre::class);
            $tenant->method('getSelectedCentre')->willReturn($centre);
            $centreRepo->method('findByCentreIndexedByKey')->willReturn($centres);
        } else {
            $tenant->method('getSelectedCentre')->willReturn(null);
        }

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        return new AppSettings($defsRepo, $globalRepo, $centreRepo, $teacherRepo, $tenant, $security);
    }

    private function makeDef(string $key, SettingType $type, string $default): SettingDefinition
    {
        return (new SettingDefinition())
            ->setKey($key)
            ->setType($type)
            ->setDefaultValue($default);
    }

    private function makeGlobalValue(string $value, bool $locked = false): GlobalSettingValue
    {
        return (new GlobalSettingValue())->setValue($value)->setLocked($locked);
    }

    private function makeCentreValue(string $value, bool $locked = false): CentreSettingValue
    {
        return (new CentreSettingValue())->setValue($value)->setLocked($locked);
    }

    private function makeTeacherValue(string $value): TeacherSettingValue
    {
        return (new TeacherSettingValue())->setValue($value);
    }
}
