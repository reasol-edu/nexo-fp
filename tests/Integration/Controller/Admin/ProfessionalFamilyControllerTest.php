<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class ProfessionalFamilyControllerTest extends ControllerTestCase
{
    // ── index ─────────────────────────────────────────────────────────────────

    public function testIndexIsAccessibleToAdmin(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/familias');

        self::assertResponseIsSuccessful();
    }

    public function testIndexIsAccessibleToEquipoDirectivo(): void
    {
        $directivo = $this->makeTeacher('directivo.1');
        [, $centre, $year] = $this->makeAdminAndCentre();
        $this->persist($directivo, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $centre->addAdmin($directivo);
        $this->flush();
        $this->loginAs($directivo);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/familias');

        self::assertResponseIsSuccessful();
    }

    public function testIndexDeniesNonAdmin(): void
    {
        $teacher = $this->makeTeacher('teacher.1');
        [, $centre, $year] = $this->makeAdminAndCentre();
        $this->persist($teacher, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($teacher);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/familias');

        self::assertResponseStatusCodeSame(403);
    }

    // ── export ─────────────────────────────────────────────────────────────────

    public function testExportReturnsJsonAttachment(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/familias/exportar');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'attachment;',
            (string) $this->client->getResponse()->headers->get('Content-Disposition'),
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }

    /** @return array{0: Teacher, 1: EducationalCentre, 2: AcademicYear} */
    private function makeAdminAndCentre(): array
    {
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.1')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        return [$admin, $centre, $year];
    }
}
