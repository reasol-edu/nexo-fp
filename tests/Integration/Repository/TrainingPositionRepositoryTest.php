<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\AcademicYear;
use App\Entity\Company;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\TrainingPosition;
use App\Entity\Workcenter;
use App\Repository\TrainingPositionRepository;
use App\Tests\Integration\RepositoryTestCase;

class TrainingPositionRepositoryTest extends RepositoryTestCase
{
    private TrainingPositionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var TrainingPositionRepository $repo */
        $repo       = self::getContainer()->get(TrainingPositionRepository::class);
        $this->repo = $repo;
    }

    // ── findByIdAndStay ──────────────────────────────────────────────────────

    public function testFindByIdAndStayReturnsPosition(): void
    {
        [$stay] = $this->makeChain('41000001');
        $position = $this->makePosition($stay);
        $this->persist($position);

        $result = $this->repo->findByIdAndStay($position->getId()->toRfc4122(), $stay);

        self::assertNotNull($result);
        self::assertSame($position->getId()->toRfc4122(), $result->getId()->toRfc4122());
    }

    public function testFindByIdAndStayReturnsNullForDifferentStay(): void
    {
        [$stayA] = $this->makeChain('41000002');
        [$stayB] = $this->makeChain('41000003');
        $position = $this->makePosition($stayA);
        $this->persist($position);

        // El position pertenece a stayA, pero se busca en stayB
        $result = $this->repo->findByIdAndStay($position->getId()->toRfc4122(), $stayB);

        self::assertNull($result);
    }

    public function testFindByIdAndStayReturnsNullForNonExistentId(): void
    {
        [$stay] = $this->makeChain('41000004');

        $result = $this->repo->findByIdAndStay('00000000-0000-0000-0000-000000000000', $stay);

        self::assertNull($result);
    }

    // ── findByStayOrdered ────────────────────────────────────────────────────

    public function testFindByStayOrderedReturnsAllPositionsForTheStay(): void
    {
        [$stay] = $this->makeChain('41000005');
        $p1 = $this->makePosition($stay);
        $p2 = $this->makePosition($stay);
        $p3 = $this->makePosition($stay);
        $this->persist($p1, $p2, $p3);

        $results = $this->repo->findByStayOrdered($stay);

        self::assertCount(3, $results);
    }

    public function testFindByStayOrderedExcludesPositionsFromOtherStays(): void
    {
        [$stayA] = $this->makeChain('41000006');
        [$stayB] = $this->makeChain('41000007');
        $pA = $this->makePosition($stayA);
        $pB = $this->makePosition($stayB);
        $this->persist($pA, $pB);

        $results = $this->repo->findByStayOrdered($stayA);

        self::assertCount(1, $results);
        self::assertSame($pA->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testFindByStayOrderedSortsByCompanyThenWorkcenter(): void
    {
        $centre  = $this->makeCentre('41000008');
        $year    = $this->makeYear($centre);
        $family  = $this->makeFamily($year);
        $programme = $this->makeProgramme($year, $family);
        $stay    = $this->makeStay($year, $programme);
        $this->persist($centre, $year, $family, $programme, $stay);

        // Dos empresas con dos centros de trabajo cada una
        $compA = $this->makeCompany($centre, 'Alfa S.L.');
        $compB = $this->makeCompany($centre, 'Beta S.L.');
        $this->persist($compA, $compB);

        $wcA1 = $this->makeWorkcenter($compA, 'Almacén');
        $wcA2 = $this->makeWorkcenter($compA, 'Oficina');
        $wcB1 = $this->makeWorkcenter($compB, 'Planta');
        $this->persist($wcA1, $wcA2, $wcB1);

        $p1 = $this->makePosition($stay)->setWorkcenter($wcA2); // Alfa / Oficina
        $p2 = $this->makePosition($stay)->setWorkcenter($wcB1); // Beta / Planta
        $p3 = $this->makePosition($stay)->setWorkcenter($wcA1); // Alfa / Almacén
        $this->persist($p1, $p2, $p3);

        $results = $this->repo->findByStayOrdered($stay);

        self::assertCount(3, $results);
        // Orden esperado: Alfa/Almacén → Alfa/Oficina → Beta/Planta
        self::assertSame($p3->getId()->toRfc4122(), $results[0]->getId()->toRfc4122()); // Alfa/Almacén
        self::assertSame($p1->getId()->toRfc4122(), $results[1]->getId()->toRfc4122()); // Alfa/Oficina
        self::assertSame($p2->getId()->toRfc4122(), $results[2]->getId()->toRfc4122()); // Beta/Planta
    }

    public function testFindByStayOrderedSortsPositionsWithoutWorkcenterFirst(): void
    {
        [$stay, $centre] = $this->makeChain('41000009');

        $compA = $this->makeCompany($centre, 'Alfa S.L.');
        $wcA1  = $this->makeWorkcenter($compA, 'Oficina');
        $this->persist($compA, $wcA1);

        $withWorkcenter    = $this->makePosition($stay)->setWorkcenter($wcA1);
        $withoutWorkcenter = $this->makePosition($stay); // workcenter = null
        $this->persist($withWorkcenter, $withoutWorkcenter);

        $results = $this->repo->findByStayOrdered($stay);

        self::assertCount(2, $results);
        // NULL company/workcenter sort first (LEFT JOIN → NULL < 'Alfa' en SQLite y PostgreSQL)
        self::assertNull($results[0]->getWorkcenter());
        self::assertNotNull($results[1]->getWorkcenter());
    }

    public function testFindByStayOrderedReturnsEmptyForStayWithNoPositions(): void
    {
        [$stay] = $this->makeChain('41000010');

        self::assertCount(0, $this->repo->findByStayOrdered($stay));
    }

    // ── findUnsignedWithStayEndingOn ─────────────────────────────────────────

    public function testFindUnsignedWithStayEndingOnMatchesExactDate(): void
    {
        [$stay] = $this->makeChain('41000020');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student);
        $this->persist($student, $position);

        $results = $this->repo->findUnsignedWithStayEndingOn($stay->getEndDate());

        self::assertCount(1, $results);
        self::assertSame($position->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testFindUnsignedWithStayEndingOnExcludesOtherDates(): void
    {
        [$stay] = $this->makeChain('41000021');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student);
        $this->persist($student, $position);

        $dayBefore = $stay->getEndDate()->modify('-1 day');

        self::assertCount(0, $this->repo->findUnsignedWithStayEndingOn($dayBefore));
    }

    public function testFindUnsignedWithStayEndingOnExcludesSignedPositions(): void
    {
        [$stay] = $this->makeChain('41000022');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setSigned(true);
        $this->persist($student, $position);

        self::assertCount(0, $this->repo->findUnsignedWithStayEndingOn($stay->getEndDate()));
    }

    public function testFindUnsignedWithStayEndingOnExcludesPositionsWithoutStudent(): void
    {
        [$stay] = $this->makeChain('41000023');
        $position = $this->makePosition($stay);
        $this->persist($position);

        self::assertCount(0, $this->repo->findUnsignedWithStayEndingOn($stay->getEndDate()));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Crea y persiste la cadena mínima para un Stay: Centre → Year → Family → Programme → Stay.
     * Devuelve [Stay, EducationalCentre] para tests que también necesiten crear empresas.
     *
     * @return array{Stay, EducationalCentre}
     */
    private function makeChain(string $centreCode): array
    {
        $centre    = $this->makeCentre($centreCode);
        $year      = $this->makeYear($centre);
        $family    = $this->makeFamily($year);
        $programme = $this->makeProgramme($year, $family);
        $stay      = $this->makeStay($year, $programme);
        $this->persist($centre, $year, $family, $programme, $stay);

        return [$stay, $centre];
    }

    private function makeCentre(string $code): EducationalCentre
    {
        return (new EducationalCentre())
            ->setCode($code)
            ->setName('IES ' . $code)
            ->setCity('Sevilla');
    }

    private function makeYear(EducationalCentre $centre): AcademicYear
    {
        return (new AcademicYear())
            ->setName('2024-2025')
            ->setEducationalCentre($centre);
    }

    private function makeFamily(AcademicYear $year): ProfessionalFamily
    {
        return (new ProfessionalFamily())
            ->setName('Informática')
            ->setAcademicYear($year);
    }

    private function makeProgramme(AcademicYear $year, ProfessionalFamily $family): Programme
    {
        return (new Programme())
            ->setName('DAM')
            ->setAcademicYear($year)
            ->setProfessionalFamily($family);
    }

    private function makeStay(AcademicYear $year, Programme $programme): Stay
    {
        return (new Stay())
            ->setName('FFEOE ' . uniqid())
            ->setAcademicYear($year)
            ->setProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('2025-03-01'))
            ->setEndDate(new \DateTimeImmutable('2025-06-30'));
    }

    private function makePosition(Stay $stay): TrainingPosition
    {
        return (new TrainingPosition())->setStay($stay);
    }

    private function makeStudent(string $studentId): Student
    {
        return (new Student(new PersonName('Test', 'Student')))->setStudentId($studentId);
    }

    private function makeCompany(EducationalCentre $centre, string $name): Company
    {
        return (new Company())
            ->setName($name)
            ->setVatNumber('B' . substr(md5($name), 0, 8))
            ->setCity('Sevilla')
            ->setEducationalCentre($centre);
    }

    private function makeWorkcenter(Company $company, string $name): Workcenter
    {
        return (new Workcenter())
            ->setName($name)
            ->setCity('Sevilla')
            ->setCompany($company);
    }
}
