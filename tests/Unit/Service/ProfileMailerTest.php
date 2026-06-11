<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Service\ProfileMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProfileMailerTest extends TestCase
{
    private const TOKEN      = 'abc123def456';
    private const VERIFY_URL = 'http://localhost/perfil/verificar-email/abc123def456';

    // ── sendEmailVerification ─────────────────────────────────────────────────

    public function testSendEmailVerificationSendsToCorrectAddress(): void
    {
        $teacher = $this->makeTeacher('luisa@actual.local');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (TemplatedEmail $email): bool {
                self::assertSame('nuevo@ejemplo.local', $email->getTo()[0]->getAddress());

                return true;
            }));

        $this->makeProfileMailer($mailer)->sendEmailVerification($teacher, 'nuevo@ejemplo.local', self::TOKEN);
    }

    public function testSendEmailVerificationUsesCorrectTemplate(): void
    {
        $teacher = $this->makeTeacher(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (TemplatedEmail $email): bool {
                self::assertSame('email/email_verification.html.twig', $email->getHtmlTemplate());

                return true;
            }));

        $this->makeProfileMailer($mailer)->sendEmailVerification($teacher, 'nuevo@ejemplo.local', self::TOKEN);
    }

    public function testSendEmailVerificationIncludesVerifyUrl(): void
    {
        $teacher = $this->makeTeacher(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (TemplatedEmail $email): bool {
                self::assertSame(self::VERIFY_URL, $email->getContext()['verify_url']);

                return true;
            }));

        $this->makeProfileMailer($mailer)->sendEmailVerification($teacher, 'nuevo@ejemplo.local', self::TOKEN);
    }

    public function testSendEmailVerificationSetsCorrectFromAddress(): void
    {
        $teacher = $this->makeTeacher(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (TemplatedEmail $email): bool {
                self::assertSame('no-responder@test.local', $email->getFrom()[0]->getAddress());
                self::assertSame('Nexo FP', $email->getFrom()[0]->getName());

                return true;
            }));

        $this->makeProfileMailer($mailer)->sendEmailVerification($teacher, 'nuevo@ejemplo.local', self::TOKEN);
    }

    public function testTransportExceptionIsLoggedAndNotPropagated(): void
    {
        $teacher = $this->makeTeacher(null);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP caído'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->makeProfileMailer($mailer, $logger)
            ->sendEmailVerification($teacher, 'nuevo@ejemplo.local', self::TOKEN);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeProfileMailer(MailerInterface $mailer, ?LoggerInterface $logger = null): ProfileMailer
    {
        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn(self::VERIFY_URL);

        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ProfileMailer(
            $mailer,
            $urlGenerator,
            $translator,
            $logger ?? new NullLogger(),
            'no-responder@test.local',
            'Nexo FP',
        );
    }

    private function makeTeacher(?string $email): Teacher
    {
        return (new Teacher(new PersonName('Luisa', 'Gómez')))
            ->setUsername('luisa.gomez')
            ->setEmail($email);
    }
}
