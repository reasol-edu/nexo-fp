<?php

namespace App\Command;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:setup', description: 'Inicializa la aplicación con datos por defecto si la base de datos está vacía')]
class SetupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TeacherRepository $teachers,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $t  = fn(string $key) => $this->translator->trans($key, domain: 'command');

        if ($this->teachers->countAll() > 0) {
            $io->note($t('setup.skipped'));

            return Command::SUCCESS;
        }

        $year     = (int) (new \DateTimeImmutable())->format('Y');
        $yearName = $year . '-' . ($year + 1);

        $centre = new EducationalCentre();
        $centre->setCode('23999999');
        $centre->setName('IES Test');
        $centre->setCity('Linares');

        $academicYear = (new AcademicYear())
            ->setName($yearName)
            ->setEducationalCentre($centre);

        $centre->setActiveAcademicYear($academicYear);

        $teacher = new Teacher(new PersonName('Admin', 'User'));
        $teacher->setUsername('admin');
        $teacher->setPassword($this->passwordHasher->hashPassword($teacher, 'admin'));
        $teacher->setAdmin(true);

        $this->em->persist($centre);
        $this->em->persist($academicYear);
        $this->em->persist($teacher);
        $this->em->flush();

        $io->success($t('setup.success'));

        return Command::SUCCESS;
    }
}
