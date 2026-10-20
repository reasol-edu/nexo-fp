<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\CentreSettingValue;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\SettingDefinition;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Entity\Workcenter;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\DomCrawler\Crawler;

/** El inicio: qué ve cada perfil, en qué orden y con qué gravedad. */
class DashboardControllerTest extends ControllerTestCase
{
    private EducationalCentre $centre;
    private AcademicYear $year;
    private ProfessionalFamily $family;
    private int $seq = 0;

    // ── Acceso ───────────────────────────────────────────────────────────────

    public function testDashboardRedirectsAnonymousUserToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testDashboardIsAccessibleToAuthenticatedTeacher(): void
    {
        // TenantContextSubscriber redirects to /centro unless a centre is selected.
        $centre  = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $teacher = (new Teacher(new PersonName('Ana', 'Lopez')))->setUsername('ana.lopez');
        $this->persist($centre, $teacher);
        $centre->addAdmin($teacher);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
    }

    // ── Cabecera con el ámbito ───────────────────────────────────────────────

    public function testTheHeaderStatesTheScopeOfWhatIsShown(): void
    {
        [$admin, $stay] = $this->adminWithStay();
        $this->loginAs($admin, $this->centre);

        $html = $this->client->request('GET', '/')->html();

        self::assertStringContainsString('Administras el centro', $html);
        self::assertStringContainsString('IES Test', $html);
        self::assertStringContainsString($this->year->getName(), $html);
    }

    // ── Atención ahora ───────────────────────────────────────────────────────

    public function testAttentionNowListsActionableSentencesWithTheDeadlineAndALinkToTheFilter(): void
    {
        [$admin, $stay, $level] = $this->adminWithStay();
        $this->enrol($stay, $level);
        $this->loginAs($admin, $this->centre);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $attention = $crawler->filter('#attention-title')->ancestors()->filter('section')->first();
        $text      = $attention->text();
        self::assertStringContainsString('Atención ahora', $text);
        self::assertStringContainsString('1 estudiante sin puesto', $text);
        self::assertStringContainsString('empezó hace 30 días', $text);
        self::assertStringContainsString('Crítico', $text, 'la gravedad va con texto, no solo con color');
        self::assertCount(1, $attention->filter('a[href$="?filtro=sin-puesto"]'));
        self::assertStringContainsString($stay->getName(), $text);
    }

    public function testStudentsWhoDoTheFfeoeButHaveNoNussGetTheirOwnTask(): void
    {
        [$admin, $stay, $level] = $this->adminWithStay();
        $student = $this->enrol($stay, $level);
        $this->persist(new \App\Entity\StudentYearProfile($student, $this->year));
        $this->em->clear();
        $profile = self::getContainer()->get(\App\Repository\StudentYearProfileRepository::class)
            ->findByStudentAndYear(
                $this->em->getReference(\App\Entity\Student::class, $student->getId()),
                $this->em->getReference(AcademicYear::class, $this->year->getId()),
            );
        $profile->setHasNuss(false);
        $this->flush();
        $this->loginAs($this->em->getReference(Teacher::class, $admin->getId()), $this->em->getReference(EducationalCentre::class, $this->centre->getId()));

        $attention = $this->client->request('GET', '/')->filter('#attention-title')->ancestors()->filter('section')->first();

        self::assertStringContainsString('1 estudiante que realiza la FFEOE sin NUSS', $attention->text());
        self::assertCount(1, $attention->filter('a[href$="?filtro=sin-nuss"]'));
    }

    public function testAttentionNowComesBeforeTheStaysAndTheProcess(): void
    {
        [$admin, $stay, $level] = $this->adminWithStay();
        $this->enrol($stay, $level);
        $this->loginAs($admin, $this->centre);

        $html = $this->client->request('GET', '/')->html();

        $attention = strpos($html, 'id="attention-title"');
        $stays     = strpos($html, 'id="stays-title"');
        $funnel    = strpos($html, 'id="funnel-title"');
        self::assertNotFalse($attention);
        self::assertNotFalse($stays);
        self::assertNotFalse($funnel);
        self::assertLessThan($stays, $attention);
        self::assertLessThan($funnel, $stays);
    }

