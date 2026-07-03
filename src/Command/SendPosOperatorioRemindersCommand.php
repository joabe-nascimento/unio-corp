<?php

namespace App\Command;

use App\Repository\EmpresaRepository;
use App\Service\PosOperatorio\PosOperatorioReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pos-operatorio:send-reminders',
    description: 'Envia lembretes de questionários pendentes (cron diário)',
)]
final class SendPosOperatorioRemindersCommand extends Command
{
    public function __construct(
        private EmpresaRepository $empresaRepo,
        private PosOperatorioReminderService $reminders,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('empresa-id', null, InputOption::VALUE_OPTIONAL, 'Limitar a uma empresa');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $empresaId = $input->getOption('empresa-id');
        $empresas = $empresaId
            ? array_filter([$this->empresaRepo->find((int) $empresaId)])
            : $this->empresaRepo->findBy(['ativo' => true]);

        $totalEnviados = 0;
        $totalIgnorados = 0;
        $totalSemMedico = 0;

        foreach ($empresas as $empresa) {
            if (!$empresa) {
                continue;
            }
            $result = $this->reminders->sendPendingQuestionnaireReminders($empresa);
            $totalEnviados += $result['enviados'];
            $totalIgnorados += $result['ignorados'];
            $totalSemMedico += $result['sem_medico'];
            $io->writeln(sprintf(
                'Empresa %s: %d lembrete(s), %d ignorado(s), %d sem médico',
                $empresa->getNome(),
                $result['enviados'],
                $result['ignorados'],
                $result['sem_medico'],
            ));
        }

        $io->success(sprintf(
            'Concluído — %d enviados, %d ignorados, %d sem médico responsável.',
            $totalEnviados,
            $totalIgnorados,
            $totalSemMedico,
        ));

        return Command::SUCCESS;
    }
}
