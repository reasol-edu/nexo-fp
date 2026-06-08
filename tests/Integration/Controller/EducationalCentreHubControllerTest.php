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

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }
}
