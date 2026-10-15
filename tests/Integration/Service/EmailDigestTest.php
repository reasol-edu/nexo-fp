<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\EmailDigestItem;
use App\Entity\PersonName;
use App\Entity\SettingDefinition;
use App\Entity\SettingType;
use App\Entity\Stay;
use App\Entity\Student;
use App\Entity\Teacher;
use App\Entity\TeacherSettingValue;
use App\Entity\TrainingPosition;
use App\Repository\EmailDigestItemRepository;
use App\Service\AppSettingsInterface;
use App\Service\EmailDigestSender;
use App\Service\StayNotifier;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Clock\MockClock;

class EmailDigestTest extends RepositoryTestCase
{
    use MailerAssertionsTrait;

    private EducationalCentre $centre;
    private Stay $stay;
    private Teacher $tutor;
    private SettingDefinition $digestDefinition;
    private SettingDefinition $hourDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->centre = (new EducationalCentre())->setCode('41000009')->setName('IES Test')->setCity('Sevilla');
        $year         = (new AcademicYear())->setName('2026-2027')->setEducationalCentre($this->centre);
        $this->stay   = (new Stay())
            ->setName('FFEOE DAW')
            ->setAcademicYear($year)
            ->setStartDate(new \DateTimeImmutable('+30 days'))
            ->setEndDate(new \DateTimeImmutable('+120 days'));
        $this->tutor = (new Teacher(new PersonName('Luisa', 'Gómez')))->setUsername('luisa.gomez')->setEmail('luisa@test.local');

        $this->digestDefinition = $this->definition('email.digest', SettingType::Boolean, 'false');
        $this->hourDefinition   = $this->definition('email.digest_hour', SettingType::Integer, '7', 0, 23);

        $this->persist($this->centre, $year, $this->stay, $this->tutor, $this->digestDefinition, $this->hourDefinition);
    }

    public function testWithTheDigestEnabledNoImmediateEmailIsSentAndTheLineIsQueued(): void
    {
        $this->enableDigest();

        $this->notifier()->notifyTutorAssigned($this->position());

        self::assertEmailCount(0);
        $items = $this->unsentItems();
        self::assertCount(1, $items);
        self::assertSame('tutor_assigned', $items[0]->getEventKey());
        self::assertSame($this->tutor->getId()->toRfc4122(), $items[0]->getRecipient()->getId()->toRfc4122());
        self::assertSame('FFEOE DAW', $items[0]->getParams()['%stay%']);
    }

    public function testWithoutTheDigestTheEmailIsStillImmediate(): void
    {
        $this->notifier()->notifyTutorAssigned($this->position());

        self::assertEmailCount(1);
        self::assertSame([], $this->unsentItems());
    }

    public function testTheDigestIsNotSentBeforeTheConfiguredHour(): void
    {
        $this->enableDigest();
        $this->notifier()->notifyTutorAssigned($this->position());

        $sent = $this->sender('tomorrow 06:30')->sendDue();

        self::assertSame(0, $sent);
        self::assertEmailCount(0);
        self::assertCount(1, $this->unsentItems());
    }

    public function testTheDigestIsSentOnceAfterTheConfiguredHourWithAllTheLines(): void
    {
        $this->enableDigest();
        $position = $this->position();
        $this->notifier()->notifyTutorAssigned($position);
        $this->notifier()->notifyTutorAssigned($position);
        $this->notifier()->sendSignatureReminderDigest($this->tutor, [['stay' => $this->stay, 'positions' => [$position, $position]]]);

        $sent = $this->sender('tomorrow 07:00')->sendDue();

        self::assertSame(1, $sent);
        self::assertEmailCount(1);
        $email = self::getMailerMessage(0);
        self::assertEmailAddressContains($email, 'to', 'luisa@test.local');
        $body = (string) $email?->getHtmlBody();
        self::assertStringContainsString('FFEOE DAW', $body);
        self::assertStringContainsString('(2 veces)', $body);   // las dos líneas idénticas de tutoría van juntas
        self::assertStringContainsString('Firmas pendientes', $body);
        self::assertSame([], $this->unsentItems());

        // Una segunda pasada no vuelve a enviar nada.
        self::assertSame(0, $this->sender('tomorrow 08:00')->sendDue());
        self::assertEmailCount(1);
    }

    public function testTheHourIsTakenFromThePersonalSetting(): void
    {
        $this->enableDigest();
        $this->setTeacherSetting($this->hourDefinition, '9');
        $this->notifier()->notifyTutorAssigned($this->position());

        self::assertSame(0, $this->sender('tomorrow 08:59')->sendDue());
        self::assertSame(1, $this->sender('tomorrow 09:00')->sendDue());
    }

    public function testIgnoreHourSendsEverythingPendingNow(): void
    {
        $this->enableDigest();
        $this->notifier()->notifyTutorAssigned($this->position());

        self::assertSame(1, $this->sender('today 00:00')->sendDue(ignoreHour: true));
    }

    public function testOldLinesArePurged(): void
    {
        $this->enableDigest();
        $this->notifier()->notifyTutorAssigned($this->position());

        // Pasados 15 días sin poder enviarse, la línea se descarta.
        $this->sender('+15 days 00:00')->sendDue();

        self::assertSame([], $this->unsentItems());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function notifier(): StayNotifier
    {
        return self::getContainer()->get(StayNotifier::class);
    }

    private function sender(string $now): EmailDigestSender
    {
        $c = self::getContainer();

        return new EmailDigestSender(
            $c->get(EmailDigestItemRepository::class),
            $c->get(StayNotifier::class),
            $c->get(AppSettingsInterface::class),
            $this->em,
            new MockClock(new \DateTimeImmutable($now)),
        );
    }

    /** @return list<EmailDigestItem> */
    private function unsentItems(): array
    {
        $this->em->clear();

        return self::getContainer()->get(EmailDigestItemRepository::class)->findUnsent();
    }

    private function position(): TrainingPosition
    {
        $student  = (new Student(new PersonName('Ana', 'Martínez')))->setStudentId('2026-' . uniqid());
        $position = (new TrainingPosition())->setStay($this->stay)->setStudent($student)->setAcademicTutor($this->tutor);
        $this->persist($student, $position);

        return $position;
    }

    private function enableDigest(): void
    {
        $this->setTeacherSetting($this->digestDefinition, 'true');
    }

    private function setTeacherSetting(SettingDefinition $definition, string $value): void
    {
        $this->persist((new TeacherSettingValue())->setDefinition($definition)->setTeacher($this->tutor)->setValue($value));
        self::getContainer()->get('App\Service\AppSettings')->invalidate();
    }

    private function definition(string $key, SettingType $type, string $default, ?int $min = null, ?int $max = null): SettingDefinition
    {
        return (new SettingDefinition())
            ->setKey($key)
            ->setType($type)
            ->setDefaultValue($default)
            ->setGlobalScope(true)
            ->setCentreScope(false)
            ->setTeacherScope(true)
            ->setMinValue($min)
            ->setMaxValue($max);
    }
}