    public function testNoUrgenciesIsShownWhenThereIsNothingPending(): void
    {
        [$admin] = $this->adminWithStay();   // una estancia sin estudiantes ni puestos
        $this->loginAs($admin, $this->centre);

        $html = $this->client->request('GET', '/')->html();

        self::assertStringContainsString('Sin urgencias', $html);
        self::assertStringNotContainsString('Todo al día', $html);
    }

    public function testTheUrgencyThresholdsAreConfigurable(): void
    {
        [$admin, , $level] = $this->adminWithStay(startOffset: 20, endOffset: 110);
        $this->enrol($this->lastStay, $level);
        $this->loginAs($admin, $this->centre);

        // Con los umbrales por defecto (7 y 21 días) empieza dentro de 20 días: «Próximo».
        $before = $this->client->request('GET', '/')->filter('#attention-title')->ancestors()->filter('section')->first()->text();
        self::assertStringContainsString('Próximo', $before);
        self::assertStringNotContainsString('Crítico', $before);

        // El centro lo cambia a 30 días: la misma tarea pasa a «Crítico».
        $this->reattach();
        $definition = $this->em->getRepository(SettingDefinition::class)->findOneBy(['key' => 'attention.urgent_days']);
        $this->persist((new CentreSettingValue())->setDefinition($definition)->setCentre($this->centre)->setValue('30')->setLocked(false));

        $after = $this->client->request('GET', '/')->filter('#attention-title')->ancestors()->filter('section')->first()->text();
        self::assertStringContainsString('Crítico', $after);
    }

    // ── Estados del curso ────────────────────────────────────────────────────

    public function testFinishedStaysDoNotAppearInTheTasksOrTheCards(): void
    {
        [$admin, $stay, $level] = $this->adminWithStay(startOffset: -120, endOffset: -30);
        $this->enrol($stay, $level);
        $this->loginAs($admin, $this->centre);

        $html = $this->client->request('GET', '/')->html();

        self::assertStringNotContainsString('1 estudiante sin puesto', $html);
        self::assertStringNotContainsString('id="stays-title"', $html);
        self::assertStringContainsString('1 estancia finalizada', $html);
    }

    public function testViewingAPastYearShowsOnlyItsSummary(): void
    {
        [$admin, $stay, $level] = $this->adminWithStay();
        $this->enrol($stay, $level);
        $past = (new AcademicYear())->setName('2020-2021')->setEducationalCentre($this->centre);
        $this->persist($past);
        $this->loginAs($admin, $this->centre);
        $this->viewPastYear($past);

        $html = $this->client->request('GET', '/')->html();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('curso anterior', $html);
        self::assertStringNotContainsString('id="attention-title"', $html);
    }

    // ── Acciones ─────────────────────────────────────────────────────────────

    public function testTheAdminSeesASinglePrimaryAction(): void
    {
        [$admin] = $this->adminWithStay();
        $this->loginAs($admin, $this->centre);

        $html = $this->client->request('GET', '/')->html();

        self::assertStringContainsString('Nueva estancia', $html);
        self::assertStringNotContainsString('Importar estudiantes', $html);
        self::assertStringNotContainsString('Ver todas las estancias', $html);
    }

    // ── Sin responsabilidades ────────────────────────────────────────────────

    public function testSomeoneWithoutResponsibilitiesGetsAnExplanationInsteadOfAllClear(): void
    {
        $this->adminWithStay();
        $teacher = (new Teacher(new PersonName('Sin', 'Permisos')))->setUsername('teacher.noperm');
        $this->persist($teacher);
        $this->loginAs($teacher, $this->centre);

        $html = $this->client->request('GET', '/')->html();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Todavía no tienes responsabilidades', $html);
        self::assertStringNotContainsString('Sin urgencias', $html);
        self::assertStringNotContainsString('Todo al día', $html);
        self::assertStringNotContainsString('Nueva estancia', $html);
        self::assertStringNotContainsString('id="attention-title"', $html);
    }

