<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

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
use App\Entity\TrainingPosition;
use App\Entity\Workcenter;
use App\Service\AppSettingsInterface;
use App\Service\EmailNotificationRecorder;
use App\Service\StayNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Avisos a las coordinaciones de las demás enseñanzas cuando cambia un puesto compartido. */
class StayNotifierSharedPositionTest extends TestCase
{
    public function testTakenPositionNotifiesCoordinatorsOfTheOtherOfferedProgrammes(): void
    {
        [$stay, $position, $dawCoord, $damCoord, $dawStudent] = $this->scenario();

        $sent = [];
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (TemplatedEmail $email) use (&$sent): void {
            $sent[] = $email;
        });

        $this->notifier($mailer)->notifySharedPositionTaken($position, $dawStudent, $dawCoord);

        self::assertCount(1, $sent);
        self::assertSame('dam@test.local', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('email/shared_position.html.twig', $sent[0]->getHtmlTemplate());
        self::assertSame('emails.shared_position.subject.taken', $sent[0]->getSubject());
        self::assertSame('taken', $sent[0]->getContext()['change']);
        self::assertSame($damCoord, $sent[0]->getContext()['recipient']);
    }

    public function testTakenPositionDoesNotNotifyTheCoordinatorOfTheStudentsProgramme(): void
    {
        [, $position, $dawCoord, , $dawStudent] = $this->scenario();
        $another = $this->teacher('Otra', 'Daw', 'otra@test.local');
        $position->getProgrammeYears()->first()->getProgramme()->addCoordinator($another);

        $recipients = [];
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (TemplatedEmail $email) use (&$recipients): void {
            $recipients[] = $email->getTo()[0]->getAddress();
        });

        // Quien asigna es la coordinación de DAW; otra coordinación de DAW tampoco se entera (es de la enseñanza del alumno).
        $this->notifier($mailer)->notifySharedPositionTaken($position, $dawStudent, $dawCoord);

        self::assertNotContains('otra@test.local', $recipients);
    }

    public function testPositionOfferedToASingleProgrammeNotifiesNobody(): void
    {
        [$stay, , $dawCoord, , $dawStudent] = $this->scenario();
        $dawLevel = $dawStudent->getGroups()->first()->getProgrammeYear();
        $position = (new TrainingPosition())->setStay($stay)->addProgrammeYear($dawLevel);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->notifier($mailer)->notifySharedPositionTaken($position, $dawStudent, $dawCoord);
    }

    public function testRemovedPositionNotifiesCoordinatorsOfProgrammesTheActorDoesNotManage(): void
    {
        [$stay, $position, $dawCoord, $damCoord] = $this->scenario();
        $daw = $position->getProgrammeYears()->first()->getProgramme();

        $sent = [];
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (TemplatedEmail $email) use (&$sent): void {
            $sent[] = $email;
        });

        $this->notifier($mailer)->notifySharedPositionRemoved($position, $dawCoord, [$daw]);

        self::assertCount(1, $sent);
        self::assertSame('dam@test.local', $sent[0]->getTo()[0]->getAddress());
        self::assertSame('removed', $sent[0]->getContext()['change']);
        self::assertSame('emails.shared_position.subject.removed', $sent[0]->getSubject());
    }

    public function testRemovedPositionOfASingleProgrammeNotifiesNobodyElse(): void
    {
        [$stay, , $dawCoord, , $dawStudent] = $this->scenario();
        $dawLevel = $dawStudent->getGroups()->first()->getProgrammeYear();
        $position = (new TrainingPosition())->setStay($stay)->addProgrammeYear($dawLevel);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->notifier($mailer)->notifySharedPositionRemoved($position, $dawCoord, [$dawLevel->getProgramme()]);
    }

    public function testRespectsTheRecipientsNotificationPreference(): void
    {
        [, $position, $dawCoord, , $dawStudent] = $this->scenario();

        $settings = $this->createStub(AppSettingsInterface::class);
        $settings->method('getForTeacher')->willReturnCallback(
            static fn (string $key): bool => $key !== 'email.notification.shared_position'
        );
        $settings->method('getForCentre')->willReturn('');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->notifier($mailer, $settings)->notifySharedPositionTaken($position, $dawStudent, $dawCoord);
    }

    public function testCoordinatorWithoutEmailIsSkipped(): void
    {
        [, $position, $dawCoord, $damCoord, $dawStudent] = $this->scenario();
        $damCoord->setEmail(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->notifier($mailer)->notifySharedPositionTaken($position, $dawStudent, $dawCoord);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{0: Stay, 1: TrainingPosition, 2: Teacher, 3: Teacher, 4: Student} */
    private function scenario(): array
    {
        $centre = $this->withId((new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla'));
        $year   = $this->withId((new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre));
        $family = $this->withId((new ProfessionalFamily())->setName('Informática')->setAcademicYear($year));

        $dawCoord = $this->teacher('Coord', 'Daw', 'daw@test.local');
        $damCoord = $this->teacher('Coord', 'Dam', 'dam@test.local');
        $daw = $this->withId((new Programme())->setName('DAW')->setAcademicYear($year)->setProfessionalFamily($family)->addCoordinator($dawCoord));
        $dam = $this->withId((new Programme())->setName('DAM')->setAcademicYear($year)->setProfessionalFamily($family)->addCoordinator($damCoord));

        $dawLevel = $this->withId((new ProgrammeYear())->setName('2.º DAW')->setProgramme($daw));
        $damLevel = $this->withId((new ProgrammeYear())->setName('2.º DAM')->setProgramme($dam));

        $dawStudent = $this->withId((new Student(new PersonName('Ana', 'Daw')))->setStudentId('S-1'));
        $dawStudent->addGroup($this->withId((new Group())->setName('DAW2A')->setProgrammeYear($dawLevel)));

        $stay = $this->withId((new Stay())->setName('FFEOE')->setAcademicYear($year)->addProgramme($daw)->addProgramme($dam));

        $company    = (new Company())->setName('Empresa Test')->setVatNumber('B1');
        $workcenter = (new Workcenter())->setName('Sede')->setCompany($company);
        $position   = $this->withId((new TrainingPosition())->setStay($stay)->setWorkcenter($workcenter)
            ->addProgrammeYear($dawLevel)->addProgrammeYear($damLevel));

        return [$stay, $position, $dawCoord, $damCoord, $dawStudent];
    }

    private function notifier(MailerInterface $mailer, ?AppSettingsInterface $settings = null): StayNotifier
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/estancias/test');

        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        if ($settings === null) {
            $settings = $this->createStub(AppSettingsInterface::class);
            $settings->method('getForTeacher')->willReturn(true);
            $settings->method('getForCentre')->willReturn('');
        }

        return new StayNotifier(
            $mailer,
            $urlGenerator,
            $translator,
            new NullLogger(),
            'no-responder@test.local',
            'Nexo FP',
            $settings,
            $this->createStub(EmailNotificationRecorder::class),
        );
    }

    private function teacher(string $first, string $last, ?string $email): Teacher
    {
        return $this->withId((new Teacher(new PersonName($first, $last)))->setUsername(strtolower($first . '.' . $last))->setEmail($email));
    }

    private function withId(object $entity): object
    {
        $class = new \ReflectionClass($entity);
        while (!$class->hasProperty('id')) {
            $class = $class->getParentClass();
        }
        $class->getProperty('id')->setValue($entity, Uuid::v7());

        return $entity;
    }
}
