<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class EducationalCentreSettingsControllerTest extends ControllerTestCase
{
    public function testSettingsRedirectsAnonymousUser(): void
    {
        $this->client->request('GET', '/mi-centro/ajustes');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testSettingsIsAccessibleToGlobalAdmin(): void
    {
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.centre.settings')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000101')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/mi-centro/ajustes');

        self::assertResponseIsSuccessful();
    }

    public function testSettingsIsAccessibleToCentreAdmin(): void
    {
        $teacher = (new Teacher(new PersonName('Dir', 'Centre')))->setUsername('directivo.settings');
        $centre  = (new EducationalCentre())->setCode('41000102')->setName('IES Test B')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($teacher, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $centre->addAdmin($teacher);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/mi-centro/ajustes');

        self::assertResponseIsSuccessful();
    }

    public function testSettingsDeniesUnprivilegedTeacher(): void
    {
        $teacher = (new Teacher(new PersonName('Plain', 'Teacher')))->setUsername('plain.settings');
        $centre  = (new EducationalCentre())->setCode('41000103')->setName('IES Test C')->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($teacher, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/mi-centro/ajustes');

        self::assertResponseStatusCodeSame(403);
    }
}
