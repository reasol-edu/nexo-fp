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
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
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

    // ── findRegisteredUnsignedStartingWithinForCentre ────────────────────────

    public function testFindRegisteredUnsignedStartingWithinMatchesRegisteredUnsigned(): void
    {
        [$stay, $centre] = $this->makeChain('41000020');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setState(TrainingPositionState::DONE);
        $this->persist($student, $position);

        $results = $this->repo->findRegisteredUnsignedStartingWithinForCentre(
            $centre,
            $stay->getStartDate(),
        );

        self::assertCount(1, $results);
        self::assertSame($position->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testFindRegisteredUnsignedStartingWithinIncludesAlreadyStarted(): void
    {
        [$stay, $centre] = $this->makeChain('41000021');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setState(TrainingPositionState::DONE);
        $this->persist($student, $position);

        // El límite es muy posterior al inicio (estancia ya comenzada): sigue avisando.
        $limit = $stay->getStartDate()->modify('+60 days');

        self::assertCount(1, $this->repo->findRegisteredUnsignedStartingWithinForCentre($centre, $limit));
    }

    public function testFindRegisteredUnsignedStartingWithinExcludesBeyondLimit(): void
    {
        [$stay, $centre] = $this->makeChain('41000022');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setState(TrainingPositionState::DONE);
        $this->persist($student, $position);

        $dayBefore = $stay->getStartDate()->modify('-1 day');

        self::assertCount(0, $this->repo->findRegisteredUnsignedStartingWithinForCentre($centre, $dayBefore));
    }

    public function testFindRegisteredUnsignedStartingWithinExcludesSignedPositions(): void
    {
        [$stay, $centre] = $this->makeChain('41000023');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)
            ->setStudent($student)
            ->setState(TrainingPositionState::DONE)
            ->setSigned(true);
        $this->persist($student, $position);

        self::assertCount(0, $this->repo->findRegisteredUnsignedStartingWithinForCentre($centre, $stay->getStartDate()));
    }

    public function testFindRegisteredUnsignedStartingWithinExcludesNonRegisteredStates(): void
    {
        [$stay, $centre] = $this->makeChain('41000024');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setState(TrainingPositionState::PENDING);
        $this->persist($student, $position);

        self::assertCount(0, $this->repo->findRegisteredUnsignedStartingWithinForCentre($centre, $stay->getStartDate()));
    }

    public function testFindRegisteredUnsignedStartingWithinExcludesOtherCentres(): void
    {
        [$stay] = $this->makeChain('41000025');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setState(TrainingPositionState::DONE);
        $this->persist($student, $position);

        $otherCentre = $this->makeCentre('41000026');
        $this->persist($otherCentre);

        self::assertCount(0, $this->repo->findRegisteredUnsignedStartingWithinForCentre($otherCentre, $stay->getStartDate()));
    }

    // ── findUnsignedByTutorWithStayEndingBetween ─────────────────────────────

    public function testFindUnsignedByTutorWithStayEndingBetweenMatchesWindow(): void
    {
        [$stay] = $this->makeChain('41000030');
        $tutor    = $this->makeTeacher('tutor.window.1');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setAcademicTutor($tutor);
        $this->persist($tutor, $student, $position);

        // endDate de makeStay: 2025-06-30
        $results = $this->repo->findUnsignedByTutorWithStayEndingBetween(
            $tutor,
            new \DateTimeImmutable('2025-06-20'),
            new \DateTimeImmutable('2025-07-04'),
        );

        self::assertCount(1, $results);
        self::assertSame($position->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testFindUnsignedByTutorWithStayEndingBetweenExcludesOutsideWindow(): void
    {
        [$stay] = $this->makeChain('41000031');
        $tutor    = $this->makeTeacher('tutor.window.2');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setAcademicTutor($tutor);
        $this->persist($tutor, $student, $position);

        $results = $this->repo->findUnsignedByTutorWithStayEndingBetween(
            $tutor,
            new \DateTimeImmutable('2025-07-01'),
            new \DateTimeImmutable('2025-07-15'),
        );

        self::assertCount(0, $results);
    }

    public function testFindUnsignedByTutorWithStayEndingBetweenExcludesOtherTutors(): void
    {
        [$stay] = $this->makeChain('41000032');
        $tutor    = $this->makeTeacher('tutor.window.3');
        $other    = $this->makeTeacher('tutor.window.4');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setAcademicTutor($other);
        $this->persist($tutor, $other, $student, $position);

        $results = $this->repo->findUnsignedByTutorWithStayEndingBetween(
            $tutor,
            new \DateTimeImmutable('2025-06-01'),
            new \DateTimeImmutable('2025-07-31'),
        );

        self::assertCount(0, $results);
    }

    public function testFindUnsignedByTutorWithStayEndingBetweenExcludesSigned(): void
    {
        [$stay] = $this->makeChain('41000033');
        $tutor    = $this->makeTeacher('tutor.window.5');
        $student  = $this->makeStudent('2024-001');
        $position = $this->makePosition($stay)->setStudent($student)->setAcademicTutor($tutor)->setSigned(true);
        $this->persist($tutor, $student, $position);

        $results = $this->repo->findUnsignedByTutorWithStayEndingBetween(
            $tutor,
            new \DateTimeImmutable('2025-06-01'),
            new \DateTimeImmutable('2025-07-31'),
        );

        self::assertCount(0, $results);
    }

    // ── createPendingSignaturesQuery ─────────────────────────────────────────

    public function testPendingQueryReturnsDoneUnsigned(): void
    {
        [$stay, , $year] = $this->makeChainWithYear('43000001');
        $student  = $this->makeStudent('2024-ps-01');
        $position = $this->makePosition($stay)
            ->setStudent($student)
            ->setState(TrainingPositionState::DONE);
        $this->persist($student, $position);

        $results = $this->repo->createPendingSignaturesQuery($year)->getResult();

        self::assertCount(1, $results);
        self::assertSame($position->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testPendingQueryExcludesDraftState(): void
    {
        [$stay, , $year] = $this->makeChainWithYear('43000002');
        $student  = $this->makeStudent('2024-ps-02');
        $position = $this->makePosition($stay)
            ->setStudent($student)
            ->setState(TrainingPositionState::DRAFT);
        $this->persist($student, $position);

        self::assertCount(0, $this->repo->createPendingSignaturesQuery($year)->getResult());
    }

    public function testPendingQueryExcludesPendingState(): void
    {
        [$stay, , $year] = $this->makeChainWithYear('43000003');
        $student  = $this->makeStudent('2024-ps-03');
        $position = $this->makePosition($stay)
            ->setStudent($student)
            ->setState(TrainingPositionState::PENDING);
        $this->persist($student, $position);

        self::assertCount(0, $this->repo->createPendingSignaturesQuery($year)->getResult());
    }

    public function testPendingQueryExcludesSignedPositions(): void
    {
        [$stay, , $year] = $this->makeChainWithYear('43000004');
        $student  = $this->makeStudent('2024-ps-04');
        $position = $this->makePosition($stay)
            ->setStudent($student)
            ->setState(TrainingPositionState::DONE)
            ->setSigned(true);
        $this->persist($student, $position);

        self::assertCount(0, $this->repo->createPendingSignaturesQuery($year)->getResult());
    }

    public function testPendingQuerySearchByStudentName(): void
    {
        [$stay, , $year] = $this->makeChainWithYear('43000005');
        $studentA = $this->makeStudentNamed('García', 'Ana', '2024-ps-05a');
        $studentB = $this->makeStudentNamed('López', 'Luis', '2024-ps-05b');
        $posA = $this->makePosition($stay)->setStudent($studentA)->setState(TrainingPositionState::DONE);
        $posB = $this->makePosition($stay)->setStudent($studentB)->setState(TrainingPositionState::DONE);
        $this->persist($studentA, $studentB, $posA, $posB);

        $results = $this->repo->createPendingSignaturesQuery($year, 'García')->getResult();

        self::assertCount(1, $results);
        self::assertSame($posA->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testPendingQuerySearchByStayName(): void
    {
        [$stayA, , $year] = $this->makeChainWithYear('43000006');
        $centre = $year->getEducationalCentre();
        $fam    = $this->makeFamily($year);
        $prog   = $this->makeProgramme($year, $fam);
        $stayB  = (new Stay())->setName('Estancia XYZ')->setAcademicYear($year)->setProgramme($prog)
            ->setStartDate(new \DateTimeImmutable('2025-03-01'))->setEndDate(new \DateTimeImmutable('2025-06-30'));
        $this->persist($fam, $prog, $stayB);

        $stA   = $this->makeStudent('2024-ps-06a');
        $stB   = $this->makeStudent('2024-ps-06b');
        $posA  = $this->makePosition($stayA)->setStudent($stA)->setState(TrainingPositionState::DONE);
        $posB  = $this->makePosition($stayB)->setStudent($stB)->setState(TrainingPositionState::DONE);
        $this->persist($stA, $stB, $posA, $posB);

        $results = $this->repo->createPendingSignaturesQuery($year, 'XYZ')->getResult();

        self::assertCount(1, $results);
        self::assertSame($posB->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
    }

    public function testPendingQueryPeriodFilterExcludesPast(): void
    {
        $centre  = $this->makeCentre('43000007');
        $year    = $this->makeYear($centre);
        $family  = $this->makeFamily($year);
        $prog    = $this->makeProgramme($year, $family);

        // Estancia actual (incluye hoy)
        $currentStay = (new Stay())
            ->setName('Actual ' . uniqid())
            ->setAcademicYear($year)
            ->setProgramme($prog)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable('+30 days'));

        // Estancia pasada (terminó hace tiempo)
        $pastStay = (new Stay())
            ->setName('Pasada ' . uniqid())
            ->setAcademicYear($year)
            ->setProgramme($prog)
            ->setStartDate(new \DateTimeImmutable('2020-01-01'))
            ->setEndDate(new \DateTimeImmutable('2020-06-30'));

        $this->persist($centre, $year, $family, $prog, $currentStay, $pastStay);

        $student1 = $this->makeStudent('2024-ps-07a');
        $student2 = $this->makeStudent('2024-ps-07b');
        $posC = $this->makePosition($currentStay)->setStudent($student1)->setState(TrainingPositionState::DONE);
        $posP = $this->makePosition($pastStay)->setStudent($student2)->setState(TrainingPositionState::DONE);
        $this->persist($student1, $student2, $posC, $posP);

        $results = $this->repo->createPendingSignaturesQuery(
            $year, '', '', '', ['current', 'future']
        )->getResult();

        $ids = array_map(static fn ($p) => $p->getId()->toRfc4122(), $results);
        self::assertContains($posC->getId()->toRfc4122(), $ids);
        self::assertNotContains($posP->getId()->toRfc4122(), $ids);
    }

    public function testPendingQuerySortByStudent(): void
    {
        [$stay, , $year] = $this->makeChainWithYear('43000008');
        $stB = $this->makeStudentNamed('Cabello', 'Carlos', '2024-ps-08b');
        $stA = $this->makeStudentNamed('Amador', 'Maria', '2024-ps-08a');
        $posB = $this->makePosition($stay)->setStudent($stB)->setState(TrainingPositionState::DONE);
        $posA = $this->makePosition($stay)->setStudent($stA)->setState(TrainingPositionState::DONE);
        $this->persist($stA, $stB, $posA, $posB);

        $results = $this->repo->createPendingSignaturesQuery(
            $year, '', '', '', ['current', 'future', 'past'], 'student', 'ASC'
        )->getResult();

        self::assertCount(2, $results);
        self::assertSame($posA->getId()->toRfc4122(), $results[0]->getId()->toRfc4122());
        self::assertSame($posB->getId()->toRfc4122(), $results[1]->getId()->toRfc4122());
    }

    public function testPendingQueryExcludesOtherYear(): void
    {
        [$stay, $centre, $year] = $this->makeChainWithYear('43000009');
        $otherYear = (new AcademicYear())->setName('2023-2024')->setEducationalCentre($centre);
        $fam2      = $this->makeFamily($otherYear);
        $prog2     = $this->makeProgramme($otherYear, $fam2);
        $stay2     = (new Stay())->setName('Otra año ' . uniqid())->setAcademicYear($otherYear)
            ->setProgramme($prog2)->setStartDate(new \DateTimeImmutable('2024-03-01'))
            ->setEndDate(new \DateTimeImmutable('2024-06-30'));
        $this->persist($otherYear, $fam2, $prog2, $stay2);

        $st1 = $this->makeStudent('2024-ps-09a');
        $st2 = $this->makeStudent('2024-ps-09b');
        $pos1 = $this->makePosition($stay)->setStudent($st1)->setState(TrainingPositionState::DONE);
        $pos2 = $this->makePosition($stay2)->setStudent($st2)->setState(TrainingPositionState::DONE);
        $this->persist($st1, $st2, $pos1, $pos2);

        $results = $this->repo->createPendingSignaturesQuery($year)->getResult();

        $ids = array_map(static fn ($p) => $p->getId()->toRfc4122(), $results);
        self::assertContains($pos1->getId()->toRfc4122(), $ids);
        self::assertNotContains($pos2->getId()->toRfc4122(), $ids);
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

    private function makeTeacher(string $username): Teacher
    {
        return (new Teacher(new PersonName('Test', 'Teacher')))->setUsername($username);
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

    /**
     * Crea y persiste la cadena mínima devolviendo también el AcademicYear.
     *
     * @return array{Stay, EducationalCentre, AcademicYear}
     */
    private function makeChainWithYear(string $centreCode): array
    {
        $centre    = $this->makeCentre($centreCode);
        $year      = $this->makeYear($centre);
        $family    = $this->makeFamily($year);
        $programme = $this->makeProgramme($year, $family);
        $stay      = $this->makeStay($year, $programme);
        $this->persist($centre, $year, $family, $programme, $stay);

        return [$stay, $centre, $year];
    }

    private function makeStudentNamed(string $lastName, string $firstName, string $studentId): Student
    {
        return (new Student(new PersonName($firstName, $lastName)))->setStudentId($studentId);
    }
}
