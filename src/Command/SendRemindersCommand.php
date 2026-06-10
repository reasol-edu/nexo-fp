<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\TrainingPositionRepository;
use App\Service\StayNotifier;
use Symfony\Component\Clock\ClockInterface;
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
        private readonly TrainingPositionRepository $positions,
        private readonly StayNotifier $notifier,
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $t = fn(string $key) => $this->translator->trans($key, domain: 'command');

        $this
            ->setDescription($t('send_reminders.description'))
            ->addOption('days', null, InputOption::VALUE_REQUIRED, $t('send_reminders.option.days'), '7');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($days === false) {
            $io->error($this->translator->trans('send_reminders.error.days_invalid', domain: 'command'));

            return Command::FAILURE;
        }

        // Solo estancias que terminan exactamente en N días: con una ejecución
        // diaria (cron) cada puesto recibe un único recordatorio.
        $endDate = $this->clock->now()->setTime(0, 0, 0)->modify(sprintf('+%d days', $days));

        $byTutor       = [];
        $withoutTutor  = 0;
        foreach ($this->positions->findUnsignedWithStayEndingOn($endDate) as $position) {
            $tutor = $position->getAcademicTutor();
            if ($tutor === null) {
                $withoutTutor++;
                continue;
            }

            $tutorId = $tutor->getId()->toRfc4122();
            $byTutor[$tutorId] ??= ['tutor' => $tutor, 'positions' => []];
            $byTutor[$tutorId]['positions'][] = $position;
        }

        foreach ($byTutor as $entry) {
            $this->notifier->sendSignatureReminder($entry['tutor'], $entry['positions'], $days);
        }

        if ($withoutTutor > 0) {
            $io->warning($this->translator->trans(
                'send_reminders.warning.no_tutor',
                ['%count%' => $withoutTutor],
                'command',
            ));
        }

        $io->success($this->translator->trans(
            'send_reminders.success',
            ['%count%' => \count($byTutor), '%days%' => $days],
            'command',
        ));

        return Command::SUCCESS;
    }
}
