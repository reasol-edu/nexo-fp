<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Company;
use App\Entity\PersonName;
use App\Entity\Stay;
use App\Entity\Teacher;
use App\Entity\TrainingPosition;
use App\Service\AppSettingsInterface;
use App\Service\StayNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

class StayNotifierTest extends TestCase
{
    // ── notifyTutorAssigned ───────────────────────────────────────────────────

    public function testNotifyTutorAssignedSendsEmailToTutor(): void
    {
        $position = $this->makePosition();
        $tutor    = $this->makeTeacher('Luisa', 'Gomez', 'luisa@test.local');
        $position->setAcademicTutor($tutor);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (TemplatedEmail $email): bool {
                self::assertSame('luisa@test.local', $email->getTo()[0]->getAddress());
                self::assertSame('no-responder@test.local', $email->getFrom()[0]->getAddress());
                self::assertSame('Nexo FP', $email->getFrom()[0]->getName());
                self::assertSame('emails.tutor_assigned.subject', $email->getSubject());
                self::assertSame('email/tutor_assigned.html.twig', $email->getHtmlTemplate());
                self::assertSame('http://localhost/estancias/test', $email->getContext()['stay_url']);

                return true;
            }));

        $this->makeNotifier($mailer)->notifyTutorAssigned($position);
    }

    public function testNotifyTutorAssignedDoesNothingWithoutTutor(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->makeNotifier($mailer)->notifyTutorAssigned($this->makePosition());
    }

    public function testNotifyTutorAssignedSkipsTutorWithoutEmail(): void
    {
        $position = $this->makePosition();
        $position->setAcademicTutor($this->makeTeacher('Luisa', 'Gomez', null));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $this->makeNotifier($mailer, $logger)->notifyTutorAssigned($position);
    }

    public function testTransportExceptionIsLoggedAndNotPropagated(): void
    {
        $position = $this->makePosition();
        $position->setAcademicTutor($this->makeTeacher('Luisa', 'Gomez', 'luisa@test.local'));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP caído'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->makeNotifier($mailer, $logger)->notifyTutorAssigned($position);
    }

    // ── notifyLiaisonsPositionsCreated ────────────────────────────────────────

    public function testNotifyLiaisonsSkipsCreatorAndTeachersWithoutEmail(): void
    {
        $stay    = $this->makeStay();
        $company = new Company();

        $creator   = $this->makeTeacher('Crea', 'Dora', 'creadora@test.local');
        $noEmail   = $this->makeTeacher('Sin', 'Correo', null);
        $recipient = $this->makeTeacher('Reci', 'Be', 'recibe@test.local');
        $company->addLiaison($creator);
        $company->addLiaison($noEmail);
        $company->addLiaison($recipient);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (TemplatedEmail $email): bool {
                self::assertSame('recibe@test.local', $email->getTo()[0]->getAddress());
                self::assertSame('email/positions_created.html.twig', $email->getHtmlTemplate());
                self::assertSame(3, $email->getContext()['count']);

                return true;
            }));

        $this->makeNotifier($mailer)->notifyLiaisonsPositionsCreated($stay, $company, 3, $creator);
    }

    // ── sendSignatureReminder ─────────────────────────────────────────────────

    public function testSendSignatureReminderBuildsStayUrlMap(): void
    {
        $tutor    = $this->makeTeacher('Luisa', 'Gomez', 'luisa@test.local');
        $position = $this->makePosition();
        $stayId   = $position->getStay()->getId()->toRfc4122();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (TemplatedEmail $email) use ($stayId): bool {
                self::assertSame('luisa@test.local', $email->getTo()[0]->getAddress());
                self::assertSame('email/signature_reminder.html.twig', $email->getHtmlTemplate());
                self::assertSame(7, $email->getContext()['days_left']);
                self::assertArrayHasKey($stayId, $email->getContext()['stay_urls']);

                return true;
            }));

        $this->makeNotifier($mailer)->sendSignatureReminder($tutor, [$position], 7);
    }

    public function testSendSignatureReminderDoesNothingWithoutPositions(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $this->makeNotifier($mailer)
            ->sendSignatureReminder($this->makeTeacher('Luisa', 'Gomez', 'luisa@test.local'), [], 7);
    }

    // ── Settings-based suppression ────────────────────────────────────────────

    public function testNotifyTutorAssignedIsSkippedWhenMasterNotificationsDisabled(): void
    {
        $position = $this->makePosition();
        $position->setAcademicTutor($this->makeTeacher('Luisa', 'Gomez', 'luisa@test.local'));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $settings = $this->createStub(AppSettingsInterface::class);
        $settings->method('getForTeacher')->willReturnCallback(
            fn(string $key) => $key !== 'email.notifications'
        );

        $this->makeNotifier($mailer, settings: $settings)->notifyTutorAssigned($position);
    }

    public function testNotifyTutorAssignedIsSkippedWhenSpecificKeyDisabled(): void
    {
        $position = $this->makePosition();
        $position->setAcademicTutor($this->makeTeacher('Luisa', 'Gomez', 'luisa@test.local'));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $settings = $this->createStub(AppSettingsInterface::class);
        $settings->method('getForTeacher')->willReturnCallback(
            fn(string $key) => $key !== 'email.notification.tutor_assigned'
        );

        $this->makeNotifier($mailer, settings: $settings)->notifyTutorAssigned($position);
    }

    public function testSendSignatureReminderIsSkippedWhenSpecificKeyDisabled(): void
    {
        $tutor    = $this->makeTeacher('Luisa', 'Gomez', 'luisa@test.local');
        $position = $this->makePosition();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $settings = $this->createStub(AppSettingsInterface::class);
        $settings->method('getForTeacher')->willReturnCallback(
            fn(string $key) => $key !== 'email.notification.signature_reminder'
        );

        $this->makeNotifier($mailer, settings: $settings)->sendSignatureReminder($tutor, [$position], 7);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeNotifier(
        MailerInterface $mailer,
        ?LoggerInterface $logger = null,
        ?AppSettingsInterface $settings = null,
    ): StayNotifier {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/estancias/test');

        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $settings ??= $this->makeAllEnabledSettings();

        return new StayNotifier(
            $mailer,
            $urlGenerator,
            $translator,
            $logger ?? new NullLogger(),
            'no-responder@test.local',
            'Nexo FP',
            $settings,
        );
    }

    private function makeAllEnabledSettings(): AppSettingsInterface
    {
        $stub = $this->createStub(AppSettingsInterface::class);
        $stub->method('getForTeacher')->willReturn(true);

        return $stub;
    }

    private function makeTeacher(string $firstName, string $lastName, ?string $email): Teacher
    {
        $teacher = (new Teacher(new PersonName($firstName, $lastName)))
            ->setUsername(strtolower($firstName . '.' . $lastName))
            ->setEmail($email);
        $this->setId($teacher);

        return $teacher;
    }

    private function makeStay(): Stay
    {
        $stay = (new Stay())->setName('Estancia Test');
        $this->setId($stay);

        return $stay;
    }

    private function makePosition(): TrainingPosition
    {
        return (new TrainingPosition())->setStay($this->makeStay());
    }

    /** Asigna un UUID por reflexión: fuera de Doctrine el id no se genera. */
    private function setId(object $entity): void
    {
        $class = new \ReflectionClass($entity);
        while (!$class->hasProperty('id')) {
            $class = $class->getParentClass();
        }
        $class->getProperty('id')->setValue($entity, Uuid::v7());
    }
}
