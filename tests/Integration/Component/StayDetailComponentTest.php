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
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class StayDetailComponentTest extends ControllerTestCase
{
    use InteractsWithLiveComponents;

    public function testAssignPositionShowsToast(): void
    {
        [$admin, $stay, $student, $position] = $this->makeScenario();

        $component = $this->createLiveComponent(
            'StayDetailComponent',
            ['stayId' => $stay->getId()->toRfc4122()],
            $this->client,
        )->actingAs($admin);

        $component->call('assignPosition', [
            'studentId'  => $student->getId()->toRfc4122(),
            'positionId' => $position->getId()->toRfc4122(),
        ]);

        $html = (string) $component->render();
        self::assertStringContainsString('Puesto asignado a Ana Martinez.', $html);

        $this->em->clear();
        $updated = $this->em->find(TrainingPosition::class, $position->getId());
        self::assertNotNull($updated->getStudent());
    }

    public function testSetAcademicTutorShowsToast(): void
    {
        [$admin, $stay, $student, $position] = $this->makeScenario();
        $tutor = (new Teacher(new PersonName('Luisa', 'Gomez')))->setUsername('tutor.1');
        $position->setStudent($student);
        $this->persist($tutor);

        $component = $this->createLiveComponent(
            'StayDetailComponent',
            ['stayId' => $stay->getId()->toRfc4122()],
            $this->client,
        )->actingAs($admin);

        $component->call('setAcademicTutor', [
            'positionId' => $position->getId()->toRfc4122(),
            'teacherId'  => $tutor->getId()->toRfc4122(),
        ]);

        $html = (string) $component->render();
        self::assertStringContainsString('Tutoría dual docente asignada a Luisa Gomez.', $html);
    }

    public function testUnassignPositionShowsToast(): void
    {
        [$admin, $stay, $student, $position] = $this->makeScenario();
        $position->setStudent($student);
        $this->flush();

        $component = $this->createLiveComponent(
            'StayDetailComponent',
            ['stayId' => $stay->getId()->toRfc4122()],
            $this->client,
        )->actingAs($admin);

        $component->call('unassignPosition', [
            'positionId' => $position->getId()->toRfc4122(),
        ]);

        $html = (string) $component->render();
        self::assertStringContainsString('Puesto de Ana Martinez liberado.', $html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{0: Teacher, 1: Stay, 2: Student, 3: TrainingPosition} */
    private function makeScenario(): array
    {
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.1')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        $family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level     = (new ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $group     = (new Group())->setName('DAW1A')->setProgrammeYear($level);

        $stay = (new Stay())
            ->setName('Estancia DAW 2025')
            ->setAcademicYear($year)
            ->setProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable('+30 days'));

        $company    = (new Company())->setName('Empresa Test S.L.')->setVatNumber('B12345678')->setCity('Sevilla')->setEducationalCentre($centre);
        $workcenter = (new Workcenter())->setName('Centro Principal')->setCity('Sevilla')->setCompany($company);
        $position   = (new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter);
        $position->addProgrammeYear($level);

        $student = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $group->addStudent($student);
        $stay->addStudent($student);

        $this->persist($admin, $centre, $year, $family, $programme, $level, $group, $stay, $company, $workcenter, $position, $student);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        return [$admin, $stay, $student, $position];
    }
}
