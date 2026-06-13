<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Repository\StayRepository;
use App\Repository\TrainingPositionRepository;
use App\Service\PendingTasksProvider;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Clock\MockClock;

class PendingTasksProviderTest extends RepositoryTestCase
{
    private const NOW = '2026-06-13 10:00:00';

    private MockClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new MockClock(new \DateTimeImmutable(self::NOW));
    }

    // ── signature_due: ventana de 14 días ─────────────────────────────────────

    public function testSignatureDueIncludedAtWindowUpperBound(): void
    {
        [$year, $prog] = $this->makeChain('42000001');
        $teacher = $this->makeTeacher('tutor.due.1');
        // Hoy + 14 días exactos: límite superior incluido.
        $stay = $this->makeStay($year, $prog, 'FFEOE Due 14', '2026-03-01', '2026-06-27');
        $this->persist($teacher, $stay);
        $this->addTutoredUnsignedPosition($stay, $teacher, 'S-D1');

        $items = $this->signatureDue($year, $teacher);

        self::assertCount(1, $items);
        self::assertSame($stay->getId()->toRfc4122(), $items[0]['stay']->getId()->toRfc4122());
        self::assertSame(1, $items[0]['count']);
    }

    public function testSignatureDueExcludedBeyondWindow(): void
    {
        [$year, $prog] = $this->makeChain('42000002');
        $teacher = $this->makeTeacher('tutor.due.2');
        // Hoy + 15 días: fuera de la ventana.
        $stay = $this->makeStay($year, $prog, 'FFEOE Due 15', '2026-03-01', '2026-06-28');
        $this->persist($teacher, $stay);
        $this->addTutoredUnsignedPosition($stay, $teacher, 'S-D2');

        self::assertSame([], $this->signatureDue($year, $teacher));
    }

    public function testSignatureDueExcludedForPastEndDate(): void
    {
        [$year, $prog] = $this->makeChain('42000003');
        $teacher = $this->makeTeacher('tutor.due.3');
        // Ayer: anterior al límite inferior (hoy).
        $stay = $this->makeStay($year, $prog, 'FFEOE Due Past', '2026-01-01', '2026-06-12');
        $this->persist($teacher, $stay);
        $this->addTutoredUnsignedPosition($stay, $teacher, 'S-D3');

        self::assertSame([], $this->signatureDue($year, $teacher));
    }

    public function testSignatureDueExcludedWhenAlreadySigned(): void
    {
        [$year, $prog] = $this->makeChain('42000004');
        $teacher = $this->makeTeacher('tutor.due.4');
        $stay    = $this->makeStay($year, $prog, 'FFEOE Due Signed', '2026-03-01', '2026-06-20');
        $this->persist($teacher, $stay);
        $student  = (new Student(new PersonName('Eva', 'Firma')))->setStudentId('S-D4');
        $position = (new TrainingPosition())->setStay($stay)->setStudent($student)
            ->setAcademicTutor($teacher)->setSigned(true);
        $this->persist($student, $position);

        self::assertSame([], $this->signatureDue($year, $teacher));
    }

    public function testSignatureDueGroupedByStayWithCount(): void
    {
        [$year, $prog] = $this->makeChain('42000005');
        $teacher = $this->makeTeacher('tutor.due.5');
        $stay    = $this->makeStay($year, $prog, 'FFEOE Due Multi', '2026-03-01', '2026-06-20');
        $this->persist($teacher, $stay);
        $this->addTutoredUnsignedPosition($stay, $teacher, 'S-D5A');
        $this->addTutoredUnsignedPosition($stay, $teacher, 'S-D5B');

        $items = $this->signatureDue($year, $teacher);

        self::assertCount(1, $items);
        self::assertSame(2, $items[0]['count']);
    }

    public function testSignatureDueIsolatedByTeacher(): void
    {
        [$year, $prog] = $this->makeChain('42000006');
        $mine   = $this->makeTeacher('tutor.due.mine');
        $other  = $this->makeTeacher('tutor.due.other');
        $stay   = $this->makeStay($year, $prog, 'FFEOE Due Iso', '2026-03-01', '2026-06-20');
        $this->persist($mine, $other, $stay);
        $this->addTutoredUnsignedPosition($stay, $other, 'S-D6');

        self::assertSame([], $this->signatureDue($year, $mine));
    }

    // ── tipos de tarea derivados de las alertas ───────────────────────────────

    public function testFreePositionProducesFreePositionsItem(): void
    {
        [$year, $prog] = $this->makeChain('42000010');
        $admin = $this->makeTeacher('admin.alerts.10', admin: true);
        $stay  = $this->makeStay($year, $prog, 'FFEOE Libre', '-10 days', '+20 days');
        $this->persist($admin, $stay);
        $this->persist((new TrainingPosition())->setStay($stay)); // puesto libre

        $types = array_column($this->provider()->findPendingForTeacher($year, $admin), 'type');

        self::assertContains('free_positions', $types);
    }

    public function testStudentWithoutPositionProducesItem(): void
    {
        [$year, $prog, $stay] = $this->makeChain('42000011');
        $stay->setStartDate(new \DateTimeImmutable('-10 days'))->setEndDate(new \DateTimeImmutable('+20 days'));
        $admin   = $this->makeTeacher('admin.alerts.11', admin: true);
        $student = (new Student(new PersonName('Sin', 'Plaza')))->setStudentId('S-11');
        $this->persist($admin, $student);
        $stay->addStudent($student);
        $this->flush();

        $types = array_column($this->provider()->findPendingForTeacher($year, $admin), 'type');

        self::assertContains('students_without_position', $types);
    }

    // ── aislamiento y orden ────────────────────────────────────────────────────

    public function testTeacherWithoutRoleSeesNothing(): void
    {
        [$year, $prog] = $this->makeChain('42000020');
        $outsider = $this->makeTeacher('outsider.20');
        $stay     = $this->makeStay($year, $prog, 'FFEOE Ajena', '-10 days', '+20 days');
        $this->persist($outsider, $stay);
        $this->persist((new TrainingPosition())->setStay($stay));

        self::assertSame([], $this->provider()->findPendingForTeacher($year, $outsider));
    }

    public function testItemsOrderedByStayEndDate(): void
    {
        [$year, $prog] = $this->makeChain('42000021');
        $teacher = $this->makeTeacher('tutor.order.21');
        $late    = $this->makeStay($year, $prog, 'FFEOE Tarde',   '2026-03-01', '2026-06-25');
        $early   = $this->makeStay($year, $prog, 'FFEOE Pronto',  '2026-03-01', '2026-06-15');
        $this->persist($teacher, $late, $early);
        $this->addTutoredUnsignedPosition($late, $teacher, 'S-21L');
        $this->addTutoredUnsignedPosition($early, $teacher, 'S-21E');

        $items = $this->signatureDue($year, $teacher);

        self::assertCount(2, $items);
        self::assertSame($early->getId()->toRfc4122(), $items[0]['stay']->getId()->toRfc4122());
        self::assertSame($late->getId()->toRfc4122(), $items[1]['stay']->getId()->toRfc4122());
    }

    public function testReturnsEmptyForTeacherWithNoData(): void
    {
        [$year] = $this->makeChain('42000022');
        $teacher = $this->makeTeacher('tutor.empty.22');
        $this->persist($teacher);

        self::assertSame([], $this->provider()->findPendingForTeacher($year, $teacher));
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function provider(): PendingTasksProvider
    {
        /** @var StayRepository $stays */
        $stays = self::getContainer()->get(StayRepository::class);
        /** @var TrainingPositionRepository $positions */
        $positions = self::getContainer()->get(TrainingPositionRepository::class);

        return new PendingTasksProvider($stays, $positions, $this->clock);
    }

    /** @return list<array{type: string, stay: Stay, count: int}> */
    private function signatureDue(AcademicYear $year, Teacher $teacher): array
    {
        return array_values(array_filter(
            $this->provider()->findPendingForTeacher($year, $teacher),
            static fn (array $item): bool => $item['type'] === 'signature_due',
        ));
    }

    private function addTutoredUnsignedPosition(Stay $stay, Teacher $tutor, string $studentId): void
    {
        $student  = (new Student(new PersonName('Alu', $studentId)))->setStudentId($studentId);
        $position = (new TrainingPosition())->setStay($stay)->setStudent($student)
            ->setAcademicTutor($tutor)->setSigned(false);
        $this->persist($student, $position);
    }

    private function makeTeacher(string $username, bool $admin = false): Teacher
    {
        return (new Teacher(new PersonName('Nombre', 'Apellido')))
            ->setUsername($username)
            ->setAdmin($admin);
    }

    /**
     * @return array{AcademicYear, Programme, Stay}
     */
    private function makeChain(string $centreCode): array
    {
        $centre = (new EducationalCentre())->setCode($centreCode)->setName('IES ' . $centreCode)->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family = (new ProfessionalFamily())->setName('Informatica')->setAcademicYear($year);
        $prog   = (new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family);
        $stay   = $this->makeStay($year, $prog, 'FFEOE DAM ' . $centreCode);
        $this->persist($centre, $year, $family, $prog, $stay);

        return [$year, $prog, $stay];
    }

    private function makeStay(
        AcademicYear $year,
        Programme $programme,
        string $name,
        string $start = '2026-03-01',
        string $end = '2026-06-30',
    ): Stay {
        return (new Stay())
            ->setName($name)
            ->setAcademicYear($year)
            ->setProgramme($programme)
            ->setStartDate(new \DateTimeImmutable($start))
            ->setEndDate(new \DateTimeImmutable($end));
    }
}
