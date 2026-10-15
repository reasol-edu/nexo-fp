<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EmailDigestSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:send-daily-digests')]
class SendDailyDigestsCommand extends Command
{
    public function __construct(
        private readonly EmailDigestSender $sender,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription($this->translator->trans('send_daily_digests.description', domain: 'command'))
            ->addOption('now', null, InputOption::VALUE_NONE, $this->translator->trans('send_daily_digests.option_now', domain: 'command'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sent = $this->sender->sendDue((bool) $input->getOption('now'));

        $io->success($this->translator->trans('send_daily_digests.success', ['%count%' => $sent], 'command'));

        return Command::SUCCESS;
    }
}