    // ── Cobertura por enseñanza ──────────────────────────────────────────────

    public function testTheCoverageTableIsOnlyForTheAdministrationAndFamilyHeads(): void
    {
        [$admin, $stay, $level] = $this->adminWithStay();
        $this->enrol($stay, $level);
        $this->loginAs($admin, $this->centre);

        $crawler = $this->client->request('GET', '/');

        self::assertCount(1, $crawler->filter('#coverage-title'));
        self::assertStringContainsString('DAM', $crawler->filter('#coverage-title')->ancestors()->filter('section')->first()->text());
    }

    // ── Cada perfil ve solo lo suyo ──────────────────────────────────────────

    public function testAGroupTeacherSeesTheirStudentsAndNothingAboutFreePositions(): void
    {
        [, $stay, $level, $programme] = $this->adminWithStay();
        $group   = $this->group($level, 'DAM1A');
        $teacher = (new Teacher(new PersonName('Fernando', 'Ibáñez')))->setUsername('fernando.ibanez');
        $this->persist($teacher);
        $group->addTeacher($teacher);
        $mine  = $this->student($group, $stay);
        $other = $this->student($this->group($level, 'DAM1B'), $stay);
        $this->persist((new TrainingPosition())->setStay($stay), (new TrainingPosition())->setStay($stay));   // dos puestos libres
        $this->flush();
        $this->loginAs($teacher, $this->centre);

        $crawler = $this->client->request('GET', '/');
        $html    = $crawler->html();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('1 estudiante de DAM1A sin puesto', $html, 'solo los de su grupo');
        self::assertStringNotContainsString('puesto libre', $html);
        self::assertStringNotContainsString('Faltan', $html);
        self::assertStringNotContainsString('Falta 1 puesto', $html);
        self::assertStringNotContainsString('id="stays-title"', $html, 'no gestiona estancias: sin tarjetas');
        self::assertStringNotContainsString('id="funnel-title"', $html);
        self::assertStringContainsString('Mi grupo', $html);
        self::assertStringContainsString($mine->getName()->getFirstName(), $html);
        self::assertStringNotContainsString($other->getName()->getFirstName() . ' ' . $other->getName()->getLastName(), $html);
    }

    public function testACoordinatorOnlyCountsTheStudentsOfTheirProgrammeInASharedStay(): void
    {
        [$admin, $stay, $dawLevel, $daw] = $this->adminWithStay();
        $smr      = (new Programme())->setName('SMR')->setAcademicYear($this->year)->setProfessionalFamily($this->family);
        $smrLevel = (new ProgrammeYear())->setName('Primero SMR')->setProgramme($smr);
        $this->persist($smr, $smrLevel);
        $stay->addProgramme($smr);
        $coordinator = (new Teacher(new PersonName('Diego', 'Romero')))->setUsername('diego.romero');
        $this->persist($coordinator);
        $daw->addCoordinator($coordinator);
        foreach ([1, 2, 3] as $i) {
            $this->student($this->group($dawLevel, 'DAW' . $i), $stay);
        }
        foreach ([1, 2, 3, 4, 5] as $i) {
            $this->student($this->group($smrLevel, 'SMR' . $i), $stay);
        }
        $this->flush();
        $this->loginAs($coordinator, $this->centre);

        $text = $this->client->request('GET', '/')->filter('#attention-title')->ancestors()->filter('section')->first()->text();

        self::assertStringContainsString('3 estudiantes sin puesto', $text, 'solo los 3 de su enseñanza, no los 8 de la estancia');
        self::assertStringNotContainsString('8 estudiantes', $text);
    }

