<?php

declare(strict_types=1);

namespace App\Tests\Integration\Component;

use App\Entity\CentreSettingValue;
use App\Entity\EducationalCentre;
use App\Entity\GlobalSettingValue;
use App\Entity\PersonName;
use App\Entity\SettingDefinition;
use App\Entity\Teacher;
use App\Entity\TeacherSettingValue;
use App\Tests\Integration\ControllerTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

class SettingsLockTest extends ControllerTestCase
{
    use InteractsWithLiveComponents;

    // ── toggleLock ────────────────────────────────────────────────────────────

    public function testToggleLockLocksGlobalValue(): void
    {
        $admin       = $this->makeAdmin();
        $globalValue = $this->seedGlobalValue('email.notifications', 'true');
        self::assertFalse($globalValue->isLocked());

        $this->loginAs($admin);

        $component = $this->createLiveComponent('SettingsComponent', ['scope' => 'global'], $this->client);
        $component->call('toggleLock', ['key' => 'email.notifications']);

        $this->em->clear();
        $refreshed = $this->em->find(GlobalSettingValue::class, $globalValue->getId());
        self::assertTrue($refreshed->isLocked());
    }

    public function testToggleLockUnlocksGlobalValue(): void
    {
        $admin       = $this->makeAdmin();
        $globalValue = $this->seedGlobalValue('email.notifications', 'false', locked: true);

        $this->loginAs($admin);

        $component = $this->createLiveComponent('SettingsComponent', ['scope' => 'global'], $this->client);
        $component->call('toggleLock', ['key' => 'email.notifications']);

        $this->em->clear();
        $refreshed = $this->em->find(GlobalSettingValue::class, $globalValue->getId());
        self::assertFalse($refreshed->isLocked());
    }

    public function testToggleLockLocksCentreValue(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $centreValue      = $this->seedCentreValue('email.notifications', 'false', $centre);
        self::assertFalse($centreValue->isLocked());

        $this->loginAs($admin, $centre);

        $component = $this->createLiveComponent('SettingsComponent', ['scope' => 'centre'], $this->client);
        $component->call('toggleLock', ['key' => 'email.notifications']);

        $this->em->clear();
        $refreshed = $this->em->find(CentreSettingValue::class, $centreValue->getId());
        self::assertTrue($refreshed->isLocked());
    }

    public function testToggleLockCreatesAndLocksGlobalValueWhenNoneStored(): void
    {
        $admin = $this->makeAdmin();
        $this->loginAs($admin);

        $component = $this->createLiveComponent('SettingsComponent', ['scope' => 'global'], $this->client);
        $component->call('toggleLock', ['key' => 'email.notifications']);

        $def    = $this->em->getRepository(SettingDefinition::class)->findOneBy(['key' => 'email.notifications']);
        $stored = $this->em->getRepository(GlobalSettingValue::class)->findOneBy(['definition' => $def]);

        self::assertNotNull($stored);
        self::assertTrue($stored->isLocked());
        self::assertSame($def->getDefaultValue(), $stored->getValue());
    }

    public function testToggleLockCreatesCentreValueWhenNoneStored(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $this->loginAs($admin, $centre);

        $component = $this->createLiveComponent('SettingsComponent', ['scope' => 'centre'], $this->client);
        $component->call('toggleLock', ['key' => 'email.notifications']);

        $def    = $this->em->getRepository(SettingDefinition::class)->findOneBy(['key' => 'email.notifications']);
        $stored = $this->em->getRepository(CentreSettingValue::class)->findOneBy(['definition' => $def, 'centre' => $centre]);

        self::assertNotNull($stored);
        self::assertTrue($stored->isLocked());
        self::assertSame($def->getDefaultValue(), $stored->getValue());
    }

    // ── save blocked by parent lock ───────────────────────────────────────────

    public function testSaveIsBlockedWhenLockedByGlobal(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $globalValue      = $this->seedGlobalValue('email.notifications', 'false', locked: true);

        $this->loginAs($admin, $centre);

        $component = $this->createLiveComponent('SettingsComponent', ['scope' => 'centre'], $this->client);
        $component->call('save', ['key' => 'email.notifications', 'value' => 'true']);

        $def         = $this->em->getRepository(SettingDefinition::class)->findOneBy(['key' => 'email.notifications']);
        $centreValue = $this->em->getRepository(CentreSettingValue::class)->findOneBy([
            'definition' => $def,
            'centre'     => $centre,
        ]);

        self::assertNull($centreValue);

        $this->em->clear();
        $refreshed = $this->em->find(GlobalSettingValue::class, $globalValue->getId());
        self::assertSame('false', $refreshed->getValue());
    }

    public function testSaveResetIsBlockedWhenOwnValueIsLocked(): void
    {
        $admin       = $this->makeAdmin();
        $globalValue = $this->seedGlobalValue('email.notifications', 'false', locked: true);

        $this->loginAs($admin);

        $component = $this->createLiveComponent('SettingsComponent', ['scope' => 'global'], $this->client);
        $component->call('save', ['key' => 'email.notifications', 'value' => '__default__']);

        $this->em->clear();
        $refreshed = $this->em->find(GlobalSettingValue::class, $globalValue->getId());
        self::assertNotNull($refreshed, 'El valor bloqueado no debe eliminarse');
        self::assertSame('false', $refreshed->getValue());
    }

