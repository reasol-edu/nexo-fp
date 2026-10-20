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
use App\Entity\StudentYearProfile;
use App\Entity\Teacher;
use App\Repository\StudentYearProfileRepository;
use App\Tests\Integration\ControllerTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Ficha del estudiante en el curso: quién la edita, validación, y su carga desde la importación. */
class StudentProfileControllerTest extends ControllerTestCase
{
    private EducationalCentre $centre;
    private AcademicYear $year;
    private Programme $programme;
    private Group $group;
    private Student $student;
    private string $studentId;
    private int $seq = 0;

    // ── Quién edita ──────────────────────────────────────────────────────────

    public function testCentreAdminCanOpenTheForm(): void
    {
        $this->scene();
        $admin = $this->teacher('directivo');
        $this->centre->addAdmin($admin);
        $this->flush();
        $this->loginAs($admin, $this->centre);

        $crawler = $this->client->request('GET', $this->url(), server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('input[name="has_nuss"]'), 'selector doble Sí/No');
        self::assertCount(2, $crawler->filter('input[name="takes_ffeoe"]'));
        self::assertCount(2, $crawler->filter('input[name="has_car"]'));
        self::assertCount(2, $crawler->filter('input[name="brings_company"]'));
        self::assertCount(6, $crawler->filter('input[name="attitude"]'));
        self::assertCount(1, $crawler->filter('input[name="residence_city"]'));
        self::assertCount(1, $crawler->filter('input[name="preferred_city"]'));
        self::assertStringNotContainsString('<html', (string) $this->client->getResponse()->getContent(), 'solo el formulario, sin layout');
    }

