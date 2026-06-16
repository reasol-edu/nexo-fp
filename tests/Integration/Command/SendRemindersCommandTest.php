<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SendRemindersCommand;
use App\Entity\AcademicYear;
use App\Entity\CentreSettingValue;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\SettingDefinition;
use App\Entity\SettingType;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Address;

class SendRemindersCommandTest extends RepositoryTestCase
{
    use MailerAssertionsTrait;

    private const SETTING_DAYS = 'email.notification.signature_reminder.days';

    private CommandTester $tester;
    private EducationalCentre $centre;
    private AcademicYear $year;
    private ProfessionalFamily $family;
    private Programme $programme;
    private Teacher $coordinator;
    private Teacher $familyHead;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SendRemindersCommand $command */
        $command = self::getContainer()->get(SendRemindersCommand::class);
        $this->tester = new CommandTester($command);

        $this->makeWorld();
    }

    public function testNotifiesTutorCoordinatorAndFamilyHead(): void
    {
        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        $this->makePosition(startsInDays: 5, tutor: $tutor);

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(3);

        $recipients = $this->sentToAddresses();
        self::assertContains('tutora@test.local', $recipients);
        self::assertContains('coord@test.local', $recipients);
        self::assertContains('jefa@test.local', $recipients);
    }

    public function testTutorSeesOnlyOwnStudentsWhileCoordinatorSeesAll(): void
    {
        $tutorA = $this->makeTeacher('Luisa', 'Gomez', 'tutora.a@test.local');
        $tutorB = $this->makeTeacher('Marta', 'Ruiz', 'tutora.b@test.local');

        $this->makePosition(startsInDays: 5, tutor: $tutorA, studentName: new PersonName('Ana', 'Alonso'));
        $this->makePosition(startsInDays: 6, tutor: $tutorB, studentName: new PersonName('Beto', 'Bravo'));

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        // tutorA, tutorB, coordinator, familyHead
        self::assertEmailCount(4);

        $byRecipient = $this->bodiesByRecipient();

        self::assertStringContainsString('Alonso', $byRecipient['tutora.a@test.local']);
        self::assertStringNotContainsString('Bravo', $byRecipient['tutora.a@test.local']);

        self::assertStringContainsString('Bravo', $byRecipient['tutora.b@test.local']);
        self::assertStringNotContainsString('Alonso', $byRecipient['tutora.b@test.local']);

        self::assertStringContainsString('Alonso', $byRecipient['coord@test.local']);
        self::assertStringContainsString('Bravo', $byRecipient['coord@test.local']);

        self::assertStringContainsString('Alonso', $byRecipient['jefa@test.local']);
        self::assertStringContainsString('Bravo', $byRecipient['jefa@test.local']);
    }

    public function testIgnoresPendingPositions(): void
    {
        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        $this->makePosition(startsInDays: 5, tutor: $tutor, state: TrainingPositionState::PENDING);

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(0);
    }

    public function testIgnoresSignedPositions(): void
    {
        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        $this->makePosition(startsInDays: 5, tutor: $tutor, signed: true);

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(0);
    }

    public function testIgnoresStaysStartingBeyondWindow(): void
    {
        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        $this->makePosition(startsInDays: 30, tutor: $tutor);

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(0);
    }

    public function testIncludesAlreadyStartedUnsignedStays(): void
    {
        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        $this->makePosition(startsInDays: -10, tutor: $tutor);

        $status = $this->tester->execute(['--days' => '7']);

        self::assertSame(Command::SUCCESS, $status);
        // tutor + coordinator + family head
        self::assertEmailCount(3);
    }

    public function testIsIdempotentWithinSameDay(): void
    {
        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        $stay  = $this->makePosition(startsInDays: 5, tutor: $tutor);

        $this->tester->execute(['--days' => '7']);
        self::assertEmailCount(3);

        // Segunda ejecución el mismo día: la estancia ya está sellada → sin reenvío.
        $this->tester->execute(['--days' => '7']);
        self::assertEmailCount(3);

        self::assertNotNull($stay->getLastSignatureReminderSentAt());
    }

    public function testSealsStayAfterSending(): void
    {
        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        $stay  = $this->makePosition(startsInDays: 5, tutor: $tutor);

        self::assertNull($stay->getLastSignatureReminderSentAt());

        $this->tester->execute(['--days' => '7']);

        self::assertNotNull($stay->getLastSignatureReminderSentAt());
    }

    public function testDaysOverrideExpandsWindow(): void
    {
        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        $this->makePosition(startsInDays: 20, tutor: $tutor);

        // Con la ventana por defecto (7) no entraría; con override 30 sí.
        $status = $this->tester->execute(['--days' => '30']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(3);
    }

    public function testUsesCentreSettingWhenNoOverride(): void
    {
        $this->seedDaysSetting(centreValue: '14');

        $tutor = $this->makeTeacher('Luisa', 'Gomez', 'tutora@test.local');
        // Empieza en +10: fuera del default 7 pero dentro del ajuste de centro (14).
        $this->makePosition(startsInDays: 10, tutor: $tutor);

        $status = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(3);
    }

    public function testFailsWithNonIntegerDaysOption(): void
    {
        $status = $this->tester->execute(['--days' => 'muchos']);

        self::assertSame(Command::FAILURE, $status);
        self::assertEmailCount(0);
    }

    public function testFailsWithZeroDaysOption(): void
    {
        $status = $this->tester->execute(['--days' => '0']);

        self::assertSame(Command::FAILURE, $status);
        self::assertEmailCount(0);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeWorld(): void
    {
        $this->centre      = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $this->year        = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($this->centre);
        $this->coordinator = $this->makeTeacher('Carmen', 'Coord', 'coord@test.local');
        $this->familyHead  = $this->makeTeacher('Julia', 'Jefa', 'jefa@test.local');

        $this->family = (new ProfessionalFamily())
            ->setName('Informática')
            ->setAcademicYear($this->year)
            ->setHead($this->familyHead);

        $this->programme = (new Programme())
            ->setName('DAW')
            ->setProfessionalFamily($this->family)
            ->setAcademicYear($this->year);
        $this->programme->addCoordinator($this->coordinator);

        $this->persist($this->centre, $this->year, $this->coordinator, $this->familyHead, $this->family, $this->programme);
    }

    private function makeTeacher(string $first, string $last, string $email): Teacher
    {
        $teacher = (new Teacher(new PersonName($first, $last)))
            ->setUsername(strtolower($last) . '.' . uniqid())
            ->setEmail($email);

        $this->em->persist($teacher);

        return $teacher;
    }

    private function makePosition(
        int $startsInDays,
        ?Teacher $tutor = null,
        TrainingPositionState $state = TrainingPositionState::DONE,
        bool $signed = false,
        ?PersonName $studentName = null,
    ): Stay {
        $stay = (new Stay())
            ->setName('Estancia DAW ' . uniqid())
            ->setAcademicYear($this->year)
            ->setProgramme($this->programme)
            ->setStartDate(new \DateTimeImmutable(sprintf('%+d days', $startsInDays)))
            ->setEndDate(new \DateTimeImmutable(sprintf('%+d days', $startsInDays + 90)));

        $student = (new Student($studentName ?? new PersonName('Ana', 'Martinez')))
            ->setStudentId('2024-' . uniqid());

        $position = (new TrainingPosition())
            ->setStay($stay)
            ->setStudent($student)
            ->setState($state)
            ->setSigned($signed);

        if ($tutor !== null) {
            $position->setAcademicTutor($tutor);
        }

        $this->persist($stay, $student, $position);

        return $stay;
    }

    private function seedDaysSetting(string $centreValue): void
    {
        $def = (new SettingDefinition())
            ->setKey(self::SETTING_DAYS)
            ->setType(SettingType::Integer)
            ->setDefaultValue('7')
            ->setGlobalScope(true)
            ->setCentreScope(true)
            ->setTeacherScope(false)
            ->setMinValue(1)
            ->setMaxValue(365);

        $value = (new CentreSettingValue())
            ->setDefinition($def)
            ->setCentre($this->centre)
            ->setValue($centreValue)
            ->setLocked(false);

        $this->persist($def, $value);
    }

    /** @return list<string> */
    private function sentToAddresses(): array
    {
        $addresses = [];
        foreach (self::getMailerMessages() as $message) {
            foreach ($message->getTo() as $address) {
                /** @var Address $address */
                $addresses[] = $address->getAddress();
            }
        }

        return $addresses;
    }

    /** @return array<string, string> recipient address → concatenated HTML bodies */
    private function bodiesByRecipient(): array
    {
        $bodies = [];
        foreach (self::getMailerMessages() as $message) {
            $body = (string) $message->getHtmlBody();
            foreach ($message->getTo() as $address) {
                /** @var Address $address */
                $bodies[$address->getAddress()] = ($bodies[$address->getAddress()] ?? '') . $body;
            }
        }

        return $bodies;
    }
}
