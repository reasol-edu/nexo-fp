<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class EducationalCentreHubControllerTest extends ControllerTestCase
{
    public function testIndexRedirectsToSelectCentreWhenNoTenantSet(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        $this->persist($teacher);
        $this->loginAs($teacher);

        $this->client->request('GET', '/mi-centro');

        self::assertResponseRedirects();
        self::assertStringContainsString('/centro', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testIndexRendersPageForGlobalAdmin(): void
    {
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.hub')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/mi-centro');

        self::assertResponseIsSuccessful();
    }

    public function testIndexRendersPageForEquipoDirectivo(): void
    {
        $teacher = $this->makeTeacher('directivo.hub');
        $centre  = (new EducationalCentre())->setCode('41000002')->setName('IES Test')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($teacher, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $centre->addAdmin($teacher);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/mi-centro');

        self::assertResponseIsSuccessful();
    }

    public function testIndexDeniesAccessToUnprivilegedTeacher(): void
    {
        $teacher = $this->makeTeacher('unprivileged.hub');
        $centre  = (new EducationalCentre())->setCode('41000003')->setName('IES Test')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($teacher, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/mi-centro');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDataReviewListsIncompleteData(): void
    {
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.review')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000004')->setName('IES Revisión')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $noMail = (new Teacher(new PersonName('Sin', 'Correo')))->setUsername('sin.correo');
        $noMail->addAcademicYear($year);
        $withMail = (new Teacher(new PersonName('Con', 'Correo')))->setUsername('con.correo')->setEmail('con@test.local');
        $withMail->addAcademicYear($year);

        $family    = (new \App\Entity\ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new \App\Entity\Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $level     = (new \App\Entity\ProgrammeYear())->setName('Primer curso')->setProgramme($programme);
        $group     = (new \App\Entity\Group())->setName('DAW1A')->setProgrammeYear($level);
        $student   = (new \App\Entity\Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $group->addStudent($student);
        $today = new \DateTimeImmutable('today');
        $stay  = (new \App\Entity\Stay())->setName('Estancia en marcha')->setAcademicYear($year)->addProgramme($programme)
            ->setStartDate($today->modify('-5 days'))->setEndDate($today->modify('+30 days'));
        $stay->addStudent($student);
        $company = (new \App\Entity\Company())->setName('Empresa Sola S.L.')->setVatNumber('B12345678')->setCity('Sevilla')->setEducationalCentre($centre);
        $wc      = (new \App\Entity\Workcenter())->setName('Sede')->setCity('Sevilla')->setCompany($company);
        $position = (new \App\Entity\TrainingPosition())->setStay($stay)->setWorkcenter($wc)->setStudent($student);
        $this->persist($admin, $centre, $year, $noMail, $withMail, $family, $programme, $level, $group, $student, $stay, $company, $wc, $position);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $crawler = $this->client->request('GET', '/mi-centro/revision-datos');

        self::assertResponseIsSuccessful();
        $text = $crawler->text();
        self::assertStringContainsString('Correo, Sin', $text);
        self::assertStringNotContainsString('Correo, Con', $text);
        self::assertStringContainsString('Empresa Sola S.L.', $text);
        self::assertStringContainsString('Estancia en marcha', $text);
    }

    public function testDataReviewDeniesUnprivilegedTeacher(): void
    {
        $teacher = $this->makeTeacher('unprivileged.review');
        $centre  = (new EducationalCentre())->setCode('41000005')->setName('IES Test')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($teacher, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/mi-centro/revision-datos');

        self::assertResponseStatusCodeSame(403);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }
}
