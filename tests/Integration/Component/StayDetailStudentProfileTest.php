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
use App\Entity\StudentYearProfile;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Entity\Workcenter;
use App\Tests\Integration\ControllerTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/** La ficha del estudiante en el detalle de la estancia: insignias, filtros, recuentos y asignación. */
class StayDetailStudentProfileTest extends ControllerTestCase
{
    use InteractsWithLiveComponents;

    private EducationalCentre $centre;
    private AcademicYear $year;
    private Programme $programme;
    private ProgrammeYear $level;
    private Group $group;
    private Stay $stay;
    private Teacher $admin;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.1')->setAdmin(true);
        $this->centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $this->year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($this->centre);
        $family       = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($this->year);
        $this->programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($this->year);
        $this->level  = (new ProgrammeYear())->setName('Primero')->setProgramme($this->programme);
        $this->group  = (new Group())->setName('DAW1A')->setProgrammeYear($this->level);
        $this->stay   = (new Stay())->setName('FFEOE DAW')->setAcademicYear($this->year)->addProgramme($this->programme)
            ->setStartDate(new \DateTimeImmutable('-30 days'))->setEndDate(new \DateTimeImmutable('+30 days'));
        $this->persist($this->admin, $this->centre, $this->year, $family, $this->programme, $this->level, $this->group, $this->stay);
        $this->centre->setActiveAcademicYear($this->year);
        $this->flush();
    }

    // ── Insignias ────────────────────────────────────────────────────────────

    public function testBadgesOnlyAppearWhereThereIsSomethingToFlag(): void
    {
        $this->student('Ana', 'Plain');
        $noNuss = $this->student('Beatriz', 'SinNuss');
        $this->profile($noNuss)->setHasNuss(false);
        $excluded = $this->student('Carlos', 'Excluido');
        $this->profile($excluded)->setHasNuss(false)->setTakesFfeoe(false)->setFfeoeExclusionReason('Ya la realizó');
        $company = $this->student('Diana', 'Empresa');
        $this->profile($company)->setBringsCompany(true)->setBroughtCompanyName('Alfa S.L.')->setHasCar(true)
            ->setResidenceCity('Lebrija')->setPreferredCity('Sevilla');
        $this->flush();

        $html = $this->html();

        self::assertSame(1, substr_count($html, 'bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700'), 'solo Beatriz: quien no realiza la FFEOE no necesita NUSS');
        self::assertStringContainsString('No realiza FFEOE', $html);
        $titles = [];
        foreach ((new \Symfony\Component\DomCrawler\Crawler($html))->filter('[title]') as $node) {
            $titles[] = $node->getAttribute('title');
        }
        self::assertContains('No realiza la FFEOE: Ya la realizó', $titles, 'el motivo va en el tooltip');
        self::assertStringContainsString('Aporta: Alfa S.L.', $html);
        self::assertStringContainsString('Dispone de coche', $html);
        self::assertStringContainsString('Lebrija', $html);
        self::assertStringContainsString('Prefiere: Sevilla', $html);
    }

    public function testQuickFiltersAndRowFlagsForNussAndFfeoe(): void
    {
        $noNuss = $this->student('Beatriz', 'SinNuss');
        $this->profile($noNuss)->setHasNuss(false);
        $excluded = $this->student('Carlos', 'Excluido');
        $this->profile($excluded)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $this->flush();

        $crawler = $this->crawler();

        self::assertCount(1, $crawler->filter('.js-filter-chip[data-filter="sin-nuss"]'));
        self::assertCount(1, $crawler->filter('.js-filter-chip[data-filter="no-ffeoe"]'));
        $flags = [];
        foreach ($crawler->filter('tr.js-student-row') as $row) {
            $flags[] = $row->getAttribute('data-flags');
        }
        self::assertContains('sin-nuss sin-puesto', array_map('trim', $flags));
        self::assertContains('no-ffeoe', array_map('trim', $flags), 'quien no realiza la FFEOE no cuenta como «sin puesto»');
    }

    public function testTheEditPencilIsOnlyOfferedToThoseWhoCanEdit(): void
    {
        $student = $this->student('Ana', 'Martínez');
        $this->flush();
        $profileUrl = '/estudiantes/' . $student->getId()->toRfc4122() . '/ficha';

        self::assertStringContainsString($profileUrl, $this->html($this->admin));

        $teacher = (new Teacher(new PersonName('Doc', 'Ente')))->setUsername('docente');
        $this->persist($teacher);
        $this->group->addTeacher($teacher);
        $this->flush();
        $teacherHtml = $this->html($teacher);
        self::assertStringContainsString($student->getName()->getLastName(), $teacherHtml, 'el docente del grupo ve la estancia');
        self::assertStringNotContainsString($profileUrl, $teacherHtml, 'pero no edita la ficha');
    }

    public function testAGroupTutorCanEditTheirStudentsProfileFromTheStay(): void
    {
        $student = $this->student('Ana', 'Martínez');
        $tutor   = (new Teacher(new PersonName('Tut', 'Ora')))->setUsername('tutora');
        $this->persist($tutor);
        $this->group->addTutor($tutor);
        $this->flush();

        $html = $this->html($tutor);

        self::assertStringContainsString($student->getName()->getLastName(), $html, 'la tutora ve la estancia');
        self::assertStringContainsString('/estudiantes/' . $student->getId()->toRfc4122() . '/ficha', $html);
    }

    public function testTheAttitudeIsOnlyShownToThoseWhoCanAssignAPositionOrEditTheProfile(): void
    {
        $student = $this->student('Ana', 'Martínez');
        $this->profile($student)->setAttitude(4)->setResidenceCity('Lebrija');
        $coordinator = (new Teacher(new PersonName('Coor', 'Dinadora')))->setUsername('coordinadora');
        $plain       = (new Teacher(new PersonName('Doc', 'Ente')))->setUsername('docente');
        $this->persist($coordinator, $plain);
        $this->programme->addCoordinator($coordinator);
        $this->group->addTeacher($plain);
        $this->flush();

        self::assertStringContainsString('Actitud 4/5', $this->html($this->admin), 'la administración la ve');
        self::assertStringContainsString('Actitud 4/5', $this->html($coordinator), 'quien puede asignar puesto la ve');
        $plainHtml = $this->html($plain);
        self::assertStringContainsString('Lebrija', $plainHtml, 'el resto de la ficha sí se ve');
        self::assertStringNotContainsString('Actitud 4/5', $plainHtml, 'un docente sin permiso sobre el estudiante no la ve');
    }

    // ── Recuentos ────────────────────────────────────────────────────────────

    public function testStudentsWhoDoNotDoTheFfeoeDoNotCountAsWithoutPosition(): void
    {
        $this->student('Ana', 'Uno');
        $this->student('Beatriz', 'Dos');
        $excluded = $this->student('Carlos', 'Excluido');
        $this->profile($excluded)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $this->flush();

        $data = $this->component()->component()->getData();

        self::assertSame(2, $data['count_without_position']);
        self::assertSame(2, $data['progress']['steps'][0]['total'] ?? null, 'el avance parte de quien realiza la FFEOE');
    }

    // ── Asignación ───────────────────────────────────────────────────────────

    public function testAStudentWhoDoesNotDoTheFfeoeCannotBeAssigned(): void
    {
        $student = $this->student('Carlos', 'Excluido');
        $this->profile($student)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $position = $this->freePosition();
        $this->flush();

        $component = $this->component();
        $component->call('assignPosition', [
            'studentId'  => $student->getId()->toRfc4122(),
            'positionId' => $position->getId()->toRfc4122(),
        ]);

        self::assertStringContainsString('no realiza la FFEOE', (string) $component->render());
        $this->em->clear();
        self::assertNull($this->em->find(TrainingPosition::class, $position->getId())?->getStudent());
    }

    public function testAStudentWithoutNussCanStillBeAssignedAfterTheWarning(): void
    {
        $student = $this->student('Beatriz', 'SinNuss');
        $this->profile($student)->setHasNuss(false);
        $position = $this->freePosition();
        $this->flush();

        $component = $this->component();
        $html      = (string) $component->render();
        self::assertStringContainsString('data-nuss-warning', $html, 'el aviso de confirmación viaja en el formulario de asignar');

        $component->call('assignPosition', [
            'studentId'  => $student->getId()->toRfc4122(),
            'positionId' => $position->getId()->toRfc4122(),
        ]);

        $this->em->clear();
        self::assertNotNull($this->em->find(TrainingPosition::class, $position->getId())?->getStudent());
    }

    // ── Quitar de la estancia ────────────────────────────────────────────────

    public function testTheBannerOffersRemovingThoseWhoDoNotDoTheFfeoe(): void
    {
        $this->student('Ana', 'Uno');
        $excluded = $this->student('Carlos', 'Excluido');
        $this->profile($excluded)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $this->flush();

        $html = (string) $this->component()->render();

        self::assertStringContainsString('1 estudiante no realiza la FFEOE y sigue en la estancia', $html);
        self::assertStringContainsString('Quitarlos de la estancia', $html);
    }

    public function testRemovingDropsOnlyThoseWithoutPositionAndLeavesTheOthers(): void
    {
        $keeps    = $this->student('Ana', 'Uno');
        $free     = $this->student('Carlos', 'SinPuesto');
        $placed   = $this->student('Diana', 'ConPuesto');
        $this->profile($free)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $this->profile($placed)->setTakesFfeoe(false)->setFfeoeExclusionReason('Ya la hizo');
        $position = $this->freePosition();
        $position->setStudent($placed);
        $this->flush();

        $component = $this->component();
        $component->call('removeNonFfeoeStudents');

        self::assertStringContainsString('1 estudiante que no realiza la FFEOE se ha quitado de la estancia.', (string) $component->render());
        $this->em->clear();
        $stay = $this->em->find(Stay::class, $this->stay->getId());
        $ids  = array_map(static fn (Student $s): string => $s->getStudentId(), $stay->getStudents()->toArray());
        sort($ids);
        self::assertSame([$keeps->getStudentId(), $placed->getStudentId()], $ids, 'el que tiene puesto no se toca');
    }

    public function testRemovingIsForbiddenToThoseWhoDoNotManageTheStay(): void
    {
        $excluded = $this->student('Carlos', 'Excluido');
        $this->profile($excluded)->setTakesFfeoe(false)->setFfeoeExclusionReason('PRL');
        $teacher = (new Teacher(new PersonName('Doc', 'Ente')))->setUsername('docente');
        $this->persist($teacher);
        $this->group->addTeacher($teacher);
        $this->flush();

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->component($teacher)->call('removeNonFfeoeStudents');
    }

    // ── Mejoras de la lista de asignaciones ──────────────────────────────────

    public function testTheAssignDropdownOffersTheBestFittingPositionsFirst(): void
    {
        $student = $this->student('Ana', 'Martínez');
        $this->profile($student)->setResidenceCity('Lebrija');
        $this->freePosition('Beta S.L.', 'Jaén');
        $this->freePosition('Gamma S.L.', 'Lebrija');
        $this->flush();

        $options = $this->crawler()->filter('.js-assign-dropdown option[data-company]');

        self::assertCount(2, $options);
        self::assertSame('Gamma S.L.', $options->eq(0)->attr('data-company'), 'el de su localidad va primero');
        self::assertSame('residence', $options->eq(0)->attr('data-fit'));
        self::assertSame('Su localidad', $options->eq(0)->attr('data-fit-label'));
        self::assertSame('', $options->eq(1)->attr('data-fit'));
    }

    public function testSuggestsTheTutorsOfOtherPositionsOfTheSameCompany(): void
    {
        $teacher = (new Teacher(new PersonName('Tutor', 'Habitual')))->setUsername('tutor.habitual');
        $this->persist($teacher);
        $this->group->addTeacher($teacher);
        $first  = $this->student('Ana', 'Uno');
        $second = $this->student('Beatriz', 'Dos');
        $a      = $this->freePosition('Alfa S.L.', 'Sevilla')->setStudent($first)->setAcademicTutor($teacher);
        $b      = $this->freePosition('Alfa S.L.', 'Sevilla', $a->getWorkcenter()->getCompany())->setStudent($second);
        $this->flush();

        $crawler = $this->crawler();
        $buttons = $crawler->filter('button[data-live-action-param="setAcademicTutor"]');

        self::assertCount(1, $buttons, 'solo se sugiere donde falta el tutor');
        self::assertSame($b->getId()->toRfc4122(), $buttons->attr('data-live-position-id-param'));
        self::assertSame($teacher->getId()->toRfc4122(), $buttons->attr('data-live-teacher-id-param'));
        self::assertStringContainsString('Usar a', $buttons->text());
    }

    public function testTheGroupHeaderShowsHowManyStudentsHaveAPosition(): void
    {
        $withPosition = $this->student('Ana', 'Uno');
        $this->student('Beatriz', 'Dos');
        $this->student('Carla', 'Tres');
        $this->freePosition()->setStudent($withPosition);
        $this->flush();

        self::assertStringContainsString('1/3 con puesto', $this->html());
    }

    public function testThePendingStateOffersBackToDraftInsideTheMoreMenu(): void
    {
        $student = $this->student('Ana', 'Uno');
        $this->freePosition()->setStudent($student)->setState(TrainingPositionState::PENDING);
        $this->flush();

        $crawler = $this->crawler();

        self::assertCount(1, $crawler->filter('button[popovertarget^="state-menu-"]'));
        $menu = $crawler->filter('.js-state-menu');
        self::assertCount(1, $menu);
        self::assertStringContainsString('Volver a borrador', $menu->text());
        self::assertStringNotContainsString('Volver a borrador', $crawler->filter('td')->reduce(
            static fn ($td): bool => $td->filter('.js-state-menu')->count() === 0,
        )->text(''), 'fuera del menú ya no hay un botón de volver a borrador');
    }

    public function testTheSearchAttributeNeverContainsTheStudentId(): void
    {
        $student = $this->student('Ana', 'Martínez');
        $this->freePosition('Alfa S.L.', 'Sevilla')->setStudent($student);
        $this->flush();

        $row = $this->crawler()->filter('tr.js-student-row')->first();

        self::assertStringContainsString('Ana Martínez', $row->attr('data-search'));
        self::assertStringContainsString('Alfa S.L.', $row->attr('data-search'));
        self::assertStringNotContainsString($student->getStudentId(), $row->attr('data-search'), 'el NIE no sale en ninguna parte de la lista');
        self::assertStringNotContainsString($student->getStudentId(), $this->html());
    }

    // ── Ayudantes ────────────────────────────────────────────────────────────

    private function student(string $first, string $last): Student
    {
        $student = (new Student(new PersonName($first, $last)))->setStudentId('2025-' . ++$this->seq);
        $this->group->addStudent($student);
        $this->stay->addStudent($student);
        $this->persist($student);

        return $student;
    }

    private function profile(Student $student): StudentYearProfile
    {
        $profile = new StudentYearProfile($student, $this->year);
        $this->em->persist($profile);

        return $profile;
    }

    private function freePosition(string $companyName = 'Alfa S.L.', string $city = 'Sevilla', ?Company $company = null): TrainingPosition
    {
        $company  ??= (new Company())->setName($companyName)->setVatNumber('B' . ++$this->seq)->setCity($city)->setEducationalCentre($this->centre);
        $workcenter = (new Workcenter())->setName('Sede')->setCity($city)->setCompany($company);
        $position   = (new TrainingPosition())->setStay($this->stay)->setWorkcenter($workcenter);
        $position->addProgrammeYear($this->level);
        $this->persist($company, $workcenter, $position);

        return $position;
    }

    private function component(?Teacher $actor = null): TestLiveComponent
    {
        if ($actor !== null && !$actor->isAdmin()) {
            // Un docente que no administra necesita el centro elegido en su sesión.
            $this->loginAs($actor, $this->centre, false);
        }

        return $this->createLiveComponent('StayDetailComponent', ['stayId' => $this->stay->getId()->toRfc4122()], $this->client)
            ->actingAs($actor ?? $this->admin);
    }

    private function html(?Teacher $actor = null): string
    {
        return (string) $this->component($actor)->render();
    }

    private function crawler(?Teacher $actor = null): \Symfony\Component\DomCrawler\Crawler
    {
        return new \Symfony\Component\DomCrawler\Crawler($this->html($actor));
    }
}