    public function testTheFormStartsWithTheDefaultsMarked(): void
    {
        $this->scene();
        $admin = $this->teacher('directivo');
        $this->centre->addAdmin($admin);
        $this->flush();
        $this->loginAs($admin, $this->centre);

        $crawler = $this->client->request('GET', $this->url(), server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        $checked = static fn (string $name): array => array_map(
            static fn (\DOMElement $e): string => $e->getAttribute('value'),
            iterator_to_array($crawler->filter('input[name="' . $name . '"][checked]')),
        );
        self::assertSame([], $checked('has_nuss'), 'sin informar: ninguna opción marcada');
        self::assertSame(['1'], $checked('takes_ffeoe'), 'por defecto realiza la FFEOE');
        self::assertSame(['0'], $checked('has_car'));
        self::assertSame(['0'], $checked('brings_company'));
        self::assertSame([''], $checked('attitude'), 'sin valorar');
    }

    public function testCoordinatorHeadAndGroupTutorCanEdit(): void
    {
        $this->scene();
        $coordinator = $this->teacher('coordinadora');
        $head        = $this->teacher('jefe');
        $tutor       = $this->teacher('tutora');
        $this->programme->addCoordinator($coordinator);
        $this->programme->getProfessionalFamily()->setHead($head);
        $this->group->addTutor($tutor);
        $this->flush();

        foreach ([$coordinator, $head, $tutor] as $teacher) {
            $this->loginAs($teacher, $this->centre);
            $this->client->request('GET', $this->url());
            self::assertResponseIsSuccessful($teacher->getUsername() . ' debe poder editar la ficha');
        }
    }

    public function testAGroupTeacherWhoIsNotTutorCannotEdit(): void
    {
        $this->scene();
        $teacher = $this->teacher('docente');
        $this->group->addTeacher($teacher);
        $this->flush();
        $this->loginAs($teacher, $this->centre);

        $this->client->request('GET', $this->url());

        self::assertResponseStatusCodeSame(403);
    }

    public function testATeacherOfAnotherGroupCannotEdit(): void
    {
        $this->scene();
        $other = $this->teacher('otra');
        $this->loginAs($other, $this->centre);   // recibe un grupo de acceso mínimo ajeno al estudiante

        $this->client->request('GET', $this->url());

        self::assertResponseStatusCodeSame(403);
    }

    public function testAPastYearIsReadOnly(): void
    {
        $this->scene();
        $admin = $this->teacher('directivo');
        $this->centre->addAdmin($admin);
        $newer = (new AcademicYear())->setName('2026-2027')->setEducationalCentre($this->centre);
        $this->persist($newer);
        $this->centre->setActiveAcademicYear($newer);
        $this->flush();
        $this->loginAs($admin, $this->centre);
        $this->viewPastYear($this->year);

        $this->client->request('GET', $this->url());

        self::assertResponseStatusCodeSame(403);
    }

    public function testAStudentOutsideTheYearIsNotFound(): void
    {
        $this->scene();
        $admin = $this->teacher('directivo');
        $this->centre->addAdmin($admin);
        $stranger = (new Student(new PersonName('Sin', 'Grupo')))->setStudentId('9-999');
        $this->persist($stranger);
        $this->loginAs($admin, $this->centre);

        $this->client->request('GET', '/estudiantes/' . $stranger->getId()->toRfc4122() . '/ficha');

        self::assertResponseStatusCodeSame(404);
    }

    // ── Guardar ──────────────────────────────────────────────────────────────

    public function testSavingTheProfileStoresEveryField(): void
    {
        $this->scene();
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->loginAs($tutor, $this->centre);

        $this->submit([
            'has_nuss'         => '0',
            'takes_ffeoe'      => '1',
            'has_car'          => '1',
            'brings_company'   => '1',
            'company_name'     => 'Alfa S.L.',
            'attitude'         => '4',
            'residence_city'   => 'Lebrija',
            'preferred_city'   => 'Sevilla',
        ], ajax: true);

        self::assertResponseIsSuccessful();
        self::assertJson((string) $this->client->getResponse()->getContent());

        $profile = $this->storedProfile();
        self::assertNotNull($profile);
        self::assertFalse($profile->hasNuss());
        self::assertTrue($profile->takesFfeoe());
        self::assertTrue($profile->hasCar());
        self::assertTrue($profile->bringsCompany());
        self::assertSame('Alfa S.L.', $profile->getBroughtCompanyName());
        self::assertSame(4, $profile->getAttitude());
        self::assertSame('Lebrija', $profile->getResidenceCity());
        self::assertSame('Sevilla', $profile->getPreferredCity());
    }

    public function testNotTakingTheFfeoeRequiresAReason(): void
    {
        $this->scene();
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->loginAs($tutor, $this->centre);

        $crawler = $this->submit(['takes_ffeoe' => '0', 'exclusion_reason' => '  '], ajax: true);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Indica el motivo', $crawler->text());
        self::assertNull($this->storedProfile(), 'una ficha inválida no se guarda');

        $this->submit(['takes_ffeoe' => '0', 'exclusion_reason' => 'Tiene suspendido PRL'], ajax: true);

        self::assertResponseIsSuccessful();
        $profile = $this->storedProfile();
        self::assertFalse($profile?->takesFfeoe());
        self::assertSame('Tiene suspendido PRL', $profile?->getFfeoeExclusionReason());
    }

    public function testBringingACompanyRequiresItsName(): void
    {
        $this->scene();
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->loginAs($tutor, $this->centre);

        $crawler = $this->submit(['brings_company' => '1', 'company_name' => ''], ajax: true);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Indica qué empresa aporta', $crawler->text());
    }

    public function testAnOutOfRangeAttitudeIsRejected(): void
    {
        $this->scene();
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->loginAs($tutor, $this->centre);

        $this->submit(['attitude' => '9'], ajax: true);

        self::assertResponseStatusCodeSame(422);
    }

    public function testSwitchingToDoingTheFfeoeAgainDropsTheReason(): void
    {
        $this->scene();
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->loginAs($tutor, $this->centre);

        $this->submit(['takes_ffeoe' => '0', 'exclusion_reason' => 'Ya la realizó'], ajax: true);
        $this->submit(['takes_ffeoe' => '1', 'exclusion_reason' => 'Ya la realizó'], ajax: true);

        $profile = $this->storedProfile();
        self::assertTrue($profile?->takesFfeoe());
        self::assertNull($profile?->getFfeoeExclusionReason());
    }

    public function testAPostWithoutAValidCsrfTokenIsRefused(): void
    {
        $this->scene();
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->loginAs($tutor, $this->centre);

        $this->client->request('POST', $this->url(), ['_token' => 'falso', 'has_nuss' => '0']);

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->storedProfile());
    }

    public function testWithoutJavascriptThePageSavesAndRedirectsBack(): void
    {
        $this->scene();
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->loginAs($tutor, $this->centre);

        $this->submit(['has_nuss' => '1', 'return' => '/estancias'], ajax: false);

        self::assertResponseRedirects('/estancias');
        self::assertTrue($this->storedProfile()?->hasNuss());
    }

