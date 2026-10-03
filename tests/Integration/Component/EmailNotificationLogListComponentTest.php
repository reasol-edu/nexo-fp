<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\EducationalCentre;
use App\Entity\EmailNotificationLog;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class EmailNotificationLogListComponentTest extends ControllerTestCase
{
    use InteractsWithLiveComponents;

    public function testSearchMatchesRecipientNameEmailAndSubject(): void
    {
        [$admin, $centre] = $this->setUpScenario();
        $component = $this->component($admin, $centre);

        $component->set('search', 'marta');
        $html = (string) $component->render();
        self::assertStringContainsString('Asunto de Marta', $html);
        self::assertStringNotContainsString('Asunto de Pedro', $html);

        $component->set('search', 'pedro@test.local');
        $html = (string) $component->render();
        self::assertStringContainsString('Asunto de Pedro', $html);
        self::assertStringNotContainsString('Asunto de Marta', $html);

        $component->set('search', 'recordatorio');
        self::assertStringContainsString('Recordatorio especial', (string) $component->render());
    }

    public function testFiltersByStatusAndEventKey(): void
    {
        [$admin, $centre] = $this->setUpScenario();
        $component = $this->component($admin, $centre);

        $component->set('status', 'failed');
        $html = (string) $component->render();
        self::assertStringContainsString('Asunto de Pedro', $html);
        self::assertStringNotContainsString('Asunto de Marta', $html);

        $component->call('clearFilters');
        $component->set('eventKey', 'signature_reminder');
        $html = (string) $component->render();
        self::assertStringContainsString('Recordatorio especial', $html);
        self::assertStringNotContainsString('Asunto de Marta', $html);
    }

    public function testFiltersByDateRange(): void
    {
        [$admin, $centre] = $this->setUpScenario();
        $component = $this->component($admin, $centre);

        $component->set('dateFrom', '2026-10-02T00:00');
        $component->set('dateTo', '2026-10-02T23:59');
        $html = (string) $component->render();

        self::assertStringContainsString('Asunto de Pedro', $html);
        self::assertStringNotContainsString('Asunto de Marta', $html);
    }

    public function testClearFiltersRestoresTheFullList(): void
    {
        [$admin, $centre] = $this->setUpScenario();
        $component = $this->component($admin, $centre);

        $component->set('search', 'zzz-no-existe');
        self::assertStringContainsString('No hay correos que coincidan', (string) $component->render());

        $component->call('clearFilters');
        $html = (string) $component->render();
        self::assertStringContainsString('Asunto de Marta', $html);
        self::assertStringContainsString('Asunto de Pedro', $html);
    }

    public function testShowsAnEmptyStateWhenNothingWasSent(): void
    {
        $admin  = (new Teacher(new PersonName('Admin', 'Global')))->setUsername('admin.vacio')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000009')->setName('IES Vacío')->setCity('Sevilla');
        $this->persist($admin, $centre);

        self::assertStringContainsString('Todavía no se ha enviado ningún correo', (string) $this->component($admin, $centre)->render());
    }

    /** @return array{0: Teacher, 1: EducationalCentre} */
    private function setUpScenario(): array
    {
        $admin  = (new Teacher(new PersonName('Admin', 'Global')))->setUsername('admin.log')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Uno')->setCity('Sevilla');
        $this->persist($admin, $centre);

        $this->persist(
            new EmailNotificationLog($centre, null, 'Marta Ruiz', 'marta@test.local', 'tutor_assigned', 'Asunto de Marta', true, null, new \DateTimeImmutable('2026-10-01 09:00:00')),
            new EmailNotificationLog($centre, null, 'Pedro Gil', 'pedro@test.local', 'tutor_assigned', 'Asunto de Pedro', false, 'SMTP caído', new \DateTimeImmutable('2026-10-02 12:00:00')),
            new EmailNotificationLog($centre, null, 'Lola Mora', 'lola@test.local', 'signature_reminder', 'Recordatorio especial', true, null, new \DateTimeImmutable('2026-10-03 08:00:00')),
        );

        return [$admin, $centre];
    }

    private function component(Teacher $admin, EducationalCentre $centre): \Symfony\UX\LiveComponent\Test\TestLiveComponent
    {
        return $this->createLiveComponent(
            'Admin:EmailNotificationLogListComponent',
            ['centre' => $centre],
            $this->client,
        )->actingAs($admin);
    }
}
