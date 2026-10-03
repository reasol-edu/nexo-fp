<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\PersonName;
use App\Entity\Worker;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class BackupCommandTest extends RepositoryTestCase
{
    private CommandTester $tester;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $this->tester = new CommandTester($application->find('app:backup'));

        $this->outputDir = sys_get_temp_dir() . '/nexo-backup-test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->outputDir)) {
            $entries = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->outputDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($entries as $entry) {
                /** @var \SplFileInfo $entry */
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($this->outputDir);
        }

        parent::tearDown();
    }

    public function testWritesAZipWithAManifestAndOneNdjsonPerTable(): void
    {
        $this->persist($this->worker('one'), $this->worker('two'));

        $this->tester->execute(['destination' => $this->outputDir]);

        self::assertSame(0, $this->tester->getStatusCode());

        $zip      = $this->openTheArchive();
        $manifest = $this->manifestOf($zip);

        self::assertSame('nexo-fp-backup', $manifest['format']);
        self::assertSame(1, $manifest['formatVersion']);
        self::assertFalse($manifest['encrypted']);
        self::assertArrayHasKey('databasePlatform', $manifest);

        // Se listan todas las tablas, también las vacías...
        self::assertArrayHasKey('teacher', $manifest['tables']);
        self::assertSame(0, $manifest['tables']['teacher']);
        // «group» es palabra reservada: PostgreSQL la devuelve entrecomillada y no debe llegar así al fichero.
        self::assertArrayHasKey('group', $manifest['tables']);
        foreach (array_keys($manifest['tables']) as $table) {
            self::assertSame($table, trim($table, "\"`"));
        }
        // ...y la que tiene datos lleva su recuento real.
        self::assertSame(2, $manifest['tables']['worker']);

        $ndjson = $zip->getFromName('tables/worker.ndjson');
        self::assertIsString($ndjson);
        $lines = array_values(array_filter(explode("\n", $ndjson), static fn (string $l): bool => $l !== ''));
        self::assertCount(2, $lines);
    }

    public function testExcludesTheTransientMessengerQueue(): void
    {
        $this->tester->execute(['destination' => $this->outputDir]);

        $zip      = $this->openTheArchive();
        $manifest = $this->manifestOf($zip);

        self::assertArrayNotHasKey('messenger_messages', $manifest['tables']);
        self::assertFalse($zip->locateName('tables/messenger_messages.ndjson'));
    }

    public function testKeepsTextAndBinaryValuesExactly(): void
    {
        $worker = $this->worker('texto', "Mª José \"la\ndel\tñandú\" 🙂");
        $this->persist($worker);

        $this->tester->execute(['destination' => $this->outputDir]);

        $zip    = $this->openTheArchive();
        $ndjson = $zip->getFromName('tables/worker.ndjson');
        self::assertIsString($ndjson);

        /** @var array<string, mixed> $row */
        $row = json_decode(trim($ndjson), true, flags: JSON_THROW_ON_ERROR);

        // El texto UTF-8 se conserva tal cual (saltos de línea y comillas incluidos).
        self::assertSame("Mª José \"la\ndel\tñandú\" 🙂", $row['name_first_name']);

        // El identificador (bytes aleatorios, no UTF-8) viaja como texto legible o envuelto en base64,
        // y en ambos casos se recupera idéntico.
        $id = \is_array($row['id']) ? base64_decode((string) $row['id']['@b64'], true) : $row['id'];
        self::assertContains($id, [$worker->getId()->toBinary(), $worker->getId()->toRfc4122()]);
    }

    public function testAcceptsAnExplicitZipPath(): void
    {
        $target = $this->outputDir . '/nested/mi-copia.zip';

        $this->tester->execute(['destination' => $target]);

        self::assertSame(0, $this->tester->getStatusCode());
        self::assertFileExists($target);
    }

    public function testIsNotEncryptedByDefault(): void
    {
        $this->tester->execute(['destination' => $this->outputDir]);

        $zip = $this->openTheArchive();
        self::assertFalse($this->manifestOf($zip)['encrypted']);
        self::assertSame(\ZipArchive::EM_NONE, $zip->statName('manifest.json')['encryption_method']);
    }

    public function testEncryptsEveryEntryWhenAPasswordIsPassed(): void
    {
        $this->persist($this->worker('secret'));

        $this->tester->execute([
            'destination' => $this->outputDir,
            '--password'  => 's3cr3t-passphrase',
        ]);

        self::assertSame(0, $this->tester->getStatusCode());

        $zip = $this->openTheArchive();

        // Todas las entradas llevan AES-256, el manifiesto y los volcados de tablas.
        self::assertSame(\ZipArchive::EM_AES_256, $zip->statName('manifest.json')['encryption_method']);
        self::assertSame(\ZipArchive::EM_AES_256, $zip->statName('tables/worker.ndjson')['encryption_method']);

        // Solo la contraseña correcta descifra, y el manifiesto deja constancia.
        $zip->setPassword('s3cr3t-passphrase');
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($manifest['encrypted']);
        self::assertSame(1, $manifest['tables']['worker']);
        self::assertIsString($zip->getFromName('tables/worker.ndjson'));
    }

    public function testPromptsForThePasswordWhenTheOptionCarriesNoValue(): void
    {
        $this->tester->setInputs(['prompted-pass', 'prompted-pass']);
        $this->tester->execute([
            'destination' => $this->outputDir,
            '--password'  => null,
        ]);

        self::assertSame(0, $this->tester->getStatusCode());

        $zip = $this->openTheArchive();
        self::assertSame(\ZipArchive::EM_AES_256, $zip->statName('manifest.json')['encryption_method']);
        $zip->setPassword('prompted-pass');
        self::assertTrue(json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR)['encrypted']);
    }

    public function testFailsWhenThePromptedPasswordsDoNotMatch(): void
    {
        $this->tester->setInputs(['one', 'two']);
        $this->tester->execute([
            'destination' => $this->outputDir,
            '--password'  => null,
        ]);

        self::assertSame(2, $this->tester->getStatusCode());
        self::assertStringContainsString('no coinciden', $this->tester->getDisplay());
        self::assertSame([], glob($this->outputDir . '/*.zip') ?: []);
    }

    public function testFailsWhenAskedToPromptNonInteractively(): void
    {
        $this->tester->execute(
            ['destination' => $this->outputDir, '--password' => null],
            ['interactive' => false],
        );

        self::assertSame(2, $this->tester->getStatusCode());
        self::assertSame([], glob($this->outputDir . '/*.zip') ?: []);
    }

    private function worker(string $seed, ?string $firstName = null): Worker
    {
        return (new Worker(new PersonName($firstName ?? 'Nombre ' . $seed, 'Apellido ' . $seed)))
            ->setNationalIdNumber('DNI-' . $seed);
    }

    private function openTheArchive(): \ZipArchive
    {
        $zips = glob($this->outputDir . '/*.zip') ?: [];
        self::assertCount(1, $zips, 'se escribe exactamente una copia de seguridad');

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($zips[0]) === true);

        return $zip;
    }

    /** @return array<string, mixed> */
    private function manifestOf(\ZipArchive $zip): array
    {
        $json = $zip->getFromName('manifest.json');
        self::assertIsString($json);

        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $manifest;
    }
}
