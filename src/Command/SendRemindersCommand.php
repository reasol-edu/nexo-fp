<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SignatureReminderDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:send-reminders')]
class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly SignatureReminderDispatcher $dispatcher,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $t = fn(string $key) => $this->translator->trans($key, domain: 'command');

        $this
            ->setDescription($t('send_reminders.description'))
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, $t('send_reminders.option.days'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $daysOption = $input->getOption('days');
        $daysOverride = null;
        if ($daysOption !== null) {
            $daysOverride = filter_var($daysOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($daysOverride === false) {
                $io->error($this->translator->trans('send_reminders.error.days_invalid', domain: 'command'));

                return Command::FAILURE;
            }
        }

        $stats = $this->dispatcher->dispatch($daysOverride);

        $io->success($this->translator->trans(
            'send_reminders.success',
            ['%count%' => $stats['recipients']],
            'command',
        ));

        return Command::SUCCESS;
    }
}
