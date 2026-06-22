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
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class ProfessionalFamilyListComponentTest extends ControllerTestCase
{
    use InteractsWithLiveComponents;

    // ── Inline add (create) ────────────────────────────────────────────────────

    public function testAddFamilyCreatesAndSelectsIt(): void
    {
        [$admin, $centre] = $this->makeScenario();

        $component = $this->component($centre, $admin);
        $component->set('addFamilyName', 'Informática y Comunicaciones');
        $component->call('addFamily');

        $this->em->clear();
        $families = $this->em->getRepository(ProfessionalFamily::class)->findAll();
        self::assertCount(1, $families);
        self::assertSame('Informática y Comunicaciones', $families[0]->getName());
    }

    public function testAddProgrammeRequiresSelectedFamily(): void
    {
        [$admin, $centre, $family] = $this->makeScenarioWithFamily();

        $component = $this->component($centre, $admin);
        $component->call('selectFamily', ['id' => $family->getId()->toRfc4122()]);
        $component->set('addProgrammeName', 'DAW');
        $component->call('addProgramme');

        $this->em->clear();
        $programmes = $this->em->getRepository(Programme::class)->findAll();
        self::assertCount(1, $programmes);
        self::assertSame('DAW', $programmes[0]->getName());
    }

    public function testAddLevelAndGroup(): void
    {
        [$admin, $centre, $family] = $this->makeScenarioWithFamily();
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($centre->getActiveAcademicYear());
        $this->persist($programme);
        $this->flush();

        $component = $this->component($centre, $admin);
        $component->call('selectFamily', ['id' => $family->getId()->toRfc4122()]);
        $component->call('selectProgramme', ['id' => $programme->getId()->toRfc4122()]);
        $component->set('addLevelName', 'Primer curso');
        $component->call('addLevel');

        $this->em->clear();
        $levels = $this->em->getRepository(ProgrammeYear::class)->findAll();
        self::assertCount(1, $levels);
        $levelId = $levels[0]->getId()->toRfc4122();

        // Reload component state and add a group under the new level.
        $component = $this->component($centre, $admin);
        $component->call('selectFamily', ['id' => $family->getId()->toRfc4122()]);
        $component->call('selectProgramme', ['id' => $programme->getId()->toRfc4122()]);
        $component->call('selectLevel', ['id' => $levelId]);
        $component->set('addGroupName', 'DAW1A');
        $component->call('addGroup');

        $this->em->clear();
        $groups = $this->em->getRepository(Group::class)->findAll();
        self::assertCount(1, $groups);
        self::assertSame('DAW1A', $groups[0]->getName());
    }

    // ── Inline edit / delete ────────────────────────────────────────────────────

    public function testSaveDetailRenamesSelectedGroup(): void
    {
        [$admin, $centre, $family, $programme, $level, $group] = $this->makeFullScenario();

        $component = $this->component($centre, $admin);
        $this->selectChain($component, $family, $programme, $level, $group);
        $component->set('editName', 'DAW1B');
        $component->call('saveDetail');

        $this->em->clear();
        $updated = $this->em->find(Group::class, $group->getId());
        self::assertSame('DAW1B', $updated->getName());
    }

    public function testSaveDetailWithEmptyNameShowsError(): void
    {
        [$admin, $centre, $family, $programme, $level, $group] = $this->makeFullScenario();

        $component = $this->component($centre, $admin);
        $this->selectChain($component, $family, $programme, $level, $group);
        $component->set('editName', '');
        $component->call('saveDetail');

        $this->em->clear();
        $updated = $this->em->find(Group::class, $group->getId());
        self::assertSame('DAW1A', $updated->getName());
    }

    public function testDeleteSelectedRemovesGroup(): void
    {
        [$admin, $centre, $family, $programme, $level, $group] = $this->makeFullScenario();
        $groupId = $group->getId();

        $component = $this->component($centre, $admin);
        $this->selectChain($component, $family, $programme, $level, $group);
        $component->call('deleteSelected');

        $this->em->clear();
        self::assertNull($this->em->find(Group::class, $groupId));
    }

    // ── Inline staff assignment ─────────────────────────────────────────────────

    public function testSetGroupTutorsAndTeachers(): void
    {
        [$admin, $centre, $family, $programme, $level, $group] = $this->makeFullScenario();
        $year = $centre->getActiveAcademicYear();

        $tutor   = $this->makeYearTeacher($year, 'tutor.1', 'Luisa', 'Gomez');
        $teacher = $this->makeYearTeacher($year, 'docente.1', 'Pedro', 'Ruiz');
        $this->persist($tutor, $teacher);
        $this->flush();

        $component = $this->component($centre, $admin);
        $this->selectChain($component, $family, $programme, $level, $group);
        $component->call('setGroupTutors', ['ids' => [$tutor->getId()->toRfc4122()]]);
        $component->call('setGroupTeachers', ['ids' => [$teacher->getId()->toRfc4122()]]);

        $this->em->clear();
        $updated = $this->em->find(Group::class, $group->getId());
        self::assertCount(1, $updated->getTutors());
        self::assertCount(1, $updated->getTeachers());
    }

    public function testSetFamilyHead(): void
    {
        [$admin, $centre, $family] = $this->makeScenarioWithFamily();
        $year = $centre->getActiveAcademicYear();
        $head = $this->makeYearTeacher($year, 'jefe.1', 'Marta', 'Díaz');
        $this->persist($head);
        $this->flush();

        $component = $this->component($centre, $admin);
        $component->call('selectFamily', ['id' => $family->getId()->toRfc4122()]);
        $component->call('setFamilyHead', ['teacherId' => $head->getId()->toRfc4122()]);

        $this->em->clear();
        $updated = $this->em->find(ProfessionalFamily::class, $family->getId());
        self::assertNotNull($updated->getHead());
        self::assertSame($head->getId()->toRfc4122(), $updated->getHead()->getId()->toRfc4122());
    }

    // ── Access control ──────────────────────────────────────────────────────────

    public function testNonAdminCannotAddFamily(): void
    {
        [, $centre] = $this->makeScenario();
        $outsider = (new Teacher(new PersonName('Sin', 'Permisos')))->setUsername('outsider.1');
        $this->persist($outsider);
        $this->flush();

        $component = $this->component($centre, $outsider);

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $component->set('addFamilyName', 'No debería crearse');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function component(EducationalCentre $centre, Teacher $actor): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        return $this->createLiveComponent(
            'Admin:ProfessionalFamilyListComponent',
            ['centre' => $centre],
            $this->client,
        )->actingAs($actor);
    }

    private function selectChain(
        \Symfony\UX\LiveComponent\Test\TestLiveComponent $component,
        ProfessionalFamily $family,
        Programme $programme,
        ProgrammeYear $level,
        Group $group,
    ): void {
        $component->call('selectFamily', ['id' => $family->getId()->toRfc4122()]);
        $component->call('selectProgramme', ['id' => $programme->getId()->toRfc4122()]);
        $component->call('selectLevel', ['id' => $level->getId()->toRfc4122()]);
        $component->call('selectGroup', ['id' => $group->getId()->toRfc4122()]);
    }

    private function makeYearTeacher(AcademicYear $year, string $username, string $first, string $last): Teacher
    {
        return (new Teacher(new PersonName($first, $last)))
            ->setUsername($username)
            ->addAcademicYear($year);
    }

    /** @return array{0: Teacher, 1: EducationalCentre} */
    private function makeScenario(): array
    {
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.1')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        return [$admin, $centre];
    }

    /** @return array{0: Teacher, 1: EducationalCentre, 2: ProfessionalFamily} */
    private function makeScenarioWithFamily(): array
    {
        [$admin, $centre] = $this->makeScenario();
        $family = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($centre->getActiveAcademicYear());
        $this->persist($family);
        $this->flush();

        return [$admin, $centre, $family];
    }

    /** @return array{0: Teacher, 1: EducationalCentre, 2: ProfessionalFamily, 3: Programme, 4: ProgrammeYear, 5: Group} */
    private function makeFullScenario(): array
    {
        [$admin, $centre, $family] = $this->makeScenarioWithFamily();
        $year      = $centre->getActiveAcademicYear();
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level     = (new ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $group     = (new Group())->setName('DAW1A')->setProgrammeYear($level);
        $this->persist($programme, $level, $group);
        $this->flush();

        return [$admin, $centre, $family, $programme, $level, $group];
    }
}