    // ── UI disabled state (rendered via HTTP page request) ────────────────────

    public function testCentreSettingsPageShowsLockedRow(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $this->seedGlobalValue('email.notifications', 'false', locked: true);

        $this->loginAs($admin, $centre);
        $this->client->request('GET', '/mi-centro/ajustes');

        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('disabled', $html);
        self::assertStringContainsString('Bloqueado por la administración global', $html);
    }

    public function testTeacherSettingsPageShowsRowLockedByGlobal(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $teacher          = (new Teacher(new PersonName('Docente', 'Prueba')))->setUsername('docente.prueba');
        $this->persist($teacher);
        $this->seedGlobalValue('email.notifications', 'false', locked: true);

        $this->loginAs($teacher, $centre);
        $this->client->request('GET', '/perfil/ajustes');

        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('disabled', $html);
        self::assertStringContainsString('Bloqueado por la administración global', $html);
    }

    public function testTeacherSettingsPageShowsRowLockedByCentre(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $teacher          = (new Teacher(new PersonName('Docente', 'Prueba2')))->setUsername('docente.prueba2');
        $this->persist($teacher);
        $this->seedCentreValue('email.notifications', 'false', $centre, locked: true);

        $this->loginAs($teacher, $centre);
        $this->client->request('GET', '/perfil/ajustes');

        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('disabled', $html);
        self::assertStringContainsString('Bloqueado por el centro educativo', $html);
    }

    // ── Valor mostrado al bloquear: siempre el del nivel superior ────────────

    public function testCentreSettingsShowsGlobalLockedValueEvenWhenCentreHasOwnValue(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $this->seedGlobalValue('email.notifications', 'false', locked: true);
        $this->seedCentreValue('email.notifications', 'true', $centre); // centre has its own differing value

        $this->loginAs($admin, $centre);
        $this->client->request('GET', '/mi-centro/ajustes');

        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('value="false" selected', $html);
        self::assertStringNotContainsString('value="true"  selected', $html);
    }

    public function testTeacherSettingsShowsGlobalLockedValueEvenWhenTeacherHasOwnValue(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $teacher          = (new Teacher(new PersonName('Docente', 'Display1')))->setUsername('docente.display1');
        $this->persist($teacher);
        $this->seedGlobalValue('email.notifications', 'false', locked: true);
        $this->seedTeacherValue('email.notifications', 'true', $teacher);

        $this->loginAs($teacher, $centre);
        $this->client->request('GET', '/perfil/ajustes');

        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('value="false" selected', $html);
        self::assertStringNotContainsString('value="true"  selected', $html);
    }

    public function testTeacherSettingsShowsCentreLockedValueEvenWhenTeacherHasOwnValue(): void
    {
        [$admin, $centre] = $this->makeAdminWithCentre();
        $teacher          = (new Teacher(new PersonName('Docente', 'Display2')))->setUsername('docente.display2');
        $this->persist($teacher);
        $this->seedCentreValue('email.notifications', 'false', $centre, locked: true);
        $this->seedTeacherValue('email.notifications', 'true', $teacher);

        $this->loginAs($teacher, $centre);
        $this->client->request('GET', '/perfil/ajustes');

        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('value="false" selected', $html);
        self::assertStringNotContainsString('value="true"  selected', $html);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAdmin(): Teacher
    {
        $admin  = (new Teacher(new PersonName('Admin', 'Global')))->setUsername('admin.global')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000001')->setName('IES Lock Global Test')->setCity('Sevilla');
        $this->persist($admin, $centre);

        return $admin;
    }

    /** @return array{0: Teacher, 1: EducationalCentre} */
    private function makeAdminWithCentre(): array
    {
        $admin  = (new Teacher(new PersonName('Admin', 'Centro')))->setUsername('admin.centro')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000099')->setName('IES Lock Test')->setCity('Sevilla');
        $this->persist($admin, $centre);

        return [$admin, $centre];
    }

    private function seedGlobalValue(string $key, string $value, bool $locked = false): GlobalSettingValue
    {
        $def    = $this->em->getRepository(SettingDefinition::class)->findOneBy(['key' => $key]);
        $entity = (new GlobalSettingValue())->setDefinition($def)->setValue($value)->setLocked($locked);
        $this->persist($entity);

        return $entity;
    }

    private function seedCentreValue(
        string $key,
        string $value,
        EducationalCentre $centre,
        bool $locked = false,
    ): CentreSettingValue {
        $def    = $this->em->getRepository(SettingDefinition::class)->findOneBy(['key' => $key]);
        $entity = (new CentreSettingValue())->setDefinition($def)->setCentre($centre)->setValue($value)->setLocked($locked);
        $this->persist($entity);

        return $entity;
    }

    private function seedTeacherValue(string $key, string $value, Teacher $teacher): TeacherSettingValue
    {
        $def    = $this->em->getRepository(SettingDefinition::class)->findOneBy(['key' => $key]);
        $entity = (new TeacherSettingValue())->setDefinition($def)->setTeacher($teacher)->setValue($value);
        $this->persist($entity);

        return $entity;
    }
}