    public function testALiaisonOnlySeesTheirCompaniesAndNoCentreWideFigures(): void
    {
        [, $stay, $level] = $this->adminWithStay();
        $this->enrol($stay, $level);
        $liaison = (new Teacher(new PersonName('Beatriz', 'Alonso')))->setUsername('beatriz.alonso');
        $mine    = (new Company())->setName('Alfa S.L.')->setVatNumber('B1')->setCity('Sevilla')->setEducationalCentre($this->centre);
        $other   = (new Company())->setName('Beta S.L.')->setVatNumber('B2')->setCity('Sevilla')->setEducationalCentre($this->centre);
        $this->persist($liaison, $mine, $other);
        $mine->addLiaison($liaison);
        $mineWc  = (new Workcenter())->setName('Sede Alfa')->setCity('Sevilla')->setCompany($mine);
        $otherWc = (new Workcenter())->setName('Sede Beta')->setCity('Sevilla')->setCompany($other);
        $this->persist($mineWc, $otherWc);
        $this->persist((new TrainingPosition())->setStay($stay)->setWorkcenter($mineWc), (new TrainingPosition())->setStay($stay)->setWorkcenter($otherWc));
        $this->loginAs($liaison, $this->centre);

        $html = $this->client->request('GET', '/')->html();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Alfa S.L.: 1 puesto y ningún trabajador registrado', $html);
        self::assertStringContainsString('Mis empresas', $html);
        self::assertStringNotContainsString('Beta S.L.', $html);
        self::assertStringNotContainsString('estudiante sin puesto', $html, 'los estudiantes no son suyos');
        self::assertStringNotContainsString('id="stays-title"', $html);
    }

    public function testATutorSeesTheirTutorshipsWithTheNextAction(): void
    {
        [, $stay, $level] = $this->adminWithStay(startOffset: 3, endOffset: 93);
        $group = $this->group($level, 'DAM1A');
        $tutor = (new Teacher(new PersonName('Laura', 'Gómez')))->setUsername('laura.gomez');
        $this->persist($tutor);
        $student = $this->student($group, $stay);
        $company = (new Company())->setName('Alfa S.L.')->setVatNumber('B1')->setCity('Sevilla')->setEducationalCentre($this->centre);
        $this->persist($company);
        $workcenter = (new Workcenter())->setName('Sede Alfa')->setCity('Sevilla')->setCompany($company);
        $this->persist($workcenter);
        $this->persist(
            (new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter)->setStudent($student)->setAcademicTutor($tutor)
                ->setState(TrainingPositionState::DONE)->setSigned(false),
        );
        $this->loginAs($tutor, $this->centre);

        $html = $this->client->request('GET', '/')->html();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Mis tutorías', $html);
        self::assertStringContainsString($student->getName()->getFirstName(), $html);
        self::assertStringContainsString('Alfa S.L. — Sede Alfa', $html);
        self::assertStringContainsString('Firma pendiente', $html);
        self::assertStringContainsString('1 de tus convenios sin firmar', $html);
        self::assertStringContainsString('Crítico', $html);
    }

    // ── Consultas: constantes, no crecen con las estancias ───────────────────

    public function testTheNumberOfQueriesDoesNotGrowWithTheNumberOfStays(): void
    {
        [$admin, $stay, $level] = $this->adminWithStay();
        $this->populate($stay, $level, 2);
        $this->loginAs($admin, $this->centre);
        $few = $this->queriesForTheDashboard();

        $this->reattach();
        $level = $this->em->getReference(ProgrammeYear::class, $level->getId());
        for ($i = 0; $i < 6; $i++) {
            $extra = $this->newStay($level->getProgramme(), 'FFEOE extra ' . $i);
            $this->populate($extra, $level, 3);
        }
        $many = $this->queriesForTheDashboard();

        self::assertGreaterThan(0, $few);
        self::assertSame($few, $many, 'con 7 estancias y decenas de puestos se lanzan las mismas consultas que con 1');
        self::assertLessThan(40, $many, 'presupuesto de consultas del inicio (incluye autenticación y centro)');
    }

