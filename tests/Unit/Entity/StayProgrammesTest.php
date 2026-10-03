<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\TrainingPosition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/** Una estancia con varias enseñanzas y los puestos que se ofertan a niveles de varias de ellas. */
class StayProgrammesTest extends TestCase
{
    private AcademicYear $year;

    protected function setUp(): void
    {
        $centre     = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $this->year = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
    }

    // ── Enseñanzas de la estancia ─────────────────────────────────────────────

    public function testProgrammesAreAddedOnceAndRemoved(): void
    {
        $daw  = $this->programme('DAW', 'Informática');
        $stay = new Stay();

        $stay->addProgramme($daw)->addProgramme($daw);
        self::assertCount(1, $stay->getProgrammes());
        self::assertTrue($stay->hasProgramme($daw));

        $stay->removeProgramme($daw);
        self::assertCount(0, $stay->getProgrammes());
        self::assertFalse($stay->hasProgramme($daw));
    }

    public function testProgrammesCanBeAddedBeforeTheyHaveAnId(): void
    {
        // Una enseñanza recién creada todavía no tiene id: no debe fallar al añadirla.
        $stay = new Stay();
        $stay->addProgramme((new Programme())->setName('DAW')->setAcademicYear($this->year));
        $stay->addProgramme((new Programme())->setName('DAM')->setAcademicYear($this->year));

        self::assertCount(2, $stay->getProgrammes());
    }

    public function testProgrammeNamesAreSortedAndJoined(): void
    {
        $stay = (new Stay())
            ->addProgramme($this->programme('DAW', 'Informática'))
            ->addProgramme($this->programme('ASIR', 'Informática'))
            ->addProgramme($this->programme('DAM', 'Informática'));

        self::assertSame('ASIR · DAM · DAW', $stay->getProgrammeNames());
    }

    public function testFamilyNamesAreDistinct(): void
    {
        $informatica = $this->family('Informática');
        $sanidad     = $this->family('Sanidad');
        $stay = (new Stay())
            ->addProgramme($this->programmeIn('DAW', $informatica))
            ->addProgramme($this->programmeIn('DAM', $informatica))
            ->addProgramme($this->programmeIn('Cuidados', $sanidad));

        self::assertSame('Informática · Sanidad', $stay->getFamilyNames());
        self::assertCount(2, $stay->getProfessionalFamilies());
    }

    // ── Alumnado ──────────────────────────────────────────────────────────────

    public function testProgrammesOfStudentUseOnlyTheStaysProgrammes(): void
    {
        [$stay, $daw, , $dawStudent] = $this->twoProgrammeStay();
        $otherProgramme = $this->programme('ASIR', 'Informática');
        $dawStudent->addGroup($this->group($this->level($otherProgramme, '1.º ASIR')));

        $programmes = $stay->getProgrammesOfStudent($dawStudent);

        self::assertCount(1, $programmes);
        self::assertSame($daw, $programmes[0]);
    }

    public function testStudentWithoutGroupInTheStayHasNoProgrammes(): void
    {
        [$stay] = $this->twoProgrammeStay();

        self::assertSame([], $stay->getProgrammesOfStudent($this->student('Sin', 'Grupo')));
    }

    // ── Compatibilidad del puesto ─────────────────────────────────────────────

    public function testPositionAcceptsStudentsOfTheOfferedLevels(): void
    {
        [$stay, , , $dawStudent, $damStudent, $dawLevel, $damLevel] = $this->twoProgrammeStay();

        $shared = (new TrainingPosition())->setStay($stay)->addProgrammeYear($dawLevel)->addProgrammeYear($damLevel);
        $dawOnly = (new TrainingPosition())->setStay($stay)->addProgrammeYear($dawLevel);

        self::assertTrue($shared->acceptsStudent($dawStudent));
        self::assertTrue($shared->acceptsStudent($damStudent));
        self::assertTrue($dawOnly->acceptsStudent($dawStudent));
        self::assertFalse($dawOnly->acceptsStudent($damStudent));
    }

    public function testPositionWithoutLevelsOrStudentWithoutGroupDoNotRestrict(): void
    {
        [$stay, , , $dawStudent, , $dawLevel] = $this->twoProgrammeStay();

        self::assertTrue((new TrainingPosition())->setStay($stay)->acceptsStudent($dawStudent));
        self::assertTrue((new TrainingPosition())->setStay($stay)->addProgrammeYear($dawLevel)->acceptsStudent($this->student('Sin', 'Grupo')));
    }

    public function testAcceptsStudentCanEvaluateSubmittedLevelsInsteadOfTheStoredOnes(): void
    {
        [$stay, , , $dawStudent, , $dawLevel, $damLevel] = $this->twoProgrammeStay();
        $position = (new TrainingPosition())->setStay($stay)->addProgrammeYear($damLevel);

        self::assertFalse($position->acceptsStudent($dawStudent));
        self::assertTrue($position->acceptsStudent($dawStudent, [$dawLevel]));
    }

