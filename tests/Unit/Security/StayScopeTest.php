<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Repository\ProgrammeYearRepository;
use App\Security\StayScope;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * La gestión es por nivel: quien coordina 2.º de una enseñanza no gestiona a los de 1.º aunque la
 * enseñanza participe en la estancia.
 */
#[AllowMockObjectsWithoutExpectations]
class StayScopeTest extends TestCase
{
    private ProgrammeYearRepository&MockObject $levels;
    private StayScope $scope;

    protected function setUp(): void
    {
        $this->levels = $this->createMock(ProgrammeYearRepository::class);
        $this->scope  = new StayScope($this->levels);
    }

    public function testGlobalAdminAndCentreAdminManageEverything(): void
    {
        $s = $this->scenario();
        $this->levels->expects($this->never())->method('findCoordinatedByInStay');

        $admin = $this->teacher(admin: true);
        self::assertNull($this->scope->manageableProgrammeYearIds($admin, $s['stay']));
        self::assertTrue($this->scope->canManageStudent($admin, $s['stay'], $s['dawFirst']));
        self::assertTrue($this->scope->canManageEveryLevel($admin, $s['stay']));

        $centreAdmin = $this->teacher();
        $s['stay']->getAcademicYear()->getEducationalCentre()->addAdmin($centreAdmin);
        self::assertNull($this->scope->manageableProgrammeYearIds($centreAdmin, $s['stay']));
        self::assertTrue($this->scope->canManageAny($centreAdmin, $s['stay']));
    }

    public function testCoordinatorOfAWholeProgrammeManagesBothOfItsLevels(): void
    {
        $s = $this->scenario();
        $this->levels->method('findCoordinatedByInStay')->willReturn([$s['dawL1'], $s['dawL2']]);

        $coordinator = $this->teacher();

        self::assertEqualsCanonicalizing(
            [$s['dawL1']->getId()->toRfc4122(), $s['dawL2']->getId()->toRfc4122()],
            $this->scope->manageableProgrammeYearIds($coordinator, $s['stay']),
        );
        self::assertTrue($this->scope->canManageProgramme($coordinator, $s['stay'], $s['daw']));
        self::assertTrue($this->scope->canManageStudent($coordinator, $s['stay'], $s['dawFirst']));
        self::assertTrue($this->scope->canManageStudent($coordinator, $s['stay'], $s['dawSecond']));
        self::assertFalse($this->scope->canManageStudent($coordinator, $s['stay'], $s['damSecond']), 'otra enseñanza');
        self::assertFalse($this->scope->canManageEveryLevel($coordinator, $s['stay']), 'no gestiona DAM');
    }

    public function testCoordinatorOfOnlySecondYearDoesNotManageFirstYearStudentsOfTheSameProgramme(): void
    {
        $s = $this->scenario();
        $this->levels->method('findCoordinatedByInStay')->willReturn([$s['dawL2']]);

        $coordinator = $this->teacher();

        self::assertTrue($this->scope->canManageAny($coordinator, $s['stay']));
        self::assertTrue($this->scope->canManageProgrammeYear($coordinator, $s['stay'], $s['dawL2']));
        self::assertFalse($this->scope->canManageProgrammeYear($coordinator, $s['stay'], $s['dawL1']));
        self::assertTrue($this->scope->canManageProgramme($coordinator, $s['stay'], $s['daw']), 'gestiona algún nivel de DAW');
        self::assertTrue($this->scope->canManageStudent($coordinator, $s['stay'], $s['dawSecond']));
        self::assertFalse($this->scope->canManageStudent($coordinator, $s['stay'], $s['dawFirst']), '1.º DAW no es suyo');
    }

    public function testAStudentInSeveralLevelsIsManagedThroughAnyOfThem(): void
    {
        $s = $this->scenario();
        $this->levels->method('findCoordinatedByInStay')->willReturn([$s['dawL1']]);
        $repeater = $this->student('Doble', 'Matricula');
        $repeater->addGroup($this->group($s['dawL1'], 'R1'));
        $repeater->addGroup($this->group($s['dawL2'], 'R2'));

        self::assertTrue($this->scope->canManageStudent($this->teacher(), $s['stay'], $repeater));
    }

    public function testFamilyHeadManagesTheLevelsOfTheirFamily(): void
    {
        $s = $this->scenario();
        $this->levels->method('findHeadedByInStay')->willReturn([$s['damL1'], $s['damL2']]);

        $head = $this->teacher();

        self::assertTrue($this->scope->canManageStudent($head, $s['stay'], $s['damSecond']));
        self::assertFalse($this->scope->canManageStudent($head, $s['stay'], $s['dawFirst']));
    }

    public function testCoordinatorAndHeadScopesAreCombinedWithoutDuplicates(): void
    {
        $s = $this->scenario();
        $this->levels->method('findCoordinatedByInStay')->willReturn([$s['dawL2']]);
        $this->levels->method('findHeadedByInStay')->willReturn([$s['damL1'], $s['dawL2']]);

        $ids = $this->scope->manageableProgrammeYearIds($this->teacher(), $s['stay']);

        self::assertEqualsCanonicalizing(
            [$s['dawL2']->getId()->toRfc4122(), $s['damL1']->getId()->toRfc4122()],
            $ids,
        );
    }

