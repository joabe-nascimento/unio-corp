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
    description: 'Prepara lembretes de confirmação de agenda (D-1) — WhatsApp/e-mail/webhook',
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
            'Agenda D-1 — %d clínica(s), %d lembrete(s) preparados (%d sem telefone).',
            $result['empresas'],
            $result['enviados'],
            $result['sem_telefone'],
        ));

        return Command::SUCCESS;
    }
}
