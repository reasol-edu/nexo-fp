<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SendRemindersCommand;
use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SendRemindersCommandTest extends RepositoryTestCase
{
    use MailerAssertionsTrait;

    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SendRemindersCommand $command */
        $command = self::getContainer()->get(SendRemindersCommand::class);

        $this->tester = new CommandTester($command);
    }

    public function testSendsReminderToTutorOfUnsignedPosition(): void
    {
        $this->makeScenario(daysUntilEnd: 7, tutorEmail: 'tutora@test.local');

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(1);

        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'to', 'tutora@test.local');
        self::assertEmailHtmlBodyContains($email, 'Martinez');
    }

    public function testSendsNothingWhenStayEndsOnAnotherDate(): void
    {
        $this->makeScenario(daysUntilEnd: 10, tutorEmail: 'tutora@test.local');

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(0);
    }

    public function testWarnsAboutPositionsWithoutTutor(): void
    {
        $this->makeScenario(daysUntilEnd: 7, tutorEmail: null);

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(0);
        self::assertStringContainsString('no se puede enviar', $this->tester->getDisplay());
    }

    public function testDefaultsToSevenDays(): void
    {
        $this->makeScenario(daysUntilEnd: 7, tutorEmail: 'tutora@test.local');

        $status = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(1);
    }

    public function testFailsWithInvalidDaysOption(): void
    {
        $status = $this->tester->execute(['--days' => 'muchos']);

        self::assertSame(Command::FAILURE, $status);
        self::assertEmailCount(0);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Crea una estancia que termina en N días con un puesto sin firmar. */
    private function makeScenario(int $daysUntilEnd, ?string $tutorEmail): void
    {
        $centre    = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year      = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);

        $stay = (new Stay())
            ->setName('Estancia DAW ' . uniqid())
            ->setAcademicYear($year)
            ->setProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable(sprintf('+%d days', $daysUntilEnd)));

        $student  = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $position = (new TrainingPosition())->setStay($stay)->setStudent($student)->setSigned(false);

        $entities = [$centre, $year, $family, $programme, $stay, $student, $position];

        if ($tutorEmail !== null) {
            $tutor = (new Teacher(new PersonName('Luisa', 'Gomez')))
                ->setUsername('tutora.' . uniqid())
                ->setEmail($tutorEmail);
            $position->setAcademicTutor($tutor);
            $entities[] = $tutor;
        }

        $this->persist(...$entities);
    }
}
