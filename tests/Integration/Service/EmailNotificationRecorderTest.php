<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\EducationalCentre;
use App\Entity\EmailNotificationLog;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Service\EmailNotificationRecorder;
use App\Tests\Integration\RepositoryTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

class EmailNotificationRecorderTest extends RepositoryTestCase
{
    private EmailNotificationRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = new EmailNotificationRecorder($this->em, new MockClock('2026-10-05 09:30:00'), new NullLogger());
    }

    public function testRecordsASuccessfulSendWithCentreAndRecipient(): void
    {
        $centre  = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $teacher = (new Teacher(new PersonName('Luisa', 'Gómez')))->setUsername('luisa');
        $this->persist($centre, $teacher);

        $this->recorder->record($centre, $teacher, 'Luisa Gómez', 'luisa@test.local', 'tutor_assigned', 'Tutoría asignada');

        $this->em->clear();
        $entries = $this->em->getRepository(EmailNotificationLog::class)->findAll();

        self::assertCount(1, $entries);
        self::assertSame($centre->getId()->toRfc4122(), $entries[0]->getEducationalCentre()?->getId()->toRfc4122());
        self::assertSame($teacher->getId()->toRfc4122(), $entries[0]->getRecipient()?->getId()->toRfc4122());
        self::assertSame('Luisa Gómez', $entries[0]->getRecipientName());
        self::assertSame('luisa@test.local', $entries[0]->getRecipientEmail());
        self::assertSame('tutor_assigned', $entries[0]->getEventKey());
        self::assertSame('Tutoría asignada', $entries[0]->getSubject());
        self::assertTrue($entries[0]->isSuccess());
        self::assertNull($entries[0]->getErrorMessage());
        self::assertSame('2026-10-05 09:30:00', $entries[0]->getSentAt()->format('Y-m-d H:i:s'));
    }

    public function testRecordsAFailureWithItsMessageAndNoCentre(): void
    {
        $this->recorder->record(null, null, 'Alguien', 'alguien@test.local', 'password_reset', 'Restablecer', 'SMTP caído');

        $this->em->clear();
        $entry = $this->em->getRepository(EmailNotificationLog::class)->findAll()[0];

        self::assertNull($entry->getEducationalCentre());
        self::assertNull($entry->getRecipient());
        self::assertFalse($entry->isSuccess());
        self::assertSame('SMTP caído', $entry->getErrorMessage());
    }

    public function testDoesNotFlushUnrelatedPendingChanges(): void
    {
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Test')->setCity('Sevilla');
        $this->persist($centre);

        // Un cambio pendiente (aún sin flush) que no tiene nada que ver con el registro.
        $centre->setName('Nombre cambiado sin guardar');

        $this->recorder->record($centre, null, 'Alguien', 'alguien@test.local', 'tutor_assigned', 'Asunto');

        $stored = $this->em->getConnection()->fetchOne('SELECT name FROM educational_centre');
        self::assertSame('IES Test', $stored, 'registrar un correo no debe guardar cambios ajenos');
    }

    public function testTruncatesOverlongValuesInsteadOfFailing(): void
    {
        $this->recorder->record(null, null, str_repeat('n', 300), str_repeat('e', 400), 'k', str_repeat('s', 400));

        $this->em->clear();
        $entry = $this->em->getRepository(EmailNotificationLog::class)->findAll()[0];

        self::assertSame(200, mb_strlen($entry->getRecipientName()));
        self::assertSame(255, mb_strlen($entry->getRecipientEmail()));
        self::assertSame(255, mb_strlen($entry->getSubject()));
    }

    public function testAFailureToRecordNeverPropagates(): void
    {
        /** @var EntityManagerInterface $em */
        $em = $this->em;
        $em->getConnection()->executeStatement('DROP TABLE email_notification_log');

        $this->recorder->record(null, null, 'Alguien', 'alguien@test.local', 'tutor_assigned', 'Asunto');

        $this->addToAssertionCount(1); // no ha lanzado ninguna excepción
    }
}