    public function testTheReturnUrlCannotLeaveTheApplication(): void
    {
        $this->scene();
        $tutor = $this->teacher('tutora');
        $this->group->addTutor($tutor);
        $this->flush();
        $this->loginAs($tutor, $this->centre);

        $this->submit(['has_nuss' => '1', 'return' => 'https://evil.example/'], ajax: false);

        self::assertResponseRedirects('/estancias');
    }

    // ── Importación ──────────────────────────────────────────────────────────

    public function testImportFillsNussAndResidenceFromTheColumns(): void
    {
        $this->scene();
        $admin = $this->globalAdmin();
        $this->loginAs($admin);

        $this->importCsv([
            '"Estado Matrícula","Nº Id. Escolar","Primer apellido","Segundo apellido","Nombre","Unidad","Localidad de residencia","NºSeg.Social"',
            '"","3-001","Ruiz","Lopez","Eva","DAW1A","Utrera","410000000001"',
            '"","3-002","Soto","Perez","Iván","DAW1A","Dos Hermanas",""',
        ]);

        $eva  = $this->profileOf('3-001');
        $ivan = $this->profileOf('3-002');
        self::assertTrue($eva?->hasNuss(), 'NºSeg.Social con valor: dispone de NUSS');
        self::assertSame('Utrera', $eva?->getResidenceCity());
        self::assertFalse($ivan?->hasNuss(), 'NºSeg.Social vacío: sin NUSS');
        self::assertTrue($ivan?->isMissingNuss());
        self::assertSame('Dos Hermanas', $ivan?->getResidenceCity());
    }

    public function testImportWithoutTheOptionalColumnsCreatesNoProfile(): void
    {
        $this->scene();
        $this->loginAs($this->globalAdmin());

        $this->importCsv([
            '"Estado Matrícula","Nº Id. Escolar","Primer apellido","Segundo apellido","Nombre","Unidad"',
            '"","3-001","Ruiz","Lopez","Eva","DAW1A"',
        ]);

        self::assertNull($this->profileOf('3-001'));
    }

    public function testReimportingKeepsTheManualFields(): void
    {
        $this->scene();
        $this->loginAs($this->globalAdmin());
        $columns = '"Estado Matrícula","Nº Id. Escolar","Primer apellido","Segundo apellido","Nombre","Unidad","Localidad de residencia","NºSeg.Social"';

        $this->importCsv([$columns, '"","3-001","Ruiz","Lopez","Eva","DAW1A","Utrera",""']);

        $profile = $this->profileOf('3-001');
        $profile->setTakesFfeoe(false)->setFfeoeExclusionReason('Ya la realizó')->setHasCar(true)->setAttitude(5)->setPreferredCity('Sevilla');
        $this->flush();

        $this->importCsv([$columns, '"","3-001","Ruiz","Lopez","Eva","DAW1A","Lebrija","410000000001"']);

        $again = $this->profileOf('3-001');
        self::assertTrue($again?->hasNuss(), 'el NUSS lo manda el CSV');
        self::assertSame('Lebrija', $again?->getResidenceCity());
        self::assertFalse($again?->takesFfeoe(), 'los datos manuales no se tocan');
        self::assertSame('Ya la realizó', $again?->getFfeoeExclusionReason());
        self::assertTrue($again?->hasCar());
        self::assertSame(5, $again?->getAttitude());
        self::assertSame('Sevilla', $again?->getPreferredCity());
    }

    public function testThePreviewWarnsAboutStudentsWithoutNuss(): void
    {
        $this->scene();
        $this->loginAs($this->globalAdmin());
        $centreId = $this->centre->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/importar');
        $token   = $crawler->filter('[name="_token"]')->first()->attr('value');
        $file    = $this->csvFile([
            '"Estado Matrícula","Nº Id. Escolar","Primer apellido","Segundo apellido","Nombre","Unidad","NºSeg.Social"',
            '"","3-002","Soto","Perez","Iván","DAW1A",""',
        ]);

        $preview = $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/importar', ['_token' => $token], ['csv' => $file]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('1 estudiante sin NUSS', $preview->text());
    }

