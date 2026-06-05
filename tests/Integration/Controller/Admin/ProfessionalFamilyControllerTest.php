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

    // ── new family ────────────────────────────────────────────────────────────

    public function testNewFamilyGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $this->client->request('GET', '/admin/centros/' . $centre->getId()->toRfc4122() . '/familias/nueva');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testNewFamilyPostCreatesFamilyAndRedirectsToEdit(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/familias/nueva');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/familias/nueva', [
            '_token' => $token,
            'name'   => 'Informática y Comunicaciones',
        ]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/familias/', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testNewFamilyPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $this->client->request('POST', '/admin/centros/' . $centreId . '/familias/nueva', [
            '_token' => 'token-invalido',
            'name'   => 'Familia Test',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testNewFamilyPostWithEmptyNameRendersFormAgain(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $this->persist($admin, $centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $crawler  = $this->client->request('GET', '/admin/centros/' . $centreId . '/familias/nueva');
        $token    = $crawler->filter('[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/familias/nueva', [
            '_token' => $token,
            'name'   => '',
        ]);

        self::assertResponseIsSuccessful();
    }

    // ── edit family ───────────────────────────────────────────────────────────

    public function testEditFamilyGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $this->persist($admin, $centre, $year, $family);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $familyId = $family->getId()->toRfc4122();

        $this->client->request('GET', '/admin/centros/' . $centreId . '/familias/' . $familyId);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditFamilyPostSavesChanges(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $this->persist($admin, $centre, $year, $family);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $familyId = $family->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/admin/centros/' . $centreId . '/familias/' . $familyId);
        $token   = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/familias/' . $familyId, [
            '_token' => $token,
            'name'   => 'Informática Modificada',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        $updated = $this->em->find(ProfessionalFamily::class, $family->getId());
        self::assertSame('Informática Modificada', $updated->getName());
    }

    public function testEditFamilyPostWithInvalidCsrfIsDenied(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $this->persist($admin, $centre, $year, $family);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $familyId = $family->getId()->toRfc4122();

        $this->client->request('POST', '/admin/centros/' . $centreId . '/familias/' . $familyId, [
            '_token' => 'token-invalido',
            'name'   => 'Hack',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteFamilyDeletesEntityAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $this->persist($admin, $centre, $year, $family);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $familyId = $family->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/admin/centros/' . $centreId . '/familias/' . $familyId);
        $token   = $crawler->filter('form[action$="/eliminar"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/admin/centros/' . $centreId . '/familias/' . $familyId . '/eliminar', ['_token' => $token]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/familias', (string) $this->client->getResponse()->headers->get('Location'));

        $this->em->clear();
        self::assertNull($this->em->find(ProfessionalFamily::class, $family->getId()));
    }

    // ── programme ─────────────────────────────────────────────────────────────

    public function testAddProgrammeCreatesProgrammeAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $this->persist($admin, $centre, $year, $family);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId = $centre->getId()->toRfc4122();
        $familyId = $family->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/admin/centros/' . $centreId . '/familias/' . $familyId);
        // The path() function percent-encodes ñ → ense%C3%B1anzas in the HTML attribute
        $token   = $crawler->filter('form[action$="/ense%C3%B1anzas"] [name="_token"]')->first()->attr('value');

        $this->client->request(
            'POST',
            '/admin/centros/' . $centreId . '/familias/' . $familyId . '/enseñanzas',
            ['_token' => $token, 'name' => 'DAW'],
        );

        self::assertResponseRedirects();
        self::assertStringContainsString('/familias/' . $familyId, (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testEditProgrammeGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $programme               = $this->makeProgramme($family, $year, 'DAW');
        $this->persist($admin, $centre, $year, $family, $programme);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId    = $centre->getId()->toRfc4122();
        $familyId    = $family->getId()->toRfc4122();
        $programmeId = $programme->getId()->toRfc4122();
        $url         = '/admin/centros/' . $centreId . '/familias/' . $familyId . '/enseñanzas/' . $programmeId;

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    // ── level ─────────────────────────────────────────────────────────────────

    public function testAddLevelCreatesLevelAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $programme               = $this->makeProgramme($family, $year, 'DAW');
        $this->persist($admin, $centre, $year, $family, $programme);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId    = $centre->getId()->toRfc4122();
        $familyId    = $family->getId()->toRfc4122();
        $programmeId = $programme->getId()->toRfc4122();
        $editUrl     = '/admin/centros/' . $centreId . '/familias/' . $familyId . '/enseñanzas/' . $programmeId;

        $crawler = $this->client->request('GET', $editUrl);
        $token   = $crawler->filter('form[action$="/niveles"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', $editUrl . '/niveles', ['_token' => $token, 'name' => 'Primer curso']);

        self::assertResponseRedirects();
        // Location header percent-encodes ñ → ense%C3%B1anzas
        self::assertStringContainsString($programmeId, (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testEditLevelGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $programme               = $this->makeProgramme($family, $year, 'DAW');
        $level                   = $this->makeLevel($programme, 'Primer curso');
        $this->persist($admin, $centre, $year, $family, $programme, $level);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId    = $centre->getId()->toRfc4122();
        $familyId    = $family->getId()->toRfc4122();
        $programmeId = $programme->getId()->toRfc4122();
        $levelId     = $level->getId()->toRfc4122();
        $url         = '/admin/centros/' . $centreId . '/familias/' . $familyId
            . '/enseñanzas/' . $programmeId . '/niveles/' . $levelId;

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    // ── group ─────────────────────────────────────────────────────────────────

    public function testAddGroupCreatesGroupAndRedirects(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $programme               = $this->makeProgramme($family, $year, 'DAW');
        $level                   = $this->makeLevel($programme, 'Primer curso');
        $this->persist($admin, $centre, $year, $family, $programme, $level);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId    = $centre->getId()->toRfc4122();
        $familyId    = $family->getId()->toRfc4122();
        $programmeId = $programme->getId()->toRfc4122();
        $levelId     = $level->getId()->toRfc4122();
        $levelUrl    = '/admin/centros/' . $centreId . '/familias/' . $familyId
            . '/enseñanzas/' . $programmeId . '/niveles/' . $levelId;

        $crawler = $this->client->request('GET', $levelUrl);
        $token   = $crawler->filter('form[action$="/grupos"] [name="_token"]')->first()->attr('value');

        $this->client->request('POST', $levelUrl . '/grupos', ['_token' => $token, 'name' => 'DAW1A']);

        self::assertResponseRedirects();
        self::assertStringContainsString('/niveles/' . $levelId, (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testEditGroupGetRendersForm(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $programme               = $this->makeProgramme($family, $year, 'DAW');
        $level                   = $this->makeLevel($programme, 'Primer curso');
        $group                   = $this->makeGroup($level, 'DAW1A');
        $this->persist($admin, $centre, $year, $family, $programme, $level, $group);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId    = $centre->getId()->toRfc4122();
        $familyId    = $family->getId()->toRfc4122();
        $programmeId = $programme->getId()->toRfc4122();
        $levelId     = $level->getId()->toRfc4122();
        $groupId     = $group->getId()->toRfc4122();
        $url         = '/admin/centros/' . $centreId . '/familias/' . $familyId
            . '/enseñanzas/' . $programmeId . '/niveles/' . $levelId . '/grupos/' . $groupId;

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testEditGroupPostSavesChanges(): void
    {
        [$admin, $centre, $year] = $this->makeAdminAndCentre();
        $family                  = $this->makeFamily($year, 'Informática');
        $programme               = $this->makeProgramme($family, $year, 'DAW');
        $level                   = $this->makeLevel($programme, 'Primer curso');
        $group                   = $this->makeGroup($level, 'DAW1A');
        $this->persist($admin, $centre, $year, $family, $programme, $level, $group);
        $centre->setActiveAcademicYear($year);
        $this->flush();
        $this->loginAs($admin);

        $centreId    = $centre->getId()->toRfc4122();
        $familyId    = $family->getId()->toRfc4122();
        $programmeId = $programme->getId()->toRfc4122();
        $levelId     = $level->getId()->toRfc4122();
        $groupId     = $group->getId()->toRfc4122();
        $url         = '/admin/centros/' . $centreId . '/familias/' . $familyId
            . '/enseñanzas/' . $programmeId . '/niveles/' . $levelId . '/grupos/' . $groupId;

        $crawler = $this->client->request('GET', $url);
        $token   = $crawler->filter('form')->first()->filter('[name="_token"]')->attr('value');

        $this->client->request('POST', $url, [
            '_token' => $token,
            'name'   => 'DAW1B',
        ]);

        self::assertResponseRedirects();

        $this->em->clear();
        $updated = $this->em->find(Group::class, $group->getId());
        self::assertSame('DAW1B', $updated->getName());
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

    private function makeFamily(AcademicYear $year, string $name): ProfessionalFamily
    {
        return (new ProfessionalFamily())->setName($name)->setAcademicYear($year);
    }

    private function makeProgramme(ProfessionalFamily $family, AcademicYear $year, string $name): Programme
    {
        return (new Programme())->setName($name)->setProfessionalFamily($family)->setAcademicYear($year);
    }

    private function makeLevel(Programme $programme, string $name): ProgrammeYear
    {
        return (new ProgrammeYear())->setName($name)->setProgramme($programme);
    }

    private function makeGroup(ProgrammeYear $level, string $name): Group
    {
        return (new Group())->setName($name)->setProgrammeYear($level);
    }
}
