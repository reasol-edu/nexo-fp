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

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crea una cuenta de docente con privilegios de administrador global',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Nombre de usuario', 'admin')
            ->addArgument('password', InputArgument::OPTIONAL, 'Contraseña', 'admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $password = $input->getArgument('password');

        if ($this->em->getRepository(Teacher::class)->findOneBy(['username' => $username]) !== null) {
            $io->error(sprintf('Ya existe un docente con el nombre de usuario "%s".', $username));

            return Command::FAILURE;
        }

        $teacher = new Teacher(new PersonName('Admin', 'User'));
        $teacher->setUsername($username);
        $teacher->setPassword($this->passwordHasher->hashPassword($teacher, $password));
        $teacher->setAdmin(true);

        $this->em->persist($teacher);
        $this->em->flush();

        $io->success(sprintf('Administrador "%s" creado correctamente.', $username));

        return Command::SUCCESS;
    }
}
