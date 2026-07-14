<?php

namespace App\Command;

use App\Service\PosOperatorio\ClinicAgendaReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clinic:agenda-reminders',
    description: 'Lembretes agenda D−1 + marcos Trilha D−7/D−3 + handoff D0',
)]
final class SendClinicAgendaRemindersCommand extends Command
{
    public function __construct(
        private ClinicAgendaReminderService $reminders,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('empresa', null, InputOption::VALUE_REQUIRED, 'ID da clínica (opcional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $empresaId = $input->getOption('empresa');
        $result = $this->reminders->runAll($empresaId !== null ? (int) $empresaId : null);

        $io->success(sprintf(
            'Trilha/agenda — %d clínica(s), %d D−1, %d marcos pré, %d handoff D0 (%d sem telefone).',
            $result['empresas'],
            $result['enviados'],
            $result['marcos'] ?? 0,
            $result['d0'] ?? 0,
            $result['sem_telefone'],
        ));

        return Command::SUCCESS;
    }
}
