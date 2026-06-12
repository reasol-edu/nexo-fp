<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class SearchControllerTest extends ControllerTestCase
{
    public function testSearchRedirectsAnonymousUser(): void
    {
        $this->client->request('GET', '/buscar?q=test');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testSearchReturnsEmptyGroupsWhenQueryTooShort(): void
    {
        [$centre, $admin] = $this->makeChain('41000070', 'search.admin.70');
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/buscar?q=a');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['groups' => []], $data);
    }

    public function testSearchReturnsStayMatchingQuery(): void
    {
        [$centre, $admin, $stay] = $this->makeChain('41000071', 'search.admin.71');
        $this->loginAs($admin, $centre);

        $q = substr($stay->getName(), 0, 5);
        $this->client->request('GET', '/buscar?q=' . urlencode($q));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('stays', $data['groups']);
        self::assertSame($stay->getName(), $data['groups']['stays'][0]['label']);
    }

    public function testSearchReturnsCompanyForCentreAdmin(): void
    {
        [$centre, $admin] = $this->makeChain('41000072', 'search.admin.72');
        $company = (new Company())->setName('TechBits Corp')->setVatNumber('B12345672')->setCity('Sevilla')->setEducationalCentre($centre);
        $this->persist($company);
        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/buscar?q=TechBits');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('companies', $data['groups']);
        self::assertSame('TechBits Corp', $data['groups']['companies'][0]['label']);
    }

    public function testSearchHidesCompaniesFromTeacherWithoutPermission(): void
    {
        [$centre] = $this->makeChain('41000073', 'search.admin.73');
        $company = (new Company())->setName('Oculta Corp')->setVatNumber('B98765432')->setCity('Sevilla')->setEducationalCentre($centre);
        $teacher = (new Teacher(new PersonName('Sin', 'Permisos')))->setUsername('noperm.73');
        $this->persist($company, $teacher);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/buscar?q=Oculta');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayNotHasKey('companies', $data['groups']);
    }

    public function testSearchReturnsTenantIsolatedStays(): void
    {
        [$centreA, $adminA, $stayA] = $this->makeChain('41000074', 'search.admin.74');
        [$centreB, $adminB, $stayB] = $this->makeChain('41000075', 'search.admin.75');
        $this->loginAs($adminA, $centreA);

        $q = substr($stayB->getName(), 0, 8);
        $this->client->request('GET', '/buscar?q=' . urlencode($q));

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $labels = array_column($data['groups']['stays'] ?? [], 'label');
        self::assertNotContains($stayB->getName(), $labels);
    }

    public function testSearchReturnsStudentForCentreAdmin(): void
    {
        [$centre, $admin, , $prog] = $this->makeChain('41000076', 'search.admin.76');

        $progYear = (new ProgrammeYear())->setName('1º DAW')->setProgramme($prog);
        $group    = (new Group())->setProgrammeYear($progYear)->setName('1DAW-A');
        $student  = new Student(new PersonName('Martina', 'Buscable'));
        $student->setStudentId('NIE-76A');
        $group->addStudent($student);
        $this->persist($progYear, $group, $student);

        $this->loginAs($admin, $centre);

        $this->client->request('GET', '/buscar?q=Buscable');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('students', $data['groups']);
        self::assertStringContainsString('Buscable', $data['groups']['students'][0]['label']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return array{EducationalCentre, Teacher, Stay, Programme}
     */
    private function makeChain(string $code, string $username): array
    {
        $centre  = (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
        $year    = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $fam     = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($fam);
        $stay    = (new Stay())
            ->setName('FCT-DAW-' . $code)
            ->setAcademicYear($year)
            ->setProgramme($prog)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable('+30 days'));
        $admin   = (new Teacher(new PersonName('Admin', 'Centro')))->setUsername($username);
        $this->persist($centre, $year, $fam, $prog, $stay, $admin);
        $centre->setActiveAcademicYear($year);
        $centre->addAdmin($admin);
        $this->flush();

        return [$centre, $admin, $stay, $prog];
    }
}