    // ── Ayudantes ────────────────────────────────────────────────────────────

    private ?Stay $lastStay = null;

    /** @return array{Teacher, Stay, ProgrammeYear, Programme} */
    private function adminWithStay(int $startOffset = -30, int $endOffset = 60): array
    {
        $this->centre = (new EducationalCentre())->setCode('41' . random_int(100000, 999999))->setName('IES Test')->setCity('Sevilla');
        $this->year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($this->centre);
        $this->family = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($this->year);
        $programme    = (new Programme())->setName('DAM')->setAcademicYear($this->year)->setProfessionalFamily($this->family);
        $level        = (new ProgrammeYear())->setName('Primero')->setProgramme($programme);
        $admin        = (new Teacher(new PersonName('Ana', 'Admin')))->setUsername('admin.' . ++$this->seq);
        $this->persist($this->centre, $this->year, $this->family, $programme, $level, $admin);
        $this->centre->setActiveAcademicYear($this->year);
        $this->centre->addAdmin($admin);
        $this->flush();

        $stay = $this->newStay($programme, 'FFEOE DAM', $startOffset, $endOffset);

        return [$admin, $stay, $level, $programme];
    }

    private function newStay(Programme $programme, string $name, int $startOffset = -30, int $endOffset = 60): Stay
    {
        $stay = (new Stay())
            ->setName($name)
            ->setAcademicYear($this->year)
            ->addProgramme($programme)
            ->setStartDate(new \DateTimeImmutable(\sprintf('%+d days', $startOffset)))
            ->setEndDate(new \DateTimeImmutable(\sprintf('%+d days', $endOffset)));
        $this->persist($stay);

        return $this->lastStay = $stay;
    }

    private function group(ProgrammeYear $level, string $name): Group
    {
        $group = (new Group())->setName($name)->setProgrammeYear($level);
        $this->persist($group);

        return $group;
    }

    /** Un estudiante del grupo matriculado en la estancia. */
    private function student(Group $group, Stay $stay): Student
    {
        $n       = ++$this->seq;
        $student = (new Student(new PersonName('Alumno' . $n, 'Apellido' . $n)))->setStudentId('2025-' . $n);
        $group->addStudent($student);
        $stay->addStudent($student);
        $this->persist($student);

        return $student;
    }

    private function enrol(Stay $stay, ProgrammeYear $level): Student
    {
        return $this->student($this->group($level, 'G' . ++$this->seq), $stay);
    }

    /** Matricula N estudiantes, deja la mitad con puesto y añade puestos libres. */
    private function populate(Stay $stay, ProgrammeYear $level, int $students): void
    {
        $group = $this->group($level, 'P' . ++$this->seq);
        for ($i = 0; $i < $students; $i++) {
            $student = $this->student($group, $stay);
            if ($i % 2 === 0) {
                $this->persist((new TrainingPosition())->setStay($stay)->setStudent($student)->setState(TrainingPositionState::DONE)->setSigned(false));
            }
        }
        $this->persist((new TrainingPosition())->setStay($stay));
    }

    /** Las peticiones del cliente vacían el gestor de entidades: se vuelven a referenciar las entidades base. */
    private function reattach(): void
    {
        $this->centre = $this->em->getReference(EducationalCentre::class, $this->centre->getId());
        $this->year   = $this->em->getReference(AcademicYear::class, $this->year->getId());
        $this->family = $this->em->getReference(ProfessionalFamily::class, $this->family->getId());
    }

    private function queriesForTheDashboard(): int
    {
        $this->client->enableProfiler();
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $profile = $this->client->getProfile();
        self::assertNotFalse($profile, 'el perfilador debe estar activo en el test');

        /** @var \Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector $db */
        $db = $profile->getCollector('db');

        return $db->getQueryCount();
    }
}