    public function testTheTemplateIncludesTheNewColumns(): void
    {
        $this->scene();
        $this->loginAs($this->globalAdmin());

        $this->client->request('GET', '/admin/centros/' . $this->centre->getId()->toRfc4122() . '/estudiantes/plantilla');

        $csv = $this->getStreamedContent();
        self::assertStringContainsString('Localidad de residencia', $csv);
        self::assertStringContainsString('NºSeg.Social', $csv);
    }

    // ── Ayudantes ────────────────────────────────────────────────────────────

    private function scene(): void
    {
        $this->centre = (new EducationalCentre())->setCode('41' . random_int(100000, 999999))->setName('IES Test')->setCity('Sevilla');
        $this->year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($this->centre);
        $family       = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($this->year);
        $this->programme = (new Programme())->setName('DAW')->setAcademicYear($this->year)->setProfessionalFamily($family);
        $level        = (new ProgrammeYear())->setName('Primero')->setProgramme($this->programme);
        $this->group  = (new Group())->setName('DAW1A')->setProgrammeYear($level);
        $this->student = (new Student(new PersonName('Ana', 'Martínez')))->setStudentId('2025-001');
        $this->group->addStudent($this->student);
        $this->persist($this->centre, $this->year, $family, $this->programme, $level, $this->group, $this->student);
        $this->centre->setActiveAcademicYear($this->year);
        $this->flush();
        $this->studentId = $this->student->getId()->toRfc4122();
    }

    private function teacher(string $username): Teacher
    {
        $teacher = (new Teacher(new PersonName('Test', 'Teacher ' . ++$this->seq)))->setUsername($username);
        $this->persist($teacher);

        return $teacher;
    }

    private function globalAdmin(): Teacher
    {
        $admin = (new Teacher(new PersonName('Admin', 'Global')))->setUsername('admin.global')->setAdmin(true);
        $this->persist($admin);

        return $admin;
    }

    private function url(): string
    {
        return '/estudiantes/' . $this->studentId . '/ficha';
    }

    /** @param array<string, string> $fields */
    private function submit(array $fields, bool $ajax): \Symfony\Component\DomCrawler\Crawler
    {
        $form  = $this->client->request('GET', $this->url());
        $token = $form->filter('[name="_token"]')->first()->attr('value');

        return $this->client->request(
            'POST',
            $this->url(),
            ['_token' => $token] + $fields,
            server: $ajax ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : [],
        );
    }

    private function storedProfile(): ?StudentYearProfile
    {
        /** @var StudentYearProfileRepository $repo */
        $repo = self::getContainer()->get(StudentYearProfileRepository::class);
        $this->em->clear();
        $student = $this->em->getReference(Student::class, $this->student->getId());
        $year    = $this->em->getReference(AcademicYear::class, $this->year->getId());

        return $repo->findByStudentAndYear($student, $year);
    }

    private function profileOf(string $nie): ?StudentYearProfile
    {
        $this->em->clear();
        $student = $this->em->getRepository(Student::class)->findOneBy(['studentId' => $nie]);
        self::assertNotNull($student, 'el estudiante ' . $nie . ' debe existir');
        /** @var StudentYearProfileRepository $repo */
        $repo = self::getContainer()->get(StudentYearProfileRepository::class);
        $year = $this->em->getReference(AcademicYear::class, $this->year->getId());

        return $repo->findByStudentAndYear($student, $year);
    }

    /** @param list<string> $lines */
    private function csvFile(array $lines): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'nexo_profile_');
        file_put_contents($tmp, implode("\n", $lines));

        return new UploadedFile($tmp, 'students.csv', 'text/csv', null, true);
    }

    /** Sube el CSV y confirma la vista previa. @param list<string> $lines */
    private function importCsv(array $lines): void
    {
        $centreId = $this->centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/estudiantes/importar');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $preview = $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/importar', ['_token' => $token], ['csv' => $this->csvFile($lines)]);
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/admin/centros/' . $centreId . '/estudiantes/importar', [
            'import_confirmed' => '1',
            'groups'           => [$this->group->getId()->toRfc4122()],
            'import_id'        => $preview->filter('[name="import_id"]')->first()->attr('value'),
            '_token'           => $preview->filter('[name="_token"]')->first()->attr('value'),
        ]);
        self::assertResponseRedirects();
    }
}
