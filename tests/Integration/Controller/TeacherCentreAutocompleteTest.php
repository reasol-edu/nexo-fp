<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

/**
 * The teacher selects in the formative-offer detail panel fetch their options
 * on demand from this endpoint (remote filtering), so we lock in the JSON
 * contract the front-end depends on and the per-year scoping.
 */
class TeacherCentreAutocompleteTest extends ControllerTestCase
{
    public function testReturnsMatchingTeachersOfTheYearAsResults(): void
    {
        [$admin, $centre, $year] = $this->makeCentre();
        $this->makeYearTeacher($year, 'gomez.1', 'Luisa', 'Gomez');
        $this->makeYearTeacher($year, 'ruiz.1', 'Pedro', 'Ruiz');
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/autocomplete/teacher_centre', [
            'academicYearId' => $year->getId()->toRfc4122(),
            'query'          => 'gom',
        ]);

        self::assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('results', $json);
        self::assertCount(1, $json['results']);
        self::assertSame('Gomez, Luisa', $json['results'][0]['text']);
        self::assertNotEmpty($json['results'][0]['value']);
    }

    public function testExcludesTeachersOfOtherYears(): void
    {
        [$admin, $centre, $year] = $this->makeCentre();
        $otherYear = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($otherYear);
        $this->makeYearTeacher($otherYear, 'gomez.2', 'Luisa', 'Gomez');
        $this->flush();
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/autocomplete/teacher_centre', [
            'academicYearId' => $year->getId()->toRfc4122(),
            'query'          => 'gom',
        ]);

        self::assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([], $json['results']);
    }

    private function makeYearTeacher(AcademicYear $year, string $username, string $first, string $last): Teacher
    {
        $teacher = (new Teacher(new PersonName($first, $last)))
            ->setUsername($username)
            ->addAcademicYear($year);
        $this->persist($teacher);

        return $teacher;
    }

    /** @return array{Teacher, EducationalCentre, AcademicYear} */
    private function makeCentre(): array
    {
        $admin  = (new Teacher(new PersonName('Admin', 'User')))->setUsername('admin.ac.1')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);

        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $centre->addAdmin($admin);
        $this->flush();

        return [$admin, $centre, $year];
    }
}
