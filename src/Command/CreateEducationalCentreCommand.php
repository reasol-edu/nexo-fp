<?php

namespace App\Command;

use App\Entity\EducationalCentre;
use App\Repository\EducationalCentreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:create-educational-centre')]
class CreateEducationalCentreCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EducationalCentreRepository $centres,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $t = fn(string $key) => $this->translator->trans($key, domain: 'command');

        $this
            ->setDescription($t('create_centre.description'))
            ->addArgument('code', InputArgument::OPTIONAL, $t('create_centre.argument.code'))
            ->addArgument('name', InputArgument::OPTIONAL, $t('create_centre.argument.name'))
            ->addArgument('city', InputArgument::OPTIONAL, $t('create_centre.argument.city'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $t  = fn(string $key, array $params = []) => $this->translator->trans($key, $params, 'command');

        $code = $input->getArgument('code')
            ?? $io->ask($t('create_centre.ask.code'), validator: $this->notBlank());
        $name = $input->getArgument('name')
            ?? $io->ask($t('create_centre.ask.name'), validator: $this->notBlank());
        $city = $input->getArgument('city')
            ?? $io->ask($t('create_centre.ask.city'), validator: $this->notBlank());

        if ($this->centres->findByCode($code) !== null) {
            $io->error($t('create_centre.error.existing_code', ['%code%' => $code]));

            return Command::FAILURE;
        }

        $centre = new EducationalCentre();
        $centre->setCode($code);
        $centre->setName($name);
        $centre->setCity($city);

        $this->em->persist($centre);
        $this->em->flush();

        $io->success($t('create_centre.success', ['%name%' => $name, '%code%' => $code]));

        return Command::SUCCESS;
    }

    private function notBlank(): \Closure
    {
        return static function (?string $value): string {
            if ($value === null || trim($value) === '') {
                throw new \RuntimeException('Este campo es obligatorio.');
            }

            return $value;
        };
    }
}
