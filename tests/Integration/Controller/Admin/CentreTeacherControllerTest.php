<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CentreTeacherControllerTest extends ControllerTestCase
{
    // ── index ─────────────────────────────────────────────────────────────────

    public function testIndexIsAccessibleToAdmin(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/docentes-curso');

        self::assertResponseIsSuccessful();
    }

    public function testIndexIsAccessibleToEquipoDirectivo(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $directivo = $this->makeTeacher('directivo.1');
        $this->persist($admin, $centre, $year, $directivo);
        $centre->addAdmin($directivo);
        $this->flush();
        $this->loginAs($directivo);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/docentes-curso');

        self::assertResponseIsSuccessful();
    }

    public function testIndexDeniesNonAdmin(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/docentes-curso');

        self::assertResponseStatusCodeSame(403);
    }

    // ── add ───────────────────────────────────────────────────────────────────

    public function testAddKnownUsernameAddsTeacherToYearAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $teacher);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/docentes-curso');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/a%C3%B1adir', [
            '_token'   => $token,
            'username' => 'teacher.1',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/docentes-curso', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testAddUnknownUsernameRedirectsToRegister(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/docentes-curso');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/a%C3%B1adir', [
            '_token'   => $token,
            'username' => 'desconocido',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/registrar', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testAddWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/a%C3%B1adir', [
            '_token'   => 'token-invalido',
            'username' => 'teacher.1',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── import ────────────────────────────────────────────────────────────────

    public function testImportGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/docentes-curso/importar');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testImportPostWithValidCsvCreatesTeachersAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/docentes-curso/importar');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $tmpFile = tempnam(sys_get_temp_dir(), 'nexo_test_');
        file_put_contents($tmpFile, "\"Empleado/a\",\"Usuario IdEA\"\n\"Garcia, Juan\",\"juan.garcia\"\n");
        $file = new UploadedFile($tmpFile, 'import.csv', 'text/csv', null, true);

        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/importar', [
            '_token' => $token,
        ], ['csv' => $file]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/docentes-curso', (string) $this->client->getResponse()->headers->get('Location'));

        @unlink($tmpFile);
    }

    public function testImportPostWithNoFileRendersFormAgain(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/docentes-curso/importar');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/importar', [
            '_token' => $token,
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testImportPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/importar', [
            '_token' => 'token-invalido',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── importAssignments ─────────────────────────────────────────────────────

    public function testImportAssignmentsGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/docentes-curso/importar-asignaciones');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testImportAssignmentsPostWithValidCsvRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/docentes-curso/importar-asignaciones');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $tmpFile = tempnam(sys_get_temp_dir(), 'nexo_test_');
        file_put_contents($tmpFile, "\"Unidad\",\"Profesor/a\"\n\"DAW1A\",\"Garcia, Juan\"\n");
        $file = new UploadedFile($tmpFile, 'assignments.csv', 'text/csv', null, true);

        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/importar-asignaciones', [
            '_token' => $token,
        ], ['csv' => $file]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/docentes-curso', (string) $this->client->getResponse()->headers->get('Location'));

        @unlink($tmpFile);
    }

    public function testImportAssignmentsPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/importar-asignaciones', [
            '_token' => 'token-invalido',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── register ──────────────────────────────────────────────────────────────

    public function testRegisterGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/docentes-curso/registrar');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testRegisterPostCreatesTeacherAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/docentes-curso/registrar');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/registrar', [
            '_token'      => $token,
            'first_name'  => 'Juan',
            'last_name'   => 'Garcia',
            'username'    => 'juan.garcia',
            'email'       => '',
            'password'    => 'secret123',
            'auth_method' => 'local',
            'active'      => '1',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/docentes-curso', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testRegisterPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/registrar', [
            '_token'     => 'token-invalido',
            'first_name' => 'Juan',
            'last_name'  => 'Garcia',
            'username'   => 'juan.garcia',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRegisterPostWithEmptyFirstNameRendersFormAgain(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/docentes-curso/registrar');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/registrar', [
            '_token'      => $token,
            'first_name'  => '',
            'last_name'   => 'Garcia',
            'username'    => 'juan.garcia',
            'password'    => 'secret123',
            'auth_method' => 'local',
        ]);

        self::assertResponseIsSuccessful();
    }

    // ── remove ────────────────────────────────────────────────────────────────

    public function testRemoveRemovesTeacherFromYearAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $teacher);
        $centre->setActiveAcademicYear($year);
        $year->addTeacher($teacher);
        $this->flush();
        $this->loginAs($admin);

        $centreId  = $centre->getId()->toRfc4122();
        $teacherId = $teacher->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/admin/centros/' . $centreId . '/docentes-curso');
        $token     = $crawler->filter('form[action$="/' . $teacherId . '/quitar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/' . $teacherId . '/quitar', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/docentes-curso', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testRemoveWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $teacher);
        $centre->setActiveAcademicYear($year);
        $year->addTeacher($teacher);
        $this->flush();
        $this->loginAs($admin);

        $centreId  = $centre->getId()->toRfc4122();
        $teacherId = $teacher->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/docentes-curso/' . $teacherId . '/quitar', [
            '_token' => 'token-invalido',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{0: Teacher, 1: EducationalCentre, 2: AcademicYear} */
    private function makeCentreWithYear(): array
    {
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.1')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        return [$admin, $centre, $year];
    }

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }
}
