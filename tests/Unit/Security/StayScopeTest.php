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
use App\Repository\ProgrammeRepository;
use App\Security\StayScope;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[AllowMockObjectsWithoutExpectations]
class StayScopeTest extends TestCase
{
    private ProgrammeRepository&MockObject $programmes;
    private StayScope $scope;

    protected function setUp(): void
    {
        $this->programmes = $this->createMock(ProgrammeRepository::class);
        $this->scope      = new StayScope($this->programmes);
    }

    public function testGlobalAdminAndCentreAdminManageEverything(): void
    {
        [$stay, , , $dawStudent] = $this->scenario();
        $this->programmes->expects($this->never())->method('findCoordinatedByInStay');

        $admin = $this->teacher(admin: true);
        self::assertNull($this->scope->manageableProgrammeIds($admin, $stay));
        self::assertTrue($this->scope->canManageStudent($admin, $stay, $dawStudent));

        $centreAdmin = $this->teacher();
        $stay->getAcademicYear()->getEducationalCentre()->addAdmin($centreAdmin);
        self::assertNull($this->scope->manageableProgrammeIds($centreAdmin, $stay));
        self::assertTrue($this->scope->canManageAny($centreAdmin, $stay));
    }

    public function testCoordinatorManagesOnlyTheirProgrammesStudents(): void
    {
        [$stay, $daw, , $dawStudent, $damStudent] = $this->scenario();
        $this->programmes->method('findCoordinatedByInStay')->willReturn([$daw]);

        $coordinator = $this->teacher();

        self::assertSame([$daw->getId()->toRfc4122()], $this->scope->manageableProgrammeIds($coordinator, $stay));
        self::assertTrue($this->scope->canManageAny($coordinator, $stay));
        self::assertTrue($this->scope->canManageProgramme($coordinator, $stay, $daw));
        self::assertTrue($this->scope->canManageStudent($coordinator, $stay, $dawStudent));
        self::assertFalse($this->scope->canManageStudent($coordinator, $stay, $damStudent));
    }

    public function testFamilyHeadManagesTheProgrammesOfTheirFamily(): void
    {
        [$stay, , $dam, , $damStudent] = $this->scenario();
        $this->programmes->method('findHeadedByInStay')->willReturn([$dam]);

        self::assertTrue($this->scope->canManageStudent($this->teacher(), $stay, $damStudent));
    }

    public function testCoordinatorAndHeadScopesAreCombined(): void
    {
        [$stay, $daw, $dam] = $this->scenario();
        $this->programmes->method('findCoordinatedByInStay')->willReturn([$daw]);
        $this->programmes->method('findHeadedByInStay')->willReturn([$dam, $daw]);

        $ids = $this->scope->manageableProgrammeIds($this->teacher(), $stay);

        self::assertEqualsCanonicalizing([$daw->getId()->toRfc4122(), $dam->getId()->toRfc4122()], $ids);
    }

    public function testUnrelatedTeacherManagesNothing(): void
    {
        [$stay, $daw, , $dawStudent] = $this->scenario();
        $teacher = $this->teacher();

        self::assertSame([], $this->scope->manageableProgrammeIds($teacher, $stay));
        self::assertFalse($this->scope->canManageAny($teacher, $stay));
        self::assertFalse($this->scope->canManageProgramme($teacher, $stay, $daw));
        self::assertFalse($this->scope->canManageStudent($teacher, $stay, $dawStudent));
    }

    public function testStudentWithoutGroupInTheStayIsManagedByAnyoneWhoManagesAProgramme(): void
    {
        [$stay, $daw] = $this->scenario();
        $this->programmes->method('findCoordinatedByInStay')->willReturn([$daw]);
        $orphan = $this->student('Sin', 'Grupo');

        self::assertTrue($this->scope->canManageStudent($this->teacher(), $stay, $orphan));
    }

    public function testScopeIsCachedPerTeacherAndStay(): void
    {
        [$stay, $daw] = $this->scenario();
        $this->programmes->expects($this->once())->method('findCoordinatedByInStay')->willReturn([$daw]);
        $teacher = $this->teacher();

        $this->scope->manageableProgrammeIds($teacher, $stay);
        $this->scope->manageableProgrammeIds($teacher, $stay);
        $this->scope->canManageAny($teacher, $stay);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{0: Stay, 1: Programme, 2: Programme, 3: Student, 4: Student} */
    private function scenario(): array
    {
        $centre = $this->withId((new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla'));
        $year   = $this->withId((new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre));
        $family = $this->withId((new ProfessionalFamily())->setName('Informática')->setAcademicYear($year));

        $daw = $this->withId((new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($family));
        $dam = $this->withId((new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family));

        $dawStudent = $this->student('Ana', 'Daw');
        $damStudent = $this->student('Luis', 'Dam');
        foreach ([[$dawStudent, $daw, 'DAW'], [$damStudent, $dam, 'DAM']] as [$student, $programme, $label]) {
            $level = $this->withId((new ProgrammeYear())->setName('2.º ' . $label)->setProgramme($programme));
            $group = $this->withId((new Group())->setName($label . '2A')->setProgrammeYear($level));
            $student->addGroup($group);
        }

        $stay = $this->withId(new Stay());
        $stay->setAcademicYear($year)->addProgramme($daw)->addProgramme($dam);

        return [$stay, $daw, $dam, $dawStudent, $damStudent];
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
