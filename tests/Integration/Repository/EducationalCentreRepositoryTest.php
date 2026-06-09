<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
use App\Entity\Teacher;
use App\Repository\EducationalCentreRepository;
use App\Tests\Integration\RepositoryTestCase;

class EducationalCentreRepositoryTest extends RepositoryTestCase
{
    private EducationalCentreRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var EducationalCentreRepository $repo */
        $repo       = self::getContainer()->get(EducationalCentreRepository::class);
        $this->repo = $repo;
    }

    // ── findByCode ────────────────────────────────────────────────────────────

    public function testFindByCodeReturnsCentre(): void
    {
        $centre = $this->makeCentre('41000001');
        $this->persist($centre);

        $result = $this->repo->findByCode('41000001');

        self::assertNotNull($result);
        self::assertSame('41000001', $result->getCode());
    }

    public function testFindByCodeReturnsNullForUnknownCode(): void
    {
        $centre = $this->makeCentre('41000001');
        $this->persist($centre);

        self::assertNull($this->repo->findByCode('99999999'));
    }

    // ── findById ──────────────────────────────────────────────────────────────

    public function testFindByIdReturnsCentre(): void
    {
        $centre = $this->makeCentre('41000002');
        $this->persist($centre);

        $result = $this->repo->findById($centre->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertSame($centre->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByIdReturnsNullForNonExistentId(): void
    {
        self::assertNull($this->repo->findById('00000000-0000-0000-0000-000000000000'));
    }

    // ── findByIdWithActiveYear ────────────────────────────────────────────────

    public function testFindByIdWithActiveYearReturnsCentreWithEagerLoadedYear(): void
    {
        $centre = $this->makeCentre('41000003');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $result = $this->repo->findByIdWithActiveYear($centre->getId()->toRfc4122());

        self::assertNotNull($result);
        self::assertNotNull($result->getActiveAcademicYear());
        self::assertSame('2024-2025', $result->getActiveAcademicYear()->getName());
    }

    // ── countAll ──────────────────────────────────────────────────────────────

    public function testCountAllReturnsZeroOnEmptyDatabase(): void
    {
        self::assertSame(0, $this->repo->countAll());
    }

    public function testCountAllReturnsCorrectCount(): void
    {
        $this->persist(
            $this->makeCentre('41000004'),
            $this->makeCentre('41000005'),
            $this->makeCentre('41000006'),
        );

        self::assertSame(3, $this->repo->countAll());
    }

    // ── findAllOrderedByName ──────────────────────────────────────────────────

    public function testFindAllOrderedByNameReturnsSortedCentres(): void
    {
        $this->persist(
            $this->makeCentreNamed('41000007', 'IES Zubia'),
            $this->makeCentreNamed('41000008', 'IES Alhambra'),
            $this->makeCentreNamed('41000009', 'IES Cartuja'),
        );

        $results = $this->repo->findAllOrderedByName();

        self::assertCount(3, $results);
        self::assertSame('IES Alhambra', $results[0]->getName());
        self::assertSame('IES Cartuja',  $results[1]->getName());
        self::assertSame('IES Zubia',    $results[2]->getName());
    }

    // ── findAllWithActiveYear ─────────────────────────────────────────────────

    public function testFindAllWithActiveYearEagerLoadsActiveYear(): void
    {
        $centre = $this->makeCentre('41000010');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $this->persist($centre, $year);
        $centre->setActiveAcademicYear($year);
        $this->flush();

        $results = $this->repo->findAllWithActiveYear();

        self::assertCount(1, $results);
        self::assertNotNull($results[0]->getActiveAcademicYear());
    }

    // ── createAllWithActiveYearFilteredQuery ──────────────────────────────────

    public function testCreateAllWithActiveYearFilteredQueryReturnsAllWithoutSearch(): void
    {
        $this->persist(
            $this->makeCentre('41000011'),
            $this->makeCentre('41000012'),
        );

        $results = $this->repo->createAllWithActiveYearFilteredQuery()->getResult();

        self::assertCount(2, $results);
    }

    public function testCreateAllWithActiveYearFilteredQuerySearchByName(): void
    {
        // ASCII-only names due to SQLite LOWER() limitation.
        // Different cities so the city column does not produce false positives.
        $c1 = (new EducationalCentre())->setCode('41000013')->setName('IES Sevilla')->setCity('Sevilla');
        $c2 = (new EducationalCentre())->setCode('41000014')->setName('IES Malaga')->setCity('Malaga');
        $this->persist($c1, $c2);

        $results = $this->repo->createAllWithActiveYearFilteredQuery('Sevilla')->getResult();

        self::assertCount(1, $results);
        self::assertSame('IES Sevilla', $results[0]->getName());
    }

    public function testCreateAllWithActiveYearFilteredQuerySearchByCode(): void
    {
        $this->persist(
            $this->makeCentre('41000015'),
            $this->makeCentre('41000016'),
        );

        $results = $this->repo->createAllWithActiveYearFilteredQuery('41000015')->getResult();

        self::assertCount(1, $results);
        self::assertSame('41000015', $results[0]->getCode());
    }

    // ── findAccessibleByTeacher ───────────────────────────────────────────────

    public function testFindAccessibleByTeacherReturnsAllCentresForGlobalAdmin(): void
    {
        $this->persist(
            $this->makeCentre('41000017'),
            $this->makeCentre('41000018'),
        );
        $admin = $this->makeTeacher('admin.one')->setAdmin(true);
        $this->persist($admin);

        $results = $this->repo->findAccessibleByTeacher($admin);

        self::assertCount(2, $results);
    }

    public function testFindAccessibleByTeacherReturnsEmptyForNonAdminWithNoAccess(): void
    {
        $this->persist($this->makeCentre('41000019'));
        $teacher = $this->makeTeacher('no.access');
        $this->persist($teacher);

        $results = $this->repo->findAccessibleByTeacher($teacher);

        self::assertCount(0, $results);
    }

    public function testFindAccessibleByTeacherReturnsCentreWhenTeacherIsFamilyHead(): void
    {
        $centre  = $this->makeCentre('41000020');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year)->setHead(null);
        $teacher = $this->makeTeacher('family.head');
        $this->persist($centre, $year, $family, $teacher);

        $family->setHead($teacher);
        $this->flush();

        $results = $this->repo->findAccessibleByTeacher($teacher);

        self::assertCount(1, $results);
        self::assertSame($centre->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testFindAccessibleByTeacherReturnsCentreWhenTeacherIsCompanyLiaison(): void
    {
        $centre  = $this->makeCentre('41000021');
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $teacher = $this->makeTeacher('liaison.one');
        $this->persist($centre, $company, $teacher);

        $company->addLiaison($teacher);
        $this->flush();

        $results = $this->repo->findAccessibleByTeacher($teacher);

        self::assertCount(1, $results);
        self::assertSame($centre->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testFindAccessibleByTeacherReturnsCentreWhenTeacherIsGroupTutor(): void
    {
        $centre  = $this->makeCentre('41000022');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog    = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $py      = (new ProgrammeYear())->setName('1.º DAM')->setProgramme($prog);
        $teacher = $this->makeTeacher('tutor.one');
        $group   = (new Group())->setName('DAM1A')->setProgrammeYear($py);
        $this->persist($centre, $year, $family, $prog, $py, $teacher, $group);

        $group->addTutor($teacher);
        $this->flush();

        $results = $this->repo->findAccessibleByTeacher($teacher);

        self::assertCount(1, $results);
        self::assertSame($centre->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testFindAccessibleByTeacherDeduplicatesWhenTeacherHasMultipleRoles(): void
    {
        // Teacher is both family head and company liaison for the same centre
        $centre  = $this->makeCentre('41000023');
        $year    = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family  = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $company = $this->makeCompany($centre, 'Empresa S.L.');
        $teacher = $this->makeTeacher('multi.role');
        $this->persist($centre, $year, $family, $company, $teacher);

        $family->setHead($teacher);
        $company->addLiaison($teacher);
        $this->flush();

        $results = $this->repo->findAccessibleByTeacher($teacher);

        self::assertCount(1, $results); // Not duplicated
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())->setCode($code)->setName('IES ' . $code)->setCity('Sevilla');
    }

    private function makeCentreNamed(string $code, string $name): EducationalCentre
    {
        return (new EducationalCentre())->setCode($code)->setName($name)->setCity('Sevilla');
    }

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
    }

    private function makeCompany(EducationalCentre $centre, string $name): Company
    {
        return (new Company())
            ->setName($name)
            ->setVatNumber('B' . substr(md5($name), 0, 8))
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }
}
