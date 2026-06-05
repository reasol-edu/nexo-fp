<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateAdminCommand;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Repository\TeacherRepository;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminCommandTest extends RepositoryTestCase
{
    private CommandTester $tester;
    private TeacherRepository $teachers;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CreateAdminCommand $command */
        $command = self::getContainer()->get(CreateAdminCommand::class);

        /** @var TeacherRepository $teachers */
        $teachers       = self::getContainer()->get(TeacherRepository::class);
        $this->teachers = $teachers;

        $this->tester = new CommandTester($command);
    }

    // ── éxito ─────────────────────────────────────────────────────────────────

    public function testCreateAdminCreatesTeacherWithAdminFlag(): void
    {
        $status = $this->tester->execute([
            'username' => 'admin',
            'password' => 'secret123',
        ]);

        self::assertSame(Command::SUCCESS, $status);

        $teacher = $this->teachers->findByUsername('admin');
        self::assertNotNull($teacher);
        self::assertTrue($teacher->isAdmin());
        self::assertTrue($teacher->isActive());
    }

    public function testCreateAdminHashesPassword(): void
    {
        $this->tester->execute([
            'username' => 'admin',
            'password' => 'secret123',
        ]);

        $teacher = $this->teachers->findByUsername('admin');
        self::assertNotNull($teacher);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($teacher, 'secret123'));
    }

    public function testCreateAdminDefaultUsernameIsAdmin(): void
    {
        // Invocar sin argumentos → usa el valor por defecto 'admin'
        $status = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertNotNull($this->teachers->findByUsername('admin'));
    }

    public function testCreateAdminOutputContainsSuccessMessage(): void
    {
        $this->tester->execute(['username' => 'admin', 'password' => 'pass']);

        $output = $this->tester->getDisplay();
        self::assertStringContainsString('admin', $output);
    }

    // ── error: usuario duplicado ──────────────────────────────────────────────

    public function testCreateAdminFailsWhenUsernameAlreadyExists(): void
    {
        $existing = (new Teacher(new PersonName('Existing', 'User')))->setUsername('admin');
        $this->persist($existing);

        $status = $this->tester->execute([
            'username' => 'admin',
            'password' => 'newpass',
        ]);

        self::assertSame(Command::FAILURE, $status);
    }

    public function testCreateAdminDoesNotCreateSecondTeacherOnDuplicateUsername(): void
    {
        $existing = (new Teacher(new PersonName('Existing', 'User')))->setUsername('admin');
        $this->persist($existing);

        $this->tester->execute(['username' => 'admin', 'password' => 'newpass']);

        self::assertSame(1, $this->teachers->countAll());
    }
}