    public function testPositionProgrammeNamesFollowTheStudentOnceAssigned(): void
    {
        [$stay, , , $dawStudent, , $dawLevel, $damLevel] = $this->twoProgrammeStay();
        $position = (new TrainingPosition())->setStay($stay)->addProgrammeYear($dawLevel)->addProgrammeYear($damLevel);

        self::assertSame('DAM · DAW', $position->getProgrammeNames(), 'Libre: las de los niveles ofertados');

        $position->setStudent($dawStudent);
        self::assertSame('DAW', $position->getProgrammeNames(), 'Asignado: las del estudiante');
    }

    // ── Preferencia temporal ──────────────────────────────────────────────────

    public function testPriorityReservesThePositionAgainstOtherProgrammesUntilTheDateInclusive(): void
    {
        [$stay, $daw, , $dawStudent, $damStudent, $dawLevel, $damLevel] = $this->twoProgrammeStay();
        $position = (new TrainingPosition())->setStay($stay)->addProgrammeYear($dawLevel)->addProgrammeYear($damLevel)
            ->setPriority($daw, new \DateTimeImmutable('2025-04-15'));

        $before = new \DateTimeImmutable('2025-04-01 10:00');
        $lastDay = new \DateTimeImmutable('2025-04-15 23:59');
        $after  = new \DateTimeImmutable('2025-04-16 00:00');

        self::assertTrue($position->isReservedAgainst($damStudent, $before));
        self::assertTrue($position->isReservedAgainst($damStudent, $lastDay), 'La fecha límite es inclusive');
        self::assertFalse($position->isReservedAgainst($damStudent, $after), 'Pasada la fecha, se abre a todas');
        self::assertFalse($position->isReservedAgainst($dawStudent, $before), 'La enseñanza preferente no queda excluida');
        self::assertTrue($position->hasActivePriority($lastDay));
        self::assertFalse($position->hasActivePriority($after));
    }

    public function testPriorityNeedsBothProgrammeAndDate(): void
    {
        [$stay, $daw, , , $damStudent] = $this->twoProgrammeStay();
        $position = (new TrainingPosition())->setStay($stay);

        $position->setPriority($daw, null);
        self::assertNull($position->getPriorityProgramme());
        self::assertNull($position->getPriorityUntil());

        $position->setPriority(null, new \DateTimeImmutable('2025-04-15'));
        self::assertNull($position->getPriorityProgramme());
        self::assertFalse($position->isReservedAgainst($damStudent, new \DateTimeImmutable('2025-04-01')));
    }

    public function testStudentWithoutGroupIsNeverReservedAgainst(): void
    {
        [$stay, $daw] = $this->twoProgrammeStay();
        $position = (new TrainingPosition())->setStay($stay)->setPriority($daw, new \DateTimeImmutable('2025-04-15'));

        self::assertFalse($position->isReservedAgainst($this->student('Sin', 'Grupo'), new \DateTimeImmutable('2025-04-01')));
    }

    public function testIsReservedAgainstCanEvaluateASubmittedPriority(): void
    {
        [$stay, $daw, , $dawStudent, $damStudent] = $this->twoProgrammeStay();
        $position = (new TrainingPosition())->setStay($stay);
        $today    = new \DateTimeImmutable('2025-04-01');
        $until    = new \DateTimeImmutable('2025-04-15');

        self::assertFalse($position->isReservedAgainst($damStudent, $today));
        self::assertTrue($position->isReservedAgainst($damStudent, $today, $daw, $until));
        self::assertFalse($position->isReservedAgainst($dawStudent, $today, $daw, $until));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return array{0: Stay, 1: Programme, 2: Programme, 3: Student, 4: Student, 5: ProgrammeYear, 6: ProgrammeYear}
     */
    private function twoProgrammeStay(): array
    {
        $family = $this->family('Informática');
        $daw    = $this->programmeIn('DAW', $family);
        $dam    = $this->programmeIn('DAM', $family);

        $dawLevel = $this->level($daw, '2.º DAW');
        $damLevel = $this->level($dam, '2.º DAM');

        $dawStudent = $this->student('Ana', 'Daw');
        $damStudent = $this->student('Luis', 'Dam');
        $dawStudent->addGroup($this->group($dawLevel));
        $damStudent->addGroup($this->group($damLevel));

        $stay = (new Stay())->addProgramme($daw)->addProgramme($dam)->setAcademicYear($this->year);

        return [$stay, $daw, $dam, $dawStudent, $damStudent, $dawLevel, $damLevel];
    }

    private function family(string $name): ProfessionalFamily
    {
        return $this->withId((new ProfessionalFamily())->setName($name)->setAcademicYear($this->year));
    }

    private function programme(string $name, string $familyName): Programme
    {
        return $this->programmeIn($name, $this->family($familyName));
    }

    private function programmeIn(string $name, ProfessionalFamily $family): Programme
    {
        return $this->withId((new Programme())->setName($name)->setAcademicYear($this->year)->setProfessionalFamily($family));
    }

    private function level(Programme $programme, string $name): ProgrammeYear
    {
        return $this->withId((new ProgrammeYear())->setName($name)->setProgramme($programme));
    }

    private function group(ProgrammeYear $level): Group
    {
        return $this->withId((new Group())->setName('G-' . $level->getName())->setProgrammeYear($level));
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
