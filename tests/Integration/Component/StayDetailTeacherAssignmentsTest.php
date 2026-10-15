<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\StayTeacherHours;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Repository\StayTeacherHoursRepository;
use App\Tests\Integration\ControllerTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/** Ajuste de estancia «Calcular asignaciones por docente»: horas dualizadas por docente y reparto de tutorías. */
class StayDetailTeacherAssignmentsTest extends ControllerTestCase
{
    use InteractsWithLiveComponents;

    private Teacher $admin;
    private Stay $stay;
    private Group $group;
    private Teacher $ana;
    private Teacher $beto;
    /** @var list<Student> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.1')->setAdmin(true);
        $centre       = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year         = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family       = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme    = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level        = (new ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $this->group  = (new Group())->setName('DAW1A')->setProgrammeYear($level);
        $this->ana    = (new Teacher(new PersonName('Ana', 'Alonso')))->setUsername('ana.alonso');
        $this->beto   = (new Teacher(new PersonName('Beto', 'Bravo')))->setUsername('beto.bravo');
        $this->group->addTeacher($this->ana)->addTeacher($this->beto);

        $this->stay = (new Stay())
            ->setName('Estancia DAW')
            ->setAcademicYear($year)
            ->addProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable('+30 days'))
            ->setCalculateByTeacher(true);

        $this->persist($this->admin, $centre, $year, $family, $programme, $level, $this->ana, $this->beto, $this->group, $this->stay);

        // Cuatro estudiantes del grupo en la estancia.
        foreach (['Carla', 'Diego', 'Elena', 'Fran'] as $i => $name) {
            $student = (new Student(new PersonName($name, 'Alumno')))->setStudentId('2024-00' . $i);
            $this->group->addStudent($student);
            $this->stay->addStudent($student);
            $this->persist($student);
            $this->students[] = $student;
        }
        $centre->setActiveAcademicYear($year);
        $this->flush();
    }

    public function testTheSectionOnlyAppearsWhenTheSettingIsEnabled(): void
    {
        $this->stay->setCalculateByTeacher(false);
        $this->flush();

        $html = (string) $this->component()->render();

        self::assertStringNotContainsString('id="stay-teachers"', $html);
        self::assertNull($this->component()->component()->getData()['teacher_assignments']);
    }

    public function testWithoutHoursTheSectionAsksForThem(): void
    {
        $html = (string) $this->component()->render();

        self::assertStringContainsString('id="stay-teachers"', $html);
        self::assertStringContainsString('Indica más abajo las horas dualizadas', $html);
        self::assertStringContainsString('js-teacher-hours', $html);   // casillas de horas para quien gestiona
    }

    public function testSavingHoursSplitsTheGroupProportionallyAndTheTotalMatchesTheStudents(): void
    {
        $component = $this->component();
        $component->call('setTeacherHours', $this->args($this->ana, '30'));
        $component->call('setTeacherHours', $this->args($this->beto, '10'));

        $data = $component->component()->getData()['teacher_assignments'];

        self::assertCount(1, $data['groups']);
        $rows = array_column($data['groups'][0]['rows'], 'expected', 'hours');
        self::assertSame([30 => 3, 10 => 1], $rows);
        self::assertSame(4, array_sum(array_column($data['groups'][0]['rows'], 'expected')), 'la suma debe coincidir con los estudiantes del grupo');
        self::assertSame(40, $data['groups'][0]['total_hours']);
        self::assertStringContainsString('Faltan 3', (string) $component->render());   // Ana debería tener 3 y tiene 0
    }

    public function testAnOddSplitStillAddsUpToTheStudentsOfTheGroup(): void
    {
        $component = $this->component();
        $component->call('setTeacherHours', $this->args($this->ana, '10'));
        $component->call('setTeacherHours', $this->args($this->beto, '10'));
        $component->call('setTeacherHours', $this->args($this->beto, '10'));

        $expected = array_column($component->component()->getData()['teacher_assignments']['groups'][0]['rows'], 'expected');

        self::assertSame(4, array_sum($expected));
        self::assertSame([2, 2], $expected);
    }

    public function testTheAssignedTutorsAreCountedAndComparedWithWhatTheyShouldHave(): void
    {
        // Ana tutoriza a dos estudiantes; Beto, a uno; el cuarto no tiene puesto.
        foreach ([[0, $this->ana], [1, $this->ana], [2, $this->beto]] as [$i, $tutor]) {
            $this->persist((new TrainingPosition())->setStay($this->stay)->setStudent($this->students[$i])->setAcademicTutor($tutor));
        }

        $component = $this->component();
        $component->call('setTeacherHours', $this->args($this->ana, '20'));
        $component->call('setTeacherHours', $this->args($this->beto, '20'));

        $data    = $component->component()->getData()['teacher_assignments'];
        $summary = [];
        foreach ($data['teachers'] as $t) {
            $summary[$t['teacher']->getUsername()] = [$t['expected'], $t['assigned']];
        }

        self::assertSame(['ana.alonso' => [2, 2], 'beto.bravo' => [2, 1]], $summary);
        $html = (string) $component->render();
        self::assertStringContainsString('Completo', $html);
        self::assertStringContainsString('Falta 1', $html);
    }

    public function testATutorWhoDoesNotTeachInTheGroupIsShownWithoutExpectedStudents(): void
    {
        $outsider = (new Teacher(new PersonName('Zoe', 'Zapata')))->setUsername('zoe.zapata');
        $this->persist($outsider, (new TrainingPosition())->setStay($this->stay)->setStudent($this->students[0])->setAcademicTutor($outsider));

        $component = $this->component();
        $component->call('setTeacherHours', $this->args($this->ana, '10'));

        $summary = [];
        foreach ($component->component()->getData()['teacher_assignments']['teachers'] as $t) {
            $summary[$t['teacher']->getUsername()] = [$t['expected'], $t['assigned']];
        }

        self::assertSame([4, 0], $summary['ana.alonso']);
        self::assertSame([0, 1], $summary['zoe.zapata']);
        self::assertStringContainsString('Sobra 1', (string) $component->render());
    }

    public function testAnEmptyOrZeroValueRemovesTheHours(): void
    {
        $component = $this->component();
        $component->call('setTeacherHours', $this->args($this->ana, '12'));
        self::assertSame(12, $this->savedHours($this->ana));

        $component->call('setTeacherHours', $this->args($this->ana, ''));

        self::assertNull($this->savedHours($this->ana));
    }

    public function testUpdatingTheHoursKeepsASingleRow(): void
    {
        $component = $this->component();
        $component->call('setTeacherHours', $this->args($this->ana, '12'));
        $component->call('setTeacherHours', $this->args($this->ana, '20'));

        self::assertSame(20, $this->savedHours($this->ana));
        self::assertCount(1, self::getContainer()->get(StayTeacherHoursRepository::class)->findAll());
    }

    public function testATeacherWhoDoesNotTeachInTheGroupCannotHaveHours(): void
    {
        $outsider = (new Teacher(new PersonName('Zoe', 'Zapata')))->setUsername('zoe.zapata');
        $this->persist($outsider);

        $this->component()->call('setTeacherHours', $this->args($outsider, '10'));

        self::assertNull($this->savedHours($outsider));
    }

    public function testWithTheSettingDisabledHoursCannotBeSaved(): void
    {
        $this->stay->setCalculateByTeacher(false);
        $this->flush();

        try {
            $this->component()->call('setTeacherHours', $this->args($this->ana, '10'));
        } catch (\Throwable) {
            // se rechaza con acceso denegado
        }

        self::assertNull($this->savedHours($this->ana));
    }

    public function testSomeoneWhoCannotManageTheStayCannotSaveHours(): void
    {
        $nobody = (new Teacher(new PersonName('Nadie', 'Nadie')))->setUsername('nadie.1');
        $this->persist($nobody);

        try {
            $this->createLiveComponent('StayDetailComponent', ['stayId' => $this->stay->getId()->toRfc4122()], $this->client)
                ->actingAs($nobody)
                ->call('setTeacherHours', $this->args($this->ana, '10'));
        } catch (\Throwable) {
            // se rechaza con acceso denegado
        }

        self::assertNull($this->savedHours($this->ana));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function component(): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        return $this->createLiveComponent('StayDetailComponent', ['stayId' => $this->stay->getId()->toRfc4122()], $this->client)
            ->actingAs($this->admin);
    }

    /** @return array<string, string> */
    private function args(Teacher $teacher, string $hours): array
    {
        return [
            'groupId'   => $this->group->getId()->toRfc4122(),
            'teacherId' => $teacher->getId()->toRfc4122(),
            'hours'     => $hours,
        ];
    }

    private function savedHours(Teacher $teacher): ?int
    {
        $this->em->clear();
        $row = self::getContainer()->get(StayTeacherHoursRepository::class)->findOneBy(['teacher' => $teacher->getId()]);

        return $row instanceof StayTeacherHours ? $row->getHours() : null;
    }
}
