<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

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
use Symfony\Component\HttpFoundation\File\UploadedFile;

class StudentControllerTest extends ControllerTestCase
{
    // ── index ─────────────────────────────────────────────────────────────────

    public function testIndexIsAccessibleToAdmin(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes');

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

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes');

        self::assertResponseIsSuccessful();
    }

    public function testIndexDeniesNonAdmin(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes');

        self::assertResponseStatusCodeSame(403);
    }

    // ── new ───────────────────────────────────────────────────────────────────

    public function testNewGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes/nuevo');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testNewPostCreatesStudentAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/nuevo');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/nuevo', [
            '_token'    => $token,
            'firstName' => 'Ana',
            'lastName'  => 'Martinez',
            'studentId' => '2024-001',
            'details'   => '',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estudiantes', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testNewPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/nuevo', [
            '_token'    => 'token-invalido',
            'firstName' => 'Ana',
            'lastName'  => 'Martinez',
            'studentId' => '2024-001',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNewPostWithEmptyFirstNameRendersFormAgain(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/nuevo');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/nuevo', [
            '_token'    => $token,
            'firstName' => '',
            'lastName'  => 'Martinez',
            'studentId' => '2024-001',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testNewPostWithDuplicateStudentIdRendersFormAgain(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $student = $this->makeStudent('2024-001');
        $this->persist($admin, $centre, $year, $student);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/nuevo');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/nuevo', [
            '_token'    => $token,
            'firstName' => 'Otro',
            'lastName'  => 'Alumno',
            'studentId' => '2024-001',
        ]);

        self::assertResponseIsSuccessful();
    }

    // ── edit ──────────────────────────────────────────────────────────────────

    public function testEditGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $student = $this->makeStudent('2024-001');
        $this->persist($admin, $centre, $year, $student);
        $this->loginAs($admin);

        $centreId  = $centre->getId()->toRfc4122();
        $studentId = $student->getId()->toRfc4122();

        $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/' . $studentId . '/editar');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditPostSavesChangesAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $student = $this->makeStudent('2024-001');
        $this->persist($admin, $centre, $year, $student);
        $this->loginAs($admin);

        $centreId  = $centre->getId()->toRfc4122();
        $studentId = $student->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/' . $studentId . '/editar');
        $token     = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/' . $studentId . '/editar', [
            '_token'    => $token,
            'firstName' => 'Modificado',
            'lastName'  => 'Apellido',
            'studentId' => '2024-001',
            'details'   => '',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        $updated = $this->em->find(Student::class, $student->getId());
        self::assertSame('Modificado', $updated->getName()->getFirstName());
    }

    public function testEditPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $student = $this->makeStudent('2024-001');
        $this->persist($admin, $centre, $year, $student);
        $this->loginAs($admin);

        $centreId  = $centre->getId()->toRfc4122();
        $studentId = $student->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/' . $studentId . '/editar', [
            '_token'    => 'token-invalido',
            'firstName' => 'Hack',
            'lastName'  => 'Attack',
            'studentId' => '2024-001',
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

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes/importar');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testImportPostWithValidCsvCreatesStudentsAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/importar');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $csv = implode("\n", [
            '"Estado Matrícula","Nº Id. Escolar","Primer apellido","Segundo apellido","Nombre","Unidad"',
            '"","2024-001","Martinez","Lopez","Ana","DAW1A"',
        ]);
        $tmpFile = tempnam(sys_get_temp_dir(), 'nexo_test_');
        file_put_contents($tmpFile, $csv);
        $file = new UploadedFile($tmpFile, 'students.csv', 'text/csv', null, true);

        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/importar', [
            '_token' => $token,
        ], ['csv' => $file]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estudiantes', (string) $this->client->getResponse()->headers->get('Location'));

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
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/importar');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/importar', [
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
        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/importar', [
            '_token' => 'token-invalido',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ── export ────────────────────────────────────────────────────────────────

    public function testExportReturnsCsvWithStudentData(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        [$family, $programme, $level, $group] = $this->makeGroupChain($year, 'DAW1A');
        $student = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $group->addStudent($student);
        $this->persist($admin, $centre, $year, $family, $programme, $level, $group, $student);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes/exportar');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');

        $content = $this->getStreamedContent();
        self::assertStringContainsString('2024-001', $content);
        self::assertStringContainsString('Martinez', $content);
        self::assertStringContainsString('DAW1A', $content);
    }

    public function testExportAppliesSearchFilter(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        [$family, $programme, $level, $group] = $this->makeGroupChain($year, 'DAW1A');
        $ana   = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $pedro = (new Student(new PersonName('Pedro', 'Sanchez')))->setStudentId('2024-002');
        $group->addStudent($ana);
        $group->addStudent($pedro);
        $this->persist($admin, $centre, $year, $family, $programme, $level, $group, $ana, $pedro);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes/exportar?search=martinez');

        self::assertResponseIsSuccessful();

        $content = $this->getStreamedContent();
        self::assertStringContainsString('Martinez', $content);
        self::assertStringNotContainsString('Sanchez', $content);
    }

    public function testExportAppliesGroupFilter(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        [$family, $programme, $level, $groupA] = $this->makeGroupChain($year, 'DAW1A');
        $groupB = (new Group())->setName('DAW1B')->setProgrammeYear($level);
        $ana    = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $pedro  = (new Student(new PersonName('Pedro', 'Sanchez')))->setStudentId('2024-002');
        $groupA->addStudent($ana);
        $groupB->addStudent($pedro);
        $this->persist($admin, $centre, $year, $family, $programme, $level, $groupA, $groupB, $ana, $pedro);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $url = '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes/exportar?groupId=' . $groupA->getId()->toRfc4122();
        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();

        $content = $this->getStreamedContent();
        self::assertStringContainsString('Martinez', $content);
        self::assertStringNotContainsString('Sanchez', $content);
    }

    public function testExportDeniesNonAdmin(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($admin, $centre, $year, $teacher);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($teacher);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/estudiantes/exportar');

        self::assertResponseStatusCodeSame(403);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function testDeleteStudentDeletesEntityAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $student = $this->makeStudent('2024-001');
        $this->persist($admin, $centre, $year, $student);
        $this->loginAs($admin);

        $centreId  = $centre->getId()->toRfc4122();
        $studentId = $student->getId()->toRfc4122();
        $crawler   = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/' . $studentId . '/editar');
        $token     = $crawler->filter('form[action$="/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/' . $studentId . '/eliminar', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/estudiantes', (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(Student::class, $student->getId()));
    }

    public function testDeleteWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeCentreWithYear();
        $student = $this->makeStudent('2024-001');
        $this->persist($admin, $centre, $year, $student);
        $this->loginAs($admin);

        $centreId  = $centre->getId()->toRfc4122();
        $studentId = $student->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/' . $studentId . '/eliminar', [
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

    private function makeStudent(string $studentId): Student
    {
        return (new Student(new PersonName('Test', 'Student')))->setStudentId($studentId);
    }

    /** @return array{0: ProfessionalFamily, 1: Programme, 2: ProgrammeYear, 3: Group} */
    private function makeGroupChain(AcademicYear $year, string $groupName): array
    {
        $family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level     = (new ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $group     = (new Group())->setName($groupName)->setProgrammeYear($level);

        return [$family, $programme, $level, $group];
    }
}
