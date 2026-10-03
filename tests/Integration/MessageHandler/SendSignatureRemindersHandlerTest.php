<?php

declare(strict_types=1);

namespace App\Tests\Integration\MessageHandler;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\ProfessionalFamily;
use App\Entity\Programme;
use App\Entity\ProgrammeYear;
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
            ->addProgramme($programme)
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

        // El envío queda anotado en el registro de correos (el worker no tiene sesión ni centro seleccionado).
        $this->em->clear();
        $entries = $this->em->getRepository(\App\Entity\EmailNotificationLog::class)->findAll();
        self::assertCount(1, $entries);
        self::assertSame('signature_reminder', $entries[0]->getEventKey());
        self::assertSame('tutora@test.local', $entries[0]->getRecipientEmail());
        self::assertSame('IES Test', $entries[0]->getEducationalCentre()?->getName());
        self::assertTrue($entries[0]->isSuccess());
    }

    public function testSharedStayRemindsOnlyTheCoordinatorOfTheStudentsProgramme(): void
    {
        $centre = (new EducationalCentre())->setCode('41000002')->setName('IES Test')->setCity('Sevilla');
        $year   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);
        $family = (new ProfessionalFamily())->setName('Informática')->setAcademicYear($year);

        $coordDaw = (new Teacher(new PersonName('Coord', 'Daw')))->setUsername('coord.daw.' . uniqid())->setEmail('daw@test.local');
        $coordDam = (new Teacher(new PersonName('Coord', 'Dam')))->setUsername('coord.dam.' . uniqid())->setEmail('dam@test.local');
        $daw = (new Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($year)->addCoordinator($coordDaw);
        $dam = (new Programme())->setName('DAM')->setProfessionalFamily($family)->setAcademicYear($year)->addCoordinator($coordDam);

        $dawLevel = (new ProgrammeYear())->setName('2.º DAW')->setProgramme($daw);
        $dawGroup = (new Group())->setName('DAW2A')->setProgrammeYear($dawLevel);
        $student  = (new Student(new PersonName('Ana', 'Martinez')))->setStudentId('2024-001');
        $dawGroup->addStudent($student);

        $stay = (new Stay())
            ->setName('Estancia compartida')
            ->setAcademicYear($year)
            ->addProgramme($daw)
            ->addProgramme($dam)
            ->setStartDate(new \DateTimeImmutable('+5 days'))
            ->setEndDate(new \DateTimeImmutable('+95 days'));
        $stay->addStudent($student);

        $position = (new TrainingPosition())
            ->setStay($stay)
            ->setStudent($student)
            ->setState(TrainingPositionState::DONE)
            ->setSigned(false);

        $this->persist($centre, $year, $coordDaw, $coordDam, $family, $daw, $dam, $dawLevel, $dawGroup, $student, $stay, $position);

        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new SendSignatureRemindersMessage());

        self::assertEmailCount(1);
        $recipients = array_values(array_unique(array_map(
            static fn ($email): string => $email->getTo()[0]->getAddress(),
            $this->getMailerMessages(),
        )));
        self::assertSame(['daw@test.local'], $recipients, 'Solo la coordinación de la enseñanza del alumno recibe el aviso');
    }
}
