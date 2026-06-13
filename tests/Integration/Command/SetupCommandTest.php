<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SetupCommand;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Repository\EducationalCentreRepository;
use App\Repository\TeacherRepository;
use App\Tests\Integration\RepositoryTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SetupCommandTest extends RepositoryTestCase
{
    private CommandTester $tester;
    private TeacherRepository $teachers;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var SetupCommand $command */
        $command = self::getContainer()->get(SetupCommand::class);

        /** @var TeacherRepository $teachers */
        $teachers       = self::getContainer()->get(TeacherRepository::class);
        $this->teachers = $teachers;

        $this->tester = new CommandTester($command);
    }

    public function testSeedsDefaultAdminOnEmptyDatabase(): void
    {
        $status = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);

        $admin = $this->teachers->findByUsername('admin');
        self::assertNotNull($admin);
        self::assertTrue($admin->isAdmin());
    }

    public function testSeededAdminPasswordIsHashed(): void
    {
        $this->tester->execute([]);

        $admin = $this->teachers->findByUsername('admin');
        self::assertNotNull($admin);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($admin, 'admin'));
    }

    public function testCreatesCentreWithActiveAcademicYear(): void
    {
        $this->tester->execute([]);
        $this->em->clear();

        /** @var EducationalCentreRepository $centresRepo */
        $centresRepo = self::getContainer()->get(EducationalCentreRepository::class);
        $centres     = $centresRepo->findAllOrderedByName();
        self::assertCount(1, $centres);
        self::assertNotNull($centres[0]->getActiveAcademicYear());
    }

    public function testSkipsWhenTeachersAlreadyExist(): void
    {
        $existing = (new Teacher(new PersonName('Existing', 'User')))->setUsername('existing.user');
        $this->persist($existing);

        $status = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        // No new "admin" teacher created; the count is unchanged.
        self::assertSame(1, $this->teachers->countAll());
        self::assertNull($this->teachers->findByUsername('admin'));
    }
}
