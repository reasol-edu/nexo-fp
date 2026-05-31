<?php

namespace App\Command;

use App\Entity\PersonName;
use App\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:create-admin')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $t = fn(string $key) => $this->translator->trans($key, domain: 'command');

        $this
            ->setDescription($t('create_admin.description'))
            ->addArgument('username', InputArgument::OPTIONAL, $t('create_admin.argument.username'), 'admin')
            ->addArgument('password', InputArgument::OPTIONAL, $t('create_admin.argument.password'), 'admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $password = $input->getArgument('password');

        if ($this->em->getRepository(Teacher::class)->findOneBy(['username' => $username]) !== null) {
            $io->error($this->translator->trans(
                'create_admin.error.existing_user',
                ['%username%' => $username],
                'command',
            ));

            return Command::FAILURE;
        }

        $teacher = new Teacher(new PersonName('Admin', 'User'));
        $teacher->setUsername($username);
        $teacher->setPassword($this->passwordHasher->hashPassword($teacher, $password));
        $teacher->setAdmin(true);

        $this->em->persist($teacher);
        $this->em->flush();

        $io->success($this->translator->trans(
            'create_admin.success',
            ['%username%' => $username],
            'command',
        ));

        return Command::SUCCESS;
    }
}
