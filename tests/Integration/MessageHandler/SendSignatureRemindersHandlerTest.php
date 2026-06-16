<?php

declare(strict_types=1);

namespace App\Tests\Integration\MessageHandler;

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
use App\Message\SendSignatureRemindersMessage;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class SendSignatureRemindersHandlerTest extends RepositoryTestCase
{
    use MailerAssertionsTrait;

    public function testHandlerDispatchesRemindersAndSendsEmail(): void
    {
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        $tutor = (new Teacher(new PersonName('Luisa', 'Gomez')))
            ->setUsername('tutora.' . uniqid())
            ->setEmail('tutora@test.local');

        $family = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);
        $programme = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year);

        $stay = (new Stay())
            ->setName('Estancia DAW')
            ->setAcademicYear($year)
            ->setProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('+5 days'))
            ->setEndDate(new \DateTimeImmutable('+95 days'));

        $student  = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $position = (new TrainingPosition())
            ->setStay($stay)
            ->setStudent($student)
            ->setState(TrainingPositionState::DONE)
            ->setSigned(false)
            ->setAcademicTutor($tutor);

        $this->persist($centre, $year, $tutor, $family, $programme, $stay, $student, $position);

        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new SendSignatureRemindersMessage());

        self::assertEmailCount(1);
        self::assertNotNull($stay->getLastSignatureReminderSentAt());
    }
}
