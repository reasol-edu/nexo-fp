<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\WeeklyDigestDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:send-weekly-digest')]
class SendWeeklyDigestCommand extends Command
{
    public function __construct(
        private readonly WeeklyDigestDispatcher $dispatcher,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription($this->translator->trans('send_weekly_digest.description', domain: 'command'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stats = $this->dispatcher->dispatch();

        $io->success($this->translator->trans(
            'send_weekly_digest.success',
            ['%count%' => $stats['recipients']],
            'command',
        ));

        return Command::SUCCESS;
    }
}
