<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Backup;

use App\Entity\GlobalSettingValue;
use App\Entity\SettingDefinition;
use App\Entity\SettingType;
use App\Service\AppSettings;
use App\Service\AppSettingsInterface;
use App\Service\Backup\AutomaticBackup;
use App\Service\Backup\BackupLibrary;
use App\Service\Backup\BackupRetentionPolicy;
use App\Service\SystemState;
use App\Tests\Integration\RepositoryTestCase;
use App\Tests\Support\CleansSystemFiles;
use Psr\Log\NullLogger;

/** Copia automática nocturna: la poda solo toca copias automáticas, no quedan ficheros a medias y hay estado para Administración. */
final class AutomaticBackupTest extends RepositoryTestCase
{
    use CleansSystemFiles;

    private const string BASIC       = '7,0,0';
    private const string RECOMMENDED = '7,4,3';

    private BackupLibrary $library;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanSystemFiles();

        /** @var BackupLibrary $library */
        $library       = self::getContainer()->get(BackupLibrary::class);
        $this->library = $library;
        $this->dir     = $library->directory();
    }

    protected function tearDown(): void
    {
        $this->cleanSystemFiles();

        parent::tearDown();
    }

    public function testSavingAnAutomaticBackupLeavesNoPartialFile(): void
    {
        $stored = $this->library->saveAutomatic();

        self::assertMatchesRegularExpression('/^nexo-fp-backup-auto-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $stored->name);
        self::assertFileExists($this->dir . '/' . $stored->name);
        self::assertSame([], glob($this->dir . '/*.part') ?: []);
        self::assertTrue($stored->automatic);
        self::assertNotNull($stored->manifest);
    }

    public function testASavedBackupIsListedAndTheOnesFromTheConsoleToo(): void
    {
        $manual = $this->library->save();
        self::assertFalse($manual->automatic);

        // `app:backup` sin destino deja `nexo-fp-backup-<fecha>.zip` en esta misma carpeta.
        copy($this->dir . '/' . $manual->name, $this->dir . '/nexo-fp-backup-2026-01-01-120000.zip');
        file_put_contents($this->dir . '/notas.txt', 'no es una copia');

        $names = array_map(static fn ($copy): string => $copy->name, $this->library->list());
        self::assertContains($manual->name, $names);
        self::assertContains('nexo-fp-backup-2026-01-01-120000.zip', $names);
        self::assertNotContains('notas.txt', $names);
    }

    public function testAnUnreadableZipIsListedWithoutManifest(): void
    {
        @mkdir($this->dir, 0o775, true);
        file_put_contents($this->dir . '/roto.zip', 'esto no es un zip');

        $copies = $this->library->list();

        self::assertCount(1, $copies);
        self::assertNull($copies[0]->manifest);
    }

    public function testACopyIsOnlyFoundByItsExactNameInsideTheFolder(): void
    {
        $stored = $this->library->save();

        self::assertNotNull($this->library->find($stored->name));
        self::assertNull($this->library->find('../' . $stored->name));
        self::assertNull($this->library->find('no-existe.zip'));
        self::assertNull($this->library->find($stored->name . '.txt'));
        self::assertFalse($this->library->delete('../' . $stored->name));
        self::assertTrue($this->library->delete($stored->name));
        self::assertNull($this->library->find($stored->name));
    }

    public function testTheBasicPolicyKeepsTheLastSevenDaysAndNeverTouchesManualOnes(): void
    {
        $manual = $this->library->save();
        foreach (range(1, 10) as $day) {
            $this->makeAutomatic(\sprintf('2026-03-%02d', $day), $manual->name);
        }
        // Un fichero de alguien con un nombre parecido pero que no sigue el patrón: tampoco se toca.
        copy($this->dir . '/' . $manual->name, $this->dir . '/nexo-fp-backup-auto-importante.zip');

        self::assertSame(3, $this->library->pruneAutomatic($this->policy(self::BASIC)));

        foreach ([1, 2, 3] as $day) {
            self::assertFileDoesNotExist($this->auto(\sprintf('2026-03-%02d', $day)));
        }
        foreach ([4, 5, 6, 7, 8, 9, 10] as $day) {
            self::assertFileExists($this->auto(\sprintf('2026-03-%02d', $day)));
        }
        self::assertFileExists($this->dir . '/' . $manual->name);
        self::assertFileExists($this->dir . '/nexo-fp-backup-auto-importante.zip');
    }

    public function testTheRecommendedPolicyAlsoKeepsTheNewestOfEachWeek(): void
    {
        $source = $this->library->save();
        foreach (range(1, 31) as $day) { // el 1-3-2026 es domingo: las semanas acaban el 29 (S13), el 22 (S12), el 15 (S11)…
            $this->makeAutomatic(\sprintf('2026-03-%02d', $day), $source->name);
        }

        $this->library->pruneAutomatic($this->policy(self::RECOMMENDED));

        // Los últimos 7 días (25–31) + la más reciente de las tres semanas anteriores a la actual (el 29 ya está; el 22 y el 15 son nuevas).
        foreach ([15, 22, 25, 26, 27, 28, 29, 30, 31] as $day) {
            self::assertFileExists($this->auto(\sprintf('2026-03-%02d', $day)), "el día $day debería conservarse");
        }
        foreach ([1, 14, 16, 21, 23, 24] as $day) {
            self::assertFileDoesNotExist($this->auto(\sprintf('2026-03-%02d', $day)), "el día $day debería podarse");
        }
        self::assertCount(9, glob($this->dir . '/nexo-fp-backup-auto-*.zip') ?: []);
    }

    public function testTheRecommendedPolicyAlsoKeepsTheNewestOfEachMonthAndDropsOlderOnes(): void
    {
        $source = $this->library->save();
        foreach (['2026-01-15', '2026-02-15', '2026-03-15', '2026-05-01', '2026-05-02', '2026-05-03', '2026-05-04', '2026-05-05', '2026-05-06', '2026-05-07'] as $date) {
            $this->makeAutomatic($date, $source->name);
        }

        // Diarias: 1–7 de mayo. Semanales: 3 de mayo, 15 de marzo, 15 de febrero (la 4.ª semana con copia es la del 15 de enero). Mensuales: mayo, marzo, febrero.
        self::assertSame(1, $this->library->pruneAutomatic($this->policy(self::RECOMMENDED)));

        self::assertFileDoesNotExist($this->auto('2026-01-15'));
        self::assertFileExists($this->auto('2026-02-15'));
        self::assertFileExists($this->auto('2026-03-15'));
    }

    public function testThePolicyUsesTheDateInTheNameNotTheFileTime(): void
    {
        $source = $this->library->save();
        $this->makeAutomatic('2026-03-01', $source->name);
        $this->makeAutomatic('2026-03-02', $source->name);
        touch($this->auto('2026-03-01'), time() + 3600); // copiar la carpeta les da una fecha más nueva: no debe importar
        touch($this->auto('2026-03-02'), time() - 400 * 86400);

        $this->library->pruneAutomatic($this->policy(self::BASIC));

        self::assertFileExists($this->auto('2026-03-01'));
        self::assertFileExists($this->auto('2026-03-02'));
    }

    public function testStaleLeftoverOfAnInterruptedRunIsRemovedButARecentOneIsKept(): void
    {
        @mkdir($this->dir, 0o775, true);
        file_put_contents($this->dir . '/nexo-fp-backup-auto-2026-01-01-023000.zip.part', 'x');
        touch($this->dir . '/nexo-fp-backup-auto-2026-01-01-023000.zip.part', time() - 8 * 3600);
        file_put_contents($this->dir . '/nexo-fp-backup-auto-2026-01-02-023000.zip.part', 'x');

        $this->library->pruneAutomatic($this->policy(self::BASIC));

        self::assertFileDoesNotExist($this->dir . '/nexo-fp-backup-auto-2026-01-01-023000.zip.part');
        self::assertFileExists($this->dir . '/nexo-fp-backup-auto-2026-01-02-023000.zip.part');
    }

    public function testLastBackupIsTheNewestZipAndNullWhenThereAreNone(): void
    {
        self::assertNull($this->library->lastBackupAt());

        $old = $this->library->save();
        touch($this->dir . '/' . $old->name, time() - 10 * 86400);
        file_put_contents($this->dir . '/nexo-fp-backup-auto-2026-01-01-023000.zip.part', 'x'); // no cuenta

        self::assertEqualsWithDelta(time() - 10 * 86400, $this->library->lastBackupAt(), 2);
    }

    public function testUsageCountsTheBackupsAndTheirSize(): void
    {
        self::assertSame(['bytes' => 0, 'count' => 0], $this->library->usage());

        $stored = $this->library->save();
        file_put_contents($this->dir . '/nexo-fp-backup-auto-2026-01-01-023000.zip.part', 'x'); // a medias: no cuenta

        self::assertSame(['bytes' => $stored->size, 'count' => 1], $this->library->usage());
    }

    public function testStatusWarnsWhenThereIsNoRecentBackup(): void
    {
        $service = $this->service();

        self::assertTrue($service->status()['stale']);
        self::assertNull($service->status()['last_backup_at']);

        $this->library->save();
        self::assertFalse($service->status()['stale']);

        touch($this->dir . '/' . $this->library->list()[0]->name, time() - 8 * 86400);
        self::assertTrue($service->status()['stale']);
    }

    public function testRunDoesNothingWhenAutomaticBackupsAreOff(): void
    {
        $service = $this->service();
        self::assertFalse($service->isEnabled());

        $service->run();

        self::assertSame([], $this->library->list());
        self::assertNull($service->status()['last_run']);
    }

    public function testRunSavesPrunesAndRecordsTheResultWhenOn(): void
    {
        $service = $this->service(true);
        $source  = $this->library->save();
        foreach (range(1, 8) as $day) {
            $this->makeAutomatic(\sprintf('2026-01-%02d', $day), $source->name);
        }

        $service->run();

        // 8 antiguas + la nueva = 9 automáticas; con 7 diarias se conservan la nueva y las 6 antiguas más recientes.
        self::assertCount(7, glob($this->dir . '/nexo-fp-backup-auto-*.zip') ?: []);
        $run = $service->status()['last_run'];
        self::assertIsArray($run);
        self::assertTrue($run['ok']);
        self::assertSame(2, $run['pruned']);
        self::assertFalse($service->status()['stale']);
    }

    public function testRunRecordsAFailureWithoutThrowing(): void
    {
        $service = $this->service(true);
        // Un fichero donde debería estar la carpeta de copias hace imposible la copia.
        $this->cleanSystemFiles();
        @mkdir(\dirname($this->dir), 0o775, true);
        file_put_contents($this->dir, 'no es una carpeta');

        try {
            $service->run();
        } finally {
            @unlink($this->dir);
        }

        $run = $service->status()['last_run'];
        self::assertIsArray($run);
        self::assertFalse($run['ok']);
        self::assertNotSame('', $run['error']);
    }

    public function testTheSizeLimitDeletesTheOldestUntilTheyFitButAlwaysKeepsTheNewest(): void
    {
        $source = $this->library->save();
        $size   = (int) filesize($this->dir . '/' . $source->name);
        foreach (range(1, 4) as $day) {
            $this->makeAutomatic(\sprintf('2026-01-%02d', $day), $source->name);
        }

        // Caben dos y media: se quedan las dos más recientes.
        self::assertSame(2, $this->library->pruneAutomatic($this->policy(self::BASIC), (int) ($size * 2.5)));
        self::assertCount(2, glob($this->dir . '/nexo-fp-backup-auto-*.zip') ?: []);
        self::assertFileExists($this->auto('2026-01-04'));

        // Un límite menor que una copia deja igualmente la más reciente.
        self::assertSame(1, $this->library->pruneAutomatic($this->policy(self::BASIC), 1));
        self::assertSame([$this->auto('2026-01-04')], glob($this->dir . '/nexo-fp-backup-auto-*.zip'));
        // La manual no se cuenta ni se borra nunca.
        self::assertFileExists($this->dir . '/' . $source->name);
    }

    public function testSettingsDriveTheStatus(): void
    {
        $status = $this->service(true, maxMb: 300)->status();

        self::assertTrue($status['enabled']);
        self::assertSame(['daily' => 7, 'weekly' => 4, 'monthly' => 3], $status['tiers']);
        self::assertSame(300, $status['max_size_mb']);
    }

    public function testTheSettingsAreReadFromTheRealGlobalSettings(): void
    {
        $enabled = (new SettingDefinition())->setKey('backup.auto_enabled')->setType(SettingType::Boolean)
            ->setDefaultValue('false')->setGlobalScope(true);
        $daily = (new SettingDefinition())->setKey('backup.auto_keep_daily')->setType(SettingType::Integer)
            ->setDefaultValue('7')->setMinValue(1)->setMaxValue(60)->setGlobalScope(true);
        $weekly = (new SettingDefinition())->setKey('backup.auto_keep_weekly')->setType(SettingType::Integer)
            ->setDefaultValue('4')->setMinValue(0)->setMaxValue(52)->setGlobalScope(true);
        $monthly = (new SettingDefinition())->setKey('backup.auto_keep_monthly')->setType(SettingType::Integer)
            ->setDefaultValue('3')->setMinValue(0)->setMaxValue(36)->setGlobalScope(true);
        $size = (new SettingDefinition())->setKey('backup.auto_max_size_mb')->setType(SettingType::Integer)
            ->setDefaultValue('0')->setMinValue(0)->setMaxValue(1048576)->setGlobalScope(true);
        $this->persist($enabled, $daily, $weekly, $monthly, $size);

        /** @var AppSettings $settings */
        $settings = self::getContainer()->get(AppSettings::class);
        $settings->invalidate();

        $service = new AutomaticBackup($this->library, $this->systemState(), new NullLogger(), $settings, 7);

        self::assertFalse($service->isEnabled()); // el valor por defecto: desactivadas
        self::assertSame(['daily' => 7, 'weekly' => 4, 'monthly' => 3], $service->policy()->tiers());
        self::assertSame(0, $service->maxSizeMb());

        $this->persist((new GlobalSettingValue())->setDefinition($enabled)->setValue('true'));
        $this->persist((new GlobalSettingValue())->setDefinition($daily)->setValue('14'));
        $this->persist((new GlobalSettingValue())->setDefinition($size)->setValue('500'));
        $settings->invalidate();

        self::assertTrue($service->isEnabled());
        self::assertSame(['daily' => 14, 'weekly' => 4, 'monthly' => 3], $service->policy()->tiers());
        self::assertSame(500, $service->maxSizeMb());
    }

    /** Copia un zip existente como la copia automática de ese día (02:30). */
    private function makeAutomatic(string $date, string $sourceName): void
    {
        copy($this->dir . '/' . $sourceName, $this->auto($date));
    }

    private function auto(string $date): string
    {
        return \sprintf('%s/nexo-fp-backup-auto-%s-023000.zip', $this->dir, $date);
    }

    /** @param string $tiers «diarias,semanales,mensuales» */
    private function policy(string $tiers): BackupRetentionPolicy
    {
        [$daily, $weekly, $monthly] = array_map('intval', explode(',', $tiers));

        return new BackupRetentionPolicy($daily, $weekly, $monthly);
    }

    private function systemState(): SystemState
    {
        /** @var SystemState $state */
        $state = self::getContainer()->get(SystemState::class);

        return $state;
    }

    private function service(bool $enabled = false, int $maxMb = 0): AutomaticBackup
    {
        $settings = $this->createStub(AppSettingsInterface::class);
        $settings->method('getGlobal')->willReturnCallback(static fn (string $key): mixed => match ($key) {
            'backup.auto_enabled'      => $enabled,
            'backup.auto_keep_daily'   => 7,
            'backup.auto_keep_weekly'  => 4,
            'backup.auto_keep_monthly' => 3,
            'backup.auto_max_size_mb'  => $maxMb,
            default                    => null,
        });

        return new AutomaticBackup($this->library, $this->systemState(), new NullLogger(), $settings, 7);
    }
}
