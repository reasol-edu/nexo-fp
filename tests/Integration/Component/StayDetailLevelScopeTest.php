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
use App\Entity\TrainingPositionState;
use App\Entity\Workcenter;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Una estancia de una sola enseñanza con 1.º y 2.º, y una coordinación que solo lo es de 2.º: gestiona a
 * los de 2.º, ve a los de 1.º (con candado) y no puede asignarles puesto ni actuar sobre el suyo.
 */
class StayDetailLevelScopeTest extends ControllerTestCase
{
    use InteractsWithLiveComponents;

    private Stay $stay;
    private Teacher $admin;
    private Teacher $coordSecond;
    private Student $firstStudent;
    private Student $secondStudent;
    private TrainingPosition $free;
    private TrainingPosition $firstPosition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin       = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.lv')->setAdmin(true);
        $this->coordSecond = (new Teacher(new PersonName('Coord', 'Segundo')))->setUsername('coord.segundo');
        $centre  = (new EducationalCentre())->setCode('41008888')->setName('IES Niveles')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $daw     = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $first   = (new ProgrammeYear())->setName('1.º DAW')->setProgramme($daw);
        $second  = (new ProgrammeYear())->setName('2.º DAW')->setProgramme($daw);
        $second->addCoordinator($this->coordSecond);

        $this->firstStudent  = (new Student(new PersonName('Ana', 'Primero')))->setStudentId('2025-101');
        $this->secondStudent = (new Student(new PersonName('Eva', 'Segundo')))->setStudentId('2025-102');
        $firstGroup  = (new Group())->setName('DAW1A')->setProgrammeYear($first);
        $secondGroup = (new Group())->setName('DAW2A')->setProgrammeYear($second);
        $firstGroup->addStudent($this->firstStudent);
        $secondGroup->addStudent($this->secondStudent);

        $this->stay = (new Stay())->setName('FFEOE DAW')->setAcademicYear($year)
            ->addProgrammeYear($first)->addProgrammeYear($second)
            ->setStartDate(new \DateTimeImmutable('-10 days'))->setEndDate(new \DateTimeImmutable('+60 days'));
        $this->stay->addStudent($this->firstStudent)->addStudent($this->secondStudent);

        $company    = (new Company())->setName('Alfa S.L.')->setVatNumber('B77777777')->setCity('Sevilla')->setEducationalCentre($centre);
        $workcenter = (new Workcenter())->setName('Sede')->setCity('Sevilla')->setCompany($company);
        // Un puesto libre ofertado a 1.º y 2.º y otro ya asignado a la estudiante de 1.º
        $this->free = (new TrainingPosition())->setStay($this->stay)->setWorkcenter($workcenter)->addProgrammeYear($first)->addProgrammeYear($second);
        $this->firstPosition = (new TrainingPosition())->setStay($this->stay)->setWorkcenter($workcenter)->addProgrammeYear($first)
            ->setStudent($this->firstStudent)->setState(TrainingPositionState::DRAFT);

        $this->persist(
            $this->admin, $this->coordSecond, $centre, $year, $family, $daw, $first, $second, $firstGroup, $secondGroup,
            $this->firstStudent, $this->secondStudent, $this->stay, $company, $workcenter, $this->free, $this->firstPosition,
        );
        $centre->setActiveAcademicYear($year);
        $this->flush();
    }

    public function testTheCoordinatorOfSecondYearAssignsASecondYearStudent(): void
    {
        $component = $this->component($this->coordSecond);
        $component->call('assignPosition', [
            'studentId'  => $this->secondStudent->getId()->toRfc4122(),
            'positionId' => $this->free->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('Puesto asignado a Eva Segundo.', (string) $component->render());
    }

    public function testTheCoordinatorOfSecondYearCannotAssignAFirstYearStudent(): void
    {
        $component = $this->component($this->coordSecond);

        $this->expectException(AccessDeniedException::class);
        $component->call('assignPosition', [
            'studentId'  => $this->firstStudent->getId()->toRfc4122(),
            'positionId' => $this->free->getId()->toRfc4122(),
        ]);
    }

    public function testBulkActionsSkipTheFirstYearPositionOfTheOtherCoordination(): void
    {
        $component = $this->component($this->coordSecond);

        $component->call('bulk', ['op' => 'sign', 'positionIds' => [$this->firstPosition->getId()->toRfc4122()]]);

        $this->em->clear();
        self::assertFalse($this->em->find(TrainingPosition::class, $this->firstPosition->getId())->isSigned());
    }

    public function testFirstYearStudentsAreLockedAndTheViewerGetsTheOwnStudentsFilter(): void
    {
        $crawler = new Crawler((string) $this->component($this->coordSecond)->render());

        self::assertCount(1, $crawler->filter('.js-mine-toggle'), 'con varias coordinaciones se ofrece «Mis estudiantes»');
        $rows = $crawler->filter('tr.js-student-row');
        self::assertCount(2, $rows);
        $byMine = [];
        foreach ($rows as $row) {
            $byMine[$row->getAttribute('data-mine')] = (new Crawler($row))->text();
        }
        self::assertStringContainsString('Eva Segundo', $byMine['1']);
        self::assertStringContainsString('Ana Primero', $byMine['0']);
        // Selector de asignar solo en la fila de 2.º (la de 1.º ya tiene puesto y no es suya)
        self::assertCount(0, $crawler->filter('tr.js-student-row[data-mine="0"] .js-assign-form'));
        self::assertCount(1, $crawler->filter('tr.js-student-row[data-mine="1"] .js-assign-form'));
        self::assertCount(1, $crawler->filter('tr.js-student-row[data-mine="0"] [title*="otra coordinación"]'), 'candado de estudiante ajeno');
        self::assertCount(0, $crawler->filter('tr.js-student-row[data-mine="1"] [title*="otra coordinación"]'));
    }

    public function testAnAdminSeesNoLocksNorTheOwnStudentsFilterInAFullScopeStay(): void
    {
        $crawler = new Crawler((string) $this->component($this->admin)->render());

        self::assertCount(0, $crawler->filter('.js-mine-toggle'));
        self::assertCount(0, $crawler->filter('tr.js-student-row[data-mine="0"]'));
    }

    private function component(Teacher $actor): TestLiveComponent
    {
        return $this->createLiveComponent('StayDetailComponent', ['stayId' => $this->stay->getId()->toRfc4122()], $this->client)
            ->actingAs($actor);
    }
}
