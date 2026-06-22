<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Ensures that write operations return 403 when an admin is viewing a
 * non-active academic year, and that write-action buttons are absent from
 * the corresponding index pages in that mode.
 */
class PastYearGuardTest extends ControllerTestCase
{
    // ── CentreTeacherController ───────────────────────────────────────────────

    #[DataProvider('provideCentreTeacherWriteRoutes')]
    public function testCentreTeacherWriteReturns403WhenViewingPastYear(string $method, string $pathSuffix): void
    {
        [$admin, $centre, , $pastYear] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $this->client->request($method, '/admin/centros/' . $centre->getId()->toRfc4122() . $pathSuffix);

        self::assertResponseStatusCodeSame(403);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideCentreTeacherWriteRoutes(): iterable
    {
        // Since the 403 guard fires before entity lookups, fake entity UUIDs suffice.
        $fakeId = '00000000-0000-0000-0000-000000000001';

        yield 'add POST'                       => ['POST', '/docentes-curso/a%C3%B1adir'];
        yield 'import GET'                     => ['GET',  '/docentes-curso/importar'];
        yield 'import_assignments GET'         => ['GET',  '/docentes-curso/importar-asignaciones'];
        yield 'register GET'                   => ['GET',  '/docentes-curso/registrar'];
        yield 'edit GET'                       => ['GET',  "/docentes-curso/{$fakeId}/editar"];
        yield 'remove POST'                    => ['POST', "/docentes-curso/{$fakeId}/quitar"];
    }

    public function testCentreTeacherIndexHidesWriteButtonsWhenViewingPastYear(): void
    {
        [$admin, $centre, $activeYear, $pastYear] = $this->makeCentreWithTwoYears();
        $teacher = (new Teacher(new PersonName('Test', 'Docente')))->setUsername('teacher.past.1');
        $activeYear->addTeacher($teacher);
        $this->persist($teacher);
        $this->flush();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $crawler = $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/docentes-curso');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="/importar"]');
        self::assertSelectorNotExists('a[href*="/registrar"]');
        self::assertSelectorNotExists('form[action*="/a%C3%B1adir"], form[action*="/añadir"]');
    }

    // ── StudentController ─────────────────────────────────────────────────────

    #[DataProvider('provideStudentWriteRoutes')]
    public function testStudentWriteReturns403WhenViewingPastYear(string $method, string $pathSuffix): void
    {
        [$admin, $centre, , $pastYear] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $this->client->request($method, '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes' . $pathSuffix);

        self::assertResponseStatusCodeSame(403);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideStudentWriteRoutes(): iterable
    {
        $fakeId = '00000000-0000-0000-0000-000000000001';

        yield 'new GET'      => ['GET',  '/nuevo'];
        yield 'edit GET'     => ['GET',  "/{$fakeId}/editar"];
        yield 'import GET'   => ['GET',  '/importar'];
        yield 'delete POST'  => ['POST', "/{$fakeId}/eliminar"];
    }

    public function testStudentIndexHidesWriteButtonsWhenViewingPastYear(): void
    {
        [$admin, $centre, , $pastYear] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $crawler = $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="/nuevo"]');
        self::assertSelectorNotExists('a[href*="/importar"]');
    }

    // ── ProfessionalFamilyController ──────────────────────────────────────────

    #[DataProvider('provideFamilyWriteRoutes')]
    public function testFamilyWriteReturns403WhenViewingPastYear(string $method, string $pathSuffix): void
    {
        [$admin, $centre, , $pastYear] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $this->client->request($method, '/admin/centros/' . $centre->getId()->toRfc4122() . '/familias' . $pathSuffix);

        self::assertResponseStatusCodeSame(403);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideFamilyWriteRoutes(): iterable
    {
        yield 'import GET' => ['GET', '/importar'];
    }

    public function testFamilyIndexHidesWriteButtonsAndKeepsExportWhenViewingPastYear(): void
    {
        [$admin, $centre, , $pastYear] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $crawler = $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/familias');

        self::assertResponseIsSuccessful();
        // Write button (import) hidden
        self::assertSelectorNotExists('a[href*="/importar"]');
        // Export (read-only) remains visible
        self::assertSelectorExists('a[href*="/exportar"]');
    }

    // ── StayController ────────────────────────────────────────────────────────

    public function testNewStayReturns403WhenViewingPastYear(): void
    {
        [$admin, $centre, , $pastYear] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $this->client->request('GET', '/estancias/nueva');

        self::assertResponseStatusCodeSame(403);
    }

    public function testStayIndexHidesNewButtonWhenViewingPastYear(): void
    {
        [$admin, $centre, $activeYear, $pastYear] = $this->makeCentreWithTwoYears();
        $fam  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($activeYear);
        $prog = (new Programme())->setName('DAM')->setAcademicYear($activeYear)->setProfessionalFamily($fam);
        $this->persist($fam, $prog);
        $this->flush();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $crawler = $this->client->request('GET', '/estancias');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/estancias/nueva"]');
    }

    // ── DashboardController ───────────────────────────────────────────────────

    public function testDashboardHidesWriteActionsWhenViewingPastYear(): void
    {
        [$admin, $centre, $activeYear, $pastYear] = $this->makeCentreWithTwoYears();
        $fam  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($activeYear);
        $prog = (new Programme())->setName('DAM')->setAcademicYear($activeYear)->setProfessionalFamily($fam);
        $this->persist($fam, $prog);
        $this->flush();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Nueva estancia', $crawler->html());
        self::assertStringNotContainsString('Importar estudiantes', $crawler->html());
    }

    public function testDashboardShowsViewedYearNameInAmberWhenViewingPastYear(): void
    {
        [$admin, $centre, , $pastYear] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);
        $this->viewPastYear($pastYear);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($pastYear->getName(), $crawler->html());
    }

    // ── Sanity: same routes succeed when NOT viewing a past year ──────────────

    public function testCentreTeacherWriteSucceedsWhenViewingActiveYear(): void
    {
        [$admin, $centre] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);
        // No viewPastYear() call — session has no year override

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/docentes-curso/registrar');

        self::assertResponseIsSuccessful();
    }

    public function testStudentWriteSucceedsWhenViewingActiveYear(): void
    {
        [$admin, $centre] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes/nuevo');

        self::assertResponseIsSuccessful();
    }

    public function testFamilyWriteSucceedsWhenViewingActiveYear(): void
    {
        [$admin, $centre] = $this->makeCentreWithTwoYears();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/familias/importar');

        self::assertResponseIsSuccessful();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns a centre admin, a centre, the active year, and a past (non-active) year.
     *
     * @return array{Teacher, EducationalCentre, AcademicYear, AcademicYear}
     */
    private function makeCentreWithTwoYears(): array
    {
        static $seq = 0;
        $seq++;

        $admin      = (new Teacher(new PersonName('Admin', 'Guard')))->setUsername("admin.guard.{$seq}")->setAdmin(true);
        $centre     = (new EducationalCentre())->setCode("4100{$seq}999")->setName("IES Guard {$seq}")->setCity('Sevilla');
        $activeYear = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $pastYear   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        $this->persist($admin, $centre, $activeYear, $pastYear);
        $centre->setActiveAcademicYear($activeYear);
        $centre->addAdmin($admin);
        $this->flush();

        return [$admin, $centre, $activeYear, $pastYear];
    }
}
