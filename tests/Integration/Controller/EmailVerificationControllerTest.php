<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

class EmailVerificationControllerTest extends ControllerTestCase
{
    public function testValidTokenMovesPendingEmailToEmail(): void
    {
        $teacher   = $this->makeTeacherWithPendingEmail('verify.valid', 'tokenvalid123456', '+1 hour');
        $teacherId = $teacher->getId();

        $this->client->request('GET', '/perfil/verificar-email/tokenvalid123456');

        self::assertResponseRedirects();
        $this->em->clear();
        $fresh = $this->em->find(Teacher::class, $teacherId);
        self::assertSame('nuevo@ejemplo.local', $fresh?->getEmail());
        self::assertNull($fresh?->getPendingEmail());
        self::assertNull($fresh?->getEmailVerificationToken());
        self::assertNull($fresh?->getEmailVerificationTokenExpiresAt());
    }

    public function testInvalidTokenLeavesTeacherUnchanged(): void
    {
        $teacher   = $this->makeTeacherWithPendingEmail('verify.invalid', 'realtoken7890123', '+1 hour');
        $teacherId = $teacher->getId();

        $this->client->request('GET', '/perfil/verificar-email/tokenquenoexiste');

        self::assertResponseRedirects();
        $this->em->clear();
        $fresh = $this->em->find(Teacher::class, $teacherId);
        self::assertSame('antiguo@ejemplo.local', $fresh?->getEmail());
        self::assertSame('nuevo@ejemplo.local', $fresh?->getPendingEmail());
        self::assertSame('realtoken7890123', $fresh?->getEmailVerificationToken());
    }

    public function testExpiredTokenClearsPendingButKeepsCurrentEmail(): void
    {
        $teacher   = $this->makeTeacherWithPendingEmail('verify.expired', 'expiredtoken0001', '-1 second');
        $teacherId = $teacher->getId();

        $this->client->request('GET', '/perfil/verificar-email/expiredtoken0001');

        self::assertResponseRedirects();
        $this->em->clear();
        $fresh = $this->em->find(Teacher::class, $teacherId);
        self::assertSame('antiguo@ejemplo.local', $fresh?->getEmail());
        self::assertNull($fresh?->getPendingEmail());
        self::assertNull($fresh?->getEmailVerificationToken());
        self::assertNull($fresh?->getEmailVerificationTokenExpiresAt());
    }

    public function testTokenExpiringInFutureIsAccepted(): void
    {
        $teacher   = $this->makeTeacherWithPendingEmail('verify.future', 'futuretoken00002', '+1 minute');
        $teacherId = $teacher->getId();

        $this->client->request('GET', '/perfil/verificar-email/futuretoken00002');

        self::assertResponseRedirects();
        $this->em->clear();
        $fresh = $this->em->find(Teacher::class, $teacherId);
        self::assertSame('nuevo@ejemplo.local', $fresh?->getEmail());
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeTeacherWithPendingEmail(string $username, string $token, string $expiresAt): Teacher
    {
        $teacher = (new Teacher(new PersonName('Veri', 'Ficar')))
            ->setUsername($username)
            ->setEmail('antiguo@ejemplo.local')
            ->setPendingEmail('nuevo@ejemplo.local')
            ->setEmailVerificationToken($token)
            ->setEmailVerificationTokenExpiresAt(new \DateTimeImmutable($expiresAt));
        $this->persist($teacher);

        return $teacher;
    }
}
