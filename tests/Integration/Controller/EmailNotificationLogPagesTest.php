<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\EducationalCentre;
use App\Entity\EmailNotificationLog;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

/**
 * Páginas del registro de correos: la del centro (administración del centro) y la global.
 */
class EmailNotificationLogPagesTest extends ControllerTestCase
{
    public function testCentreAdminSeesOnlyTheirCentreEmails(): void
    {
        [$centre, $other, $centreAdmin] = $this->setUpScenario();
        $this->log($centre, 'Ana del Centro', 'Aviso del centro uno');
        $this->log($other, 'Beto del Otro', 'Aviso del centro dos');
        $this->log(null, 'Carla Sin Centro', 'Restablecer contraseña de otra persona');
        $this->loginAs($centreAdmin, $centre);

        $this->client->request('GET', '/mi-centro/registro-correos');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Aviso del centro uno', $html);
        self::assertStringNotContainsString('Aviso del centro dos', $html);
        self::assertStringNotContainsString('Restablecer contraseña de otra persona', $html);
    }

    public function testUnprivilegedTeacherCannotSeeTheCentreLog(): void
    {
        [$centre] = $this->setUpScenario();
        $teacher = (new Teacher(new PersonName('Docente', 'Normal')))->setUsername('docente.normal');
        $this->persist($teacher);
        $this->loginAs($teacher, $centre);

        $this->client->request('GET', '/mi-centro/registro-correos');

        self::assertResponseStatusCodeSame(403);
    }

    public function testGlobalAdminSeesEveryEmailIncludingTheOnesWithoutCentre(): void
    {
        [$centre, $other, , $globalAdmin] = $this->setUpScenario();
        $this->log($centre, 'Ana del Centro', 'Aviso del centro uno');
        $this->log($other, 'Beto del Otro', 'Aviso del centro dos');
        $this->log(null, 'Carla Sin Centro', 'Restablecer contraseña de otra persona');
        $this->loginAs($globalAdmin, $centre);

        $this->client->request('GET', '/admin/registro-correos');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Aviso del centro uno', $html);
        self::assertStringContainsString('Aviso del centro dos', $html);
        self::assertStringContainsString('Restablecer contraseña de otra persona', $html);
    }

    public function testCentreAdminCannotOpenTheGlobalLog(): void
    {
        [$centre, , $centreAdmin] = $this->setUpScenario();
        $this->loginAs($centreAdmin, $centre);

        $this->client->request('GET', '/admin/registro-correos');

        self::assertResponseStatusCodeSame(403);
    }

    public function testFailedSendsShowTheirError(): void
    {
        [$centre, , , $globalAdmin] = $this->setUpScenario();
        $this->log($centre, 'Ana del Centro', 'Aviso fallido', 'Conexión rechazada por el servidor');
        $this->loginAs($globalAdmin, $centre);

        $this->client->request('GET', '/admin/registro-correos');

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Fallido', $html);
        self::assertStringContainsString('Conexión rechazada por el servidor', $html);
    }

    public function testCentreHubLinksToTheLogForCentreAdmins(): void
    {
        [$centre, , $centreAdmin] = $this->setUpScenario();
        $this->loginAs($centreAdmin, $centre);

        $crawler = $this->client->request('GET', '/mi-centro');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href="/mi-centro/registro-correos"]'));
    }

    /** @return array{0: EducationalCentre, 1: EducationalCentre, 2: Teacher, 3: Teacher} */
    private function setUpScenario(): array
    {
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Uno')->setCity('Sevilla');
        $other  = (new EducationalCentre())->setCode('41000002')->setName('IES Dos')->setCity('Sevilla');
        $centreAdmin = (new Teacher(new PersonName('Admin', 'Centro')))->setUsername('admin.centro');
        $globalAdmin = (new Teacher(new PersonName('Admin', 'Global')))->setUsername('admin.global')->setAdmin(true);
        $this->persist($centre, $other, $centreAdmin, $globalAdmin);
        $centre->addAdmin($centreAdmin);
        $this->flush();

        return [$centre, $other, $centreAdmin, $globalAdmin];
    }

    private function log(?EducationalCentre $centre, string $name, string $subject, ?string $error = null): void
    {
        $this->persist(new EmailNotificationLog(
            $centre,
            null,
            $name,
            'destino@test.local',
            'tutor_assigned',
            $subject,
            $error === null,
            $error,
            new \DateTimeImmutable('2026-10-01 10:00:00'),
        ));
    }
}
