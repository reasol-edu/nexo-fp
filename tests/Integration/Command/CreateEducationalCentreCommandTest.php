<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateEducationalCentreCommand;
use App\Repository\AcademicYearRepository;
use App\Repository\EducationalCentreRepository;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CreateEducationalCentreCommandTest extends RepositoryTestCase
{
    private CommandTester $tester;
    private EducationalCentreRepository $centres;
    private AcademicYearRepository $years;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CreateEducationalCentreCommand $command */
        $command = self::getContainer()->get(CreateEducationalCentreCommand::class);

        /** @var EducationalCentreRepository $centres */
        $centres       = self::getContainer()->get(EducationalCentreRepository::class);
        $this->centres = $centres;

        /** @var AcademicYearRepository $years */
        $years       = self::getContainer()->get(AcademicYearRepository::class);
        $this->years = $years;

        $this->tester = new CommandTester($command);
    }

    // ── éxito ─────────────────────────────────────────────────────────────────

    public function testCreateCentreCreatesCentreInDatabase(): void
    {
        $status = $this->tester->execute([
            'code' => '41000001',
            'name' => 'IES Prueba',
            'city' => 'Sevilla',
        ]);

        self::assertSame(Command::SUCCESS, $status);

        $centre = $this->centres->findByCode('41000001');
        self::assertNotNull($centre);
        self::assertSame('IES Prueba', $centre->getName());
        self::assertSame('Sevilla', $centre->getCity());
    }

    public function testCreateCentreCreatesInitialAcademicYear(): void
    {
        $this->tester->execute([
            'code' => '41000002',
            'name' => 'IES Ejemplo',
            'city' => 'Granada',
        ]);

        $centre = $this->centres->findByCode('41000002');
        self::assertNotNull($centre);

        $academicYears = $this->years->findByCentreOrderedByName($centre);
        self::assertCount(1, $academicYears);
    }

    public function testCreateCentreSetsActiveAcademicYear(): void
    {
        $this->tester->execute([
            'code' => '41000003',
            'name' => 'IES Nuevo',
            'city' => 'Malaga',
        ]);

        $centre = $this->centres->findByCode('41000003');
        self::assertNotNull($centre);
        self::assertNotNull($centre->getActiveAcademicYear());
    }

    public function testCreateCentreAcademicYearNameMatchesCurrentSchoolYear(): void
    {
        $this->tester->execute([
            'code' => '41000004',
            'name' => 'IES Actual',
            'city' => 'Cadiz',
        ]);

        $centre = $this->centres->findByCode('41000004');
        self::assertNotNull($centre);

        $year    = (int) (new \DateTimeImmutable())->format('Y');
        $yearName = $year . '-' . ($year + 1);

        $activeYear = $centre->getActiveAcademicYear();
        self::assertNotNull($activeYear);
        self::assertSame($yearName, $activeYear->getName());
    }

    public function testCreateCentreOutputContainsSuccessMessage(): void
    {
        $this->tester->execute([
            'code' => '41000005',
            'name' => 'IES OK',
            'city' => 'Huelva',
        ]);

        $output = $this->tester->getDisplay();
        self::assertStringContainsString('IES OK', $output);
    }

    // ── error: código duplicado ───────────────────────────────────────────────

    public function testCreateCentreFailsWhenCodeAlreadyExists(): void
    {
        // Primera creación
        $this->tester->execute([
            'code' => '41000006',
            'name' => 'IES Primera',
            'city' => 'Sevilla',
        ]);

        // Segunda con mismo código
        $status = $this->tester->execute([
            'code' => '41000006',
            'name' => 'IES Duplicada',
            'city' => 'Granada',
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testCreateCentreDoesNotCreateDuplicateCentre(): void
    {
        $this->tester->execute(['code' => '41000007', 'name' => 'IES Primera', 'city' => 'Sevilla']);
        $this->tester->execute(['code' => '41000007', 'name' => 'IES Duplicada', 'city' => 'Sevilla']);

        self::assertSame(1, $this->centres->countAll());
    }
}
