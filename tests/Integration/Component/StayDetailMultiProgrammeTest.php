<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\Workcenter;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Estancia que reúne DAW y DAM: cada coordinación gestiona solo a su alumnado, pero ve a todo el
 * mundo, y un puesto asignado deja de estar disponible para cualquier otro estudiante.
 */
class StayDetailMultiProgrammeTest extends ControllerTestCase
{
    use InteractsWithLiveComponents;

    public function testCoordinatorAssignsOwnStudentToSharedPosition(): void
    {
        $s = $this->makeScenario();

        $component = $this->component($s['stay'], $s['coordDaw']);
        $component->call('assignPosition', [
            'studentId'  => $s['dawStudent']->getId()->toRfc4122(),
            'positionId' => $s['shared']->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('Puesto asignado a Ana Daw.', (string) $component->render());
        $this->em->clear();
        self::assertSame(
            $s['dawStudent']->getId()->toRfc4122(),
            $this->em->find(TrainingPosition::class, $s['shared']->getId())->getStudent()?->getId()->toRfc4122(),
        );
    }

    public function testCoordinatorCannotAssignAStudentOfAnotherProgramme(): void
    {
        $s = $this->makeScenario();
        $positionId = $s['shared']->getId();

        $component = $this->component($s['stay'], $s['coordDam']);

        try {
            $component->call('assignPosition', [
                'studentId'  => $s['dawStudent']->getId()->toRfc4122(),
                'positionId' => $positionId->toRfc4122(),
            ]);
            self::fail('La asignación de un alumno ajeno debe denegarse.');
        } catch (AccessDeniedException) {
            // esperado
        }

        $this->em->clear();
        self::assertNull($this->em->find(TrainingPosition::class, $positionId)->getStudent());
    }

    public function testAssignedSharedPositionIsNoLongerAvailableToAStudentOfAnotherProgramme(): void
    {
        $s = $this->makeScenario();

        $dawComponent = $this->component($s['stay'], $s['coordDaw']);
        $dawComponent->call('assignPosition', [
            'studentId'  => $s['dawStudent']->getId()->toRfc4122(),
            'positionId' => $s['shared']->getId()->toRfc4122(),
        ]);

        $damComponent = $this->component($s['stay'], $s['coordDam']);
        $damComponent->call('assignPosition', [
            'studentId'  => $s['damStudent']->getId()->toRfc4122(),
            'positionId' => $s['shared']->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('Ese puesto ya no está disponible', (string) $damComponent->render());
        $this->em->clear();
        self::assertSame(
            $s['dawStudent']->getId()->toRfc4122(),
            $this->em->find(TrainingPosition::class, $s['shared']->getId())->getStudent()?->getId()->toRfc4122(),
        );
        self::assertNull($this->em->find(TrainingPosition::class, $s['damOnly']->getId())->getStudent());
    }

    public function testPositionNotOfferedToTheStudentsLevelIsRejected(): void
    {
        $s = $this->makeScenario();

        $component = $this->component($s['stay'], $s['coordDaw']);
        $component->call('assignPosition', [
            'studentId'  => $s['dawStudent']->getId()->toRfc4122(),
            'positionId' => $s['damOnly']->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('no está ofertado al nivel de Ana Daw', (string) $component->render());
        $this->em->clear();
        self::assertNull($this->em->find(TrainingPosition::class, $s['damOnly']->getId())->getStudent());
    }

    public function testPriorityKeepsPositionForItsProgrammeUntilTheDate(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setPriority($s['daw'], new \DateTimeImmutable('+10 days'));
        $this->flush();

        $component = $this->component($s['stay'], $s['coordDam']);
        $component->call('assignPosition', [
            'studentId'  => $s['damStudent']->getId()->toRfc4122(),
            'positionId' => $s['shared']->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('tiene preferencia para DAW hasta el', (string) $component->render());
        $this->em->clear();
        self::assertNull($this->em->find(TrainingPosition::class, $s['shared']->getId())->getStudent());
    }

    public function testPriorityIsReleasedAfterTheDate(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setPriority($s['daw'], new \DateTimeImmutable('-1 day'));
        $this->flush();

        $component = $this->component($s['stay'], $s['coordDam']);
        $component->call('assignPosition', [
            'studentId'  => $s['damStudent']->getId()->toRfc4122(),
            'positionId' => $s['shared']->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('Puesto asignado a Luis Dam.', (string) $component->render());
    }

    public function testPriorityOnItsLastDayStillAppliesAndProgrammeStudentsAreNotAffected(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setPriority($s['daw'], new \DateTimeImmutable('today'));
        $this->flush();

        $component = $this->component($s['stay'], $s['coordDaw']);
        $component->call('assignPosition', [
            'studentId'  => $s['dawStudent']->getId()->toRfc4122(),
            'positionId' => $s['shared']->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('Puesto asignado a Ana Daw.', (string) $component->render());
    }

    public function testCoordinatorSeesEveryoneButOnlyManagesOwnStudents(): void
    {
        $s    = $this->makeScenario();
        $html = (string) $this->component($s['stay'], $s['coordDaw'])->render();

        // Ve a todo el alumnado de la estancia…
        self::assertStringContainsString('Ana Daw', $html);
        self::assertStringContainsString('Luis Dam', $html);
        // …pero el NIE solo del suyo.
        self::assertStringContainsString('NIE-DAW', $html);
        self::assertStringNotContainsString('NIE-DAM', $html);
        // Filtro «Mis estudiantes» y panel de reparto por enseñanza.
        self::assertStringContainsString('js-mine-toggle', $html);
        self::assertStringContainsString('data-mine="1"', $html);
        self::assertStringContainsString('data-mine="0"', $html);
        self::assertStringContainsString('Reparto por enseñanza', $html);
    }

    public function testSummaryCountsStudentsWithoutPositionAndFreePositionsPerProgramme(): void
    {
        $s = $this->makeScenario();
        $s['damOnly']->setStudent($s['damStudent']);
        $this->flush();

        /** @var array<string, mixed> $data */
        $data = $this->component($s['stay'], $s['coordDaw'])->component()->getData();
        $rows = [];
        foreach ($data['programme_summary'] as $row) {
            $rows[$row['programme']->getName()] = $row;
        }

        self::assertSame(1, $rows['DAW']['students']);
        self::assertSame(1, $rows['DAW']['without_position']);
        self::assertSame(1, $rows['DAW']['free'], 'El puesto compartido está libre para DAW');
        self::assertTrue($rows['DAW']['mine']);
        self::assertSame(1, $rows['DAM']['students']);
        self::assertSame(0, $rows['DAM']['without_position']);
        self::assertSame(1, $rows['DAM']['free'], 'El puesto compartido también está libre para DAM');
        self::assertFalse($rows['DAM']['mine']);
    }

    public function testSummaryMarksPositionsReservedForAnotherProgramme(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setPriority($s['daw'], new \DateTimeImmutable('+10 days'));
        $this->flush();

        /** @var array<string, mixed> $data */
        $data = $this->component($s['stay'], $s['coordDam'])->component()->getData();
        $rows = [];
        foreach ($data['programme_summary'] as $row) {
            $rows[$row['programme']->getName()] = $row;
        }

        // El puesto compartido está reservado a DAW; el exclusivo de DAM sigue libre para DAM.
        self::assertSame(1, $rows['DAM']['reserved_elsewhere']);
        self::assertSame(1, $rows['DAM']['free']);
        self::assertSame(1, $rows['DAW']['free']);
        self::assertSame(0, $rows['DAW']['reserved_elsewhere']);
    }

    public function testForeignStudentsHaveNoAssignDropdownForTheCoordinator(): void
    {
        $s = $this->makeScenario();

        /** @var array<string, mixed> $data */
        $data = $this->component($s['stay'], $s['coordDaw'])->component()->getData();

        self::assertNotEmpty($data['compatible_positions_for_student'][$s['dawStudent']->getId()->toRfc4122()]);
        self::assertSame([], $data['compatible_positions_for_student'][$s['damStudent']->getId()->toRfc4122()]);
        self::assertArrayHasKey($s['dawStudent']->getId()->toRfc4122(), $data['manageable_student_ids']);
        self::assertArrayNotHasKey($s['damStudent']->getId()->toRfc4122(), $data['manageable_student_ids']);
    }

    public function testCoordinatorCannotUnassignAnotherProgrammesStudent(): void
    {
        $s = $this->makeScenario();
        $s['shared']->setStudent($s['dawStudent']);
        $this->flush();
        $positionId = $s['shared']->getId();

        $component = $this->component($s['stay'], $s['coordDam']);

        try {
            $component->call('unassignPosition', ['positionId' => $positionId->toRfc4122()]);
            self::fail('Quitar el puesto de un alumno ajeno debe denegarse.');
        } catch (AccessDeniedException) {
            // esperado
        }

        $this->em->clear();
        self::assertNotNull($this->em->find(TrainingPosition::class, $positionId)->getStudent());
    }

    public function testStayDeleteButtonOnlyForWhoManagesEveryProgramme(): void
    {
        $s = $this->makeScenario();

        $deleteUrl = '/estancias/' . $s['stay']->getId()->toRfc4122() . '/eliminar';

        self::assertStringNotContainsString($deleteUrl, (string) $this->component($s['stay'], $s['coordDaw'])->render());
        self::assertStringContainsString($deleteUrl, (string) $this->component($s['stay'], $s['admin'])->render());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function component(Stay $stay, Teacher $actor): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        return $this->createLiveComponent(
            'StayDetailComponent',
            ['stayId' => $stay->getId()->toRfc4122()],
            $this->client,
        )->actingAs($actor);
    }

    /**
     * @return array{
     *     admin: Teacher, coordDaw: Teacher, coordDam: Teacher, stay: Stay, daw: Programme, dam: Programme,
     *     dawStudent: Student, damStudent: Student, shared: TrainingPosition, damOnly: TrainingPosition
     * }
     */
    private function makeScenario(): array
    {
        $admin    = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.mp')->setAdmin(true);
        $coordDaw = (new Teacher(new PersonName('Coord', 'Daw')))->setUsername('coord.daw');
        $coordDam = (new Teacher(new PersonName('Coord', 'Dam')))->setUsername('coord.dam');
        $centre   = (new EducationalCentre())->setCode('41000077')->setName('IES Test')->setCity('Sevilla');
        $year     = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family   = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);

        $daw = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year)->addCoordinator($coordDaw);
        $dam = (new Programme())->setName('DAM')->setProfessionalFamily($family)->setAcademicYear($year)->addCoordinator($coordDam);

        $dawLevel = (new ProgrammeYear())->setName('2.º DAW')->setProgramme($daw);
        $damLevel = (new ProgrammeYear())->setName('2.º DAM')->setProgramme($dam);
        $dawGroup = (new Group())->setName('DAW2A')->setProgrammeYear($dawLevel);
        $damGroup = (new Group())->setName('DAM2A')->setProgrammeYear($damLevel);

        $dawStudent = (new Student(new PersonName('Ana', 'Daw')))->setStudentId('NIE-DAW');
        $damStudent = (new Student(new PersonName('Luis', 'Dam')))->setStudentId('NIE-DAM');
        $dawGroup->addStudent($dawStudent);
        $damGroup->addStudent($damStudent);

        $stay = (new Stay())
            ->setName('FFEOE 2025')
            ->setAcademicYear($year)
            ->addProgramme($daw)
            ->addProgramme($dam)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable('+30 days'))
            ->addStudent($dawStudent)
            ->addStudent($damStudent);

        $company    = (new Company())->setName('Empresa Test S.L.')->setVatNumber('B12345678')->setCity('Sevilla')->setEducationalCentre($centre);
        $workcenter = (new Workcenter())->setName('Centro Principal')->setCity('Sevilla')->setCompany($company);

        $shared  = (new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter)->addProgrammeYear($dawLevel)->addProgrammeYear($damLevel);
        $damOnly = (new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter)->addProgrammeYear($damLevel);

        $this->persist(
            $admin, $coordDaw, $coordDam, $centre, $year, $family, $daw, $dam, $dawLevel, $damLevel, $dawGroup, $damGroup,
            $dawStudent, $damStudent, $stay, $company, $workcenter, $shared, $damOnly,
        );
        $centre->setActiveAcademicYear($year);
        $this->flush();

        return compact('admin', 'coordDaw', 'coordDam', 'stay', 'daw', 'dam', 'dawStudent', 'damStudent', 'shared', 'damOnly');
    }
}
