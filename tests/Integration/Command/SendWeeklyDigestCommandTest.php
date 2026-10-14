<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SendWeeklyDigestCommand;
use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Entity\TrainingPositionState;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SendWeeklyDigestCommandTest extends RepositoryTestCase
{
    use MailerAssertionsTrait;

    public function testSendsOneDigestToWhoHasPendingTasksAndLinksToTheFilter(): void
    {
        [$tutor] = $this->makeWorld(withPendingTask: true);

        $status = $this->executeDigest();

        self::assertSame(Command::SUCCESS, $status);
        self::assertEmailCount(1);
        $email = self::getMailerMessage(0);
        self::assertEmailAddressContains($email, 'to', $tutor->getEmail());
        self::assertEmailHtmlBodyContains($email, 'filtro=sin-firmar');
        self::assertEmailHtmlBodyContains($email, 'Estancia DAW');
    }

    public function testSendsNothingWhenThereIsNothingPending(): void
    {
        $this->makeWorld(withPendingTask: false);

        $this->executeDigest();

        self::assertEmailCount(0);
    }

    public function testSkipsTeachersWithoutEmail(): void
    {
        [$tutor] = $this->makeWorld(withPendingTask: true);
        $tutor->setEmail(null);
        $this->flush();

        $this->executeDigest();

        self::assertEmailCount(0);
    }

    private function executeDigest(): int
    {
        /** @var SendWeeklyDigestCommand $command */
        $command = self::getContainer()->get(SendWeeklyDigestCommand::class);

        return (new CommandTester($command))->execute([]);
    }

    /** @return array{0: Teacher} */
    private function makeWorld(bool $withPendingTask): array
    {
        $centre = (new EducationalCentre())->setCode('41000009')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $tutor  = (new Teacher(new PersonName('Luisa', 'Gomez')))->setUsername('tutora.' . uniqid())->setEmail('tutora@test.local');
        $tutor->addAcademicYear($year);
        $family    = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);
        $stay      = (new Stay())
            ->setName('Estancia DAW')
            ->setAcademicYear($year)
            ->addProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('-30 days'))
            ->setEndDate(new \DateTimeImmutable('+5 days'));

        $this->persist($centre, $year, $tutor, $family, $programme, $stay);
        $centre->setActiveAcademicYear($year);

        if ($withPendingTask) {
            $student  = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
            $position = (new TrainingPosition())
                ->setStay($stay)
                ->setStudent($student)
                ->setState(TrainingPositionState::DONE)
                ->setSigned(false)
                ->setAcademicTutor($tutor);
            $this->persist($student, $position);
        }
        $this->flush();

        return [$tutor];
    }
}