    public function testUnrelatedTeacherManagesNothing(): void
    {
        $s       = $this->scenario();
        $teacher = $this->teacher();

        self::assertSame([], $this->scope->manageableProgrammeYearIds($teacher, $s['stay']));
        self::assertFalse($this->scope->canManageAny($teacher, $s['stay']));
        self::assertFalse($this->scope->canManageProgramme($teacher, $s['stay'], $s['daw']));
        self::assertFalse($this->scope->canManageProgrammeYear($teacher, $s['stay'], $s['dawL1']));
        self::assertFalse($this->scope->canManageStudent($teacher, $s['stay'], $s['dawFirst']));
        self::assertFalse($this->scope->canManageEveryLevel($teacher, $s['stay']));
    }

    public function testStudentWithoutGroupInTheStayIsManagedByAnyoneWhoManagesALevel(): void
    {
        $s = $this->scenario();
        $this->levels->method('findCoordinatedByInStay')->willReturn([$s['dawL2']]);
        $orphan = $this->student('Sin', 'Grupo');

        self::assertTrue($this->scope->canManageStudent($this->teacher(), $s['stay'], $orphan));
    }

    public function testAStudentWhoseOnlyLevelIsNotInTheStayIsTreatedAsHavingNoLevel(): void
    {
        $s = $this->scenario();
        $this->levels->method('findCoordinatedByInStay')->willReturn([$s['dawL2']]);
        // 1.º DAM no participa en la estancia: no cuenta como nivel del estudiante
        $outside = $this->student('Fuera', 'Estancia');
        $outsideLevel = $this->withId((new ProgrammeYear())->setName('3.º DAM')->setProgramme($s['dam']));
        $outside->addGroup($this->group($outsideLevel, 'X'));

        self::assertTrue($this->scope->canManageStudent($this->teacher(), $s['stay'], $outside));
    }

    public function testEveryLevelCanBeDeletedOnlyByWhoManagesAllOfThem(): void
    {
        $s = $this->scenario();
        $this->levels->method('findCoordinatedByInStay')->willReturn([$s['dawL1'], $s['dawL2'], $s['damL1'], $s['damL2']]);

        self::assertTrue($this->scope->canManageEveryLevel($this->teacher(), $s['stay']));
    }

    public function testScopeIsCachedPerTeacherAndStay(): void
    {
        $s = $this->scenario();
        $this->levels->expects($this->once())->method('findCoordinatedByInStay')->willReturn([$s['dawL2']]);
        $teacher = $this->teacher();

        $this->scope->manageableProgrammeYearIds($teacher, $s['stay']);
        $this->scope->manageableProgrammeYearIds($teacher, $s['stay']);
        $this->scope->canManageAny($teacher, $s['stay']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Estancia con 1.º y 2.º de DAW y de DAM, y un estudiante en cada nivel.
     *
     * @return array{stay: Stay, daw: Programme, dam: Programme, dawL1: ProgrammeYear, dawL2: ProgrammeYear, damL1: ProgrammeYear, damL2: ProgrammeYear, dawFirst: Student, dawSecond: Student, damSecond: Student}
     */
    private function scenario(): array
    {
        $centre = $this->withId((new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla'));
        $year   = $this->withId((new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre));
        $family = $this->withId((new ProfessionalFamily())->setName('Informática')->setAcademicYear($year));

        $daw = $this->withId((new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($family));
        $dam = $this->withId((new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family));

        $dawL1 = $this->withId((new ProgrammeYear())->setName('1.º DAW')->setProgramme($daw));
        $dawL2 = $this->withId((new ProgrammeYear())->setName('2.º DAW')->setProgramme($daw));
        $damL1 = $this->withId((new ProgrammeYear())->setName('1.º DAM')->setProgramme($dam));
        $damL2 = $this->withId((new ProgrammeYear())->setName('2.º DAM')->setProgramme($dam));

        $dawFirst  = $this->student('Ana', 'DawPrimero');
        $dawSecond = $this->student('Eva', 'DawSegundo');
        $damSecond = $this->student('Luis', 'DamSegundo');
        $dawFirst->addGroup($this->group($dawL1, 'DAW1A'));
        $dawSecond->addGroup($this->group($dawL2, 'DAW2A'));
        $damSecond->addGroup($this->group($damL2, 'DAM2A'));

        $stay = $this->withId(new Stay());
        $stay->setAcademicYear($year)->addProgramme($daw)->addProgramme($dam);

        return compact('stay', 'daw', 'dam', 'dawL1', 'dawL2', 'damL1', 'damL2', 'dawFirst', 'dawSecond', 'damSecond');
    }

    private function group(ProgrammeYear $level, string $name): Group
    {
        return $this->withId((new Group())->setName($name)->setProgrammeYear($level));
    }

    private function teacher(bool $admin = false): Teacher
    {
        return $this->withId((new Teacher(new PersonName('Ana', 'García')))->setUsername('t' . random_int(1, 99999))->setAdmin($admin));
    }

    private function student(string $first, string $last): Student
    {
        return $this->withId((new Student(new PersonName($first, $last)))->setStudentId('S-' . $last));
    }

    private function withId(object $entity): object
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, Uuid::v4());

        return $entity;
    }
}
