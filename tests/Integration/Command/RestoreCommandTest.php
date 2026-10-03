<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\PersonName;
use App\Entity\Worker;
use App\Tests\Integration\RepositoryTestCase;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class RestoreCommandTest extends RepositoryTestCase
{
    private Application $application;
    private CommandTester $restore;
    private Connection $connection;
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $this->application = new Application($kernel);
        $this->restore     = new CommandTester($this->application->find('app:restore'));

        /** @var Connection $connection */
        $connection       = self::getContainer()->get('doctrine.dbal.default_connection');
        $this->connection = $connection;

        $this->workDir = sys_get_temp_dir() . '/nexo-restore-test-' . bin2hex(random_bytes(6));
        mkdir($this->workDir, 0o700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->workDir)) {
            $entries = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->workDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($entries as $entry) {
                /** @var \SplFileInfo $entry */
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($this->workDir);
        }

        parent::tearDown();
    }

    public function testRoundTripRestoresExactlyWhatWasBackedUp(): void
    {
        $special = $this->worker('b', "Mª José \"la\ndel\tñandú\" 🙂");
        $this->persist($this->worker('a'), $special);
        $originalId = $this->connection->fetchOne('SELECT id FROM worker WHERE national_id_number = ?', ['DNI-b']);

        $archive = $this->backup();

        // Deriva: aparece una fila más después de la copia.
        $this->persist($this->worker('c'));
        self::assertSame(3, $this->countRows('worker'));

        $this->restore->execute(['archive' => $archive, '--force' => true]);

        self::assertSame(0, $this->restore->getStatusCode(), $this->restore->getDisplay());
        self::assertSame(2, $this->countRows('worker'), 'la fila posterior a la copia ha desaparecido');

        $row = $this->connection->fetchAssociative('SELECT id, name_first_name FROM worker WHERE national_id_number = ?', ['DNI-b']);
        self::assertIsArray($row);
        self::assertSame("Mª José \"la\ndel\tñandú\" 🙂", $row['name_first_name']);
        self::assertSame($this->raw($originalId), $this->raw($row['id']), 'el identificador (binario) se restaura idéntico');
    }

    public function testRestoresAnEncryptedArchive(): void
    {
        $this->persist($this->worker('x'));

        $archive = $this->backup('cl4ve-secreta');
        $this->truncate('worker');
        self::assertSame(0, $this->countRows('worker'));

        $this->restore->execute([
            'archive'    => $archive,
            '--password' => 'cl4ve-secreta',
            '--force'    => true,
        ]);

        self::assertSame(0, $this->restore->getStatusCode(), $this->restore->getDisplay());
        self::assertSame(1, $this->countRows('worker'));
    }

    public function testFailsWithTheWrongPassword(): void
    {
        $this->persist($this->worker('x'));
        $archive = $this->backup('la-buena');
        $this->truncate('worker');

        $this->restore->execute([
            'archive'    => $archive,
            '--password' => 'la-mala',
            '--force'    => true,
        ]);

        self::assertSame(1, $this->restore->getStatusCode());
        self::assertSame(0, $this->countRows('worker'), 'una restauración fallida no cambia nada');
    }

    public function testRefusesToRunUnattendedWithoutForce(): void
    {
        $this->persist($this->worker('x'));
        $archive = $this->backup();
        $this->persist($this->worker('y'));

        $this->restore->execute(['archive' => $archive], ['interactive' => false]); // sin --force

        self::assertSame(2, $this->restore->getStatusCode()); // Command::INVALID
        self::assertSame(2, $this->countRows('worker'), 'no se ha tocado nada');
    }

    public function testInteractiveConfirmationCanAbort(): void
    {
        $this->persist($this->worker('x'));
        $archive = $this->backup();
        $this->persist($this->worker('y'));

        $this->restore->setInputs(['no']);
        $this->restore->execute(['archive' => $archive]);

        self::assertSame(0, $this->restore->getStatusCode());
        self::assertStringContainsString('cancelada', $this->restore->getDisplay());
        self::assertSame(2, $this->countRows('worker'));
    }

    public function testFailsWhenTheArchiveIsMissing(): void
    {
        $this->restore->execute([
            'archive' => $this->workDir . '/no-existe.zip',
            '--force' => true,
        ]);

        self::assertSame(1, $this->restore->getStatusCode());
    }

    public function testRefusesAFileThatIsNotANexoBackup(): void
    {
        $zip = new \ZipArchive();
        $path = $this->workDir . '/otro.zip';
        self::assertTrue($zip->open($path, \ZipArchive::CREATE) === true);
        $zip->addFromString('manifest.json', json_encode(['format' => 'otra-aplicacion', 'formatVersion' => 1, 'tables' => []], JSON_THROW_ON_ERROR));
        $zip->close();

        $this->restore->execute(['archive' => $path, '--force' => true]);

        self::assertSame(1, $this->restore->getStatusCode());
        self::assertStringContainsString('No se pudo leer la copia de seguridad', $this->restore->getDisplay());
    }

    public function testRefusesASchemaMismatchUnlessForced(): void
    {
        $this->persist($this->worker('x'));
        $archive = $this->backup();
        $this->retagSchemaVersion($archive, 'DoctrineMigrations\\Version99999999999999');
        $this->truncate('worker');

        // Confirmado por quien opera, pero la guarda del esquema sigue negándose sin --force.
        $this->restore->setInputs(['yes']);
        $this->restore->execute(['archive' => $archive]);
        self::assertSame(1, $this->restore->getStatusCode());
        self::assertStringContainsString('esquema', $this->restore->getDisplay());
        self::assertSame(0, $this->countRows('worker'));

        // Con --force: se carga igualmente, con un aviso.
        $this->restore->execute(['archive' => $archive, '--force' => true]);
        self::assertSame(0, $this->restore->getStatusCode(), $this->restore->getDisplay());
        self::assertStringContainsString('--force', $this->restore->getDisplay());
        self::assertSame(1, $this->countRows('worker'));
    }

    private function worker(string $seed, ?string $firstName = null): Worker
    {
        return (new Worker(new PersonName($firstName ?? 'Nombre ' . $seed, 'Apellido ' . $seed)))
            ->setNationalIdNumber('DNI-' . $seed);
    }

    private function backup(?string $password = null): string
    {
        $tester = new CommandTester($this->application->find('app:backup'));
        $args   = ['destination' => $this->workDir];
        if ($password !== null) {
            $args['--password'] = $password;
        }
        $tester->execute($args);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        $zips = glob($this->workDir . '/*.zip') ?: [];
        self::assertCount(1, $zips);

        return $zips[0];
    }

    private function raw(mixed $value): string
    {
        return \is_resource($value) ? (string) stream_get_contents($value) : (string) $value;
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $this->connection->quoteSingleIdentifier($table));
    }

    private function truncate(string $table): void
    {
        $this->connection->executeStatement('DELETE FROM ' . $this->connection->quoteSingleIdentifier($table));
    }

    private function retagSchemaVersion(string $archive, string $version): void
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($archive) === true);
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $manifest['schemaVersion'] = $version;
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();
    }
}
