<?php

namespace App\Command;

use App\Repository\EmpresaRepository;
use App\Service\PosOperatorio\PosOperatorioEscalationService;
use App\Service\PosOperatorio\PosOperatorioReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pos-operatorio:send-reminders',
    description: 'Envia lembretes de questionários pendentes e processa escalação de alertas (cron diário)',
)]
final class SendPosOperatorioRemindersCommand extends Command
{
    public function __construct(
        private EmpresaRepository $empresaRepo,
        private PosOperatorioReminderService $reminders,
        private PosOperatorioEscalationService $escalation,
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
        $totalPacientes = 0;
        $totalEscalados = 0;

        foreach ($empresas as $empresa) {
            if (!$empresa) {
                continue;
            }
            $result = $this->reminders->sendPendingQuestionnaireReminders($empresa);
            $escalation = $this->escalation->processOpenAlerts($empresa);
            $totalEnviados += $result['enviados'];
            $totalIgnorados += $result['ignorados'];
            $totalSemMedico += $result['sem_medico'];
            $totalPacientes += $result['pacientes'];
            $totalEscalados += $escalation['escalados'];
            $io->writeln(sprintf(
                'Empresa %s: %d lembrete(s), %d paciente(s) notificado(s), %d ignorado(s), %d sem médico, %d escalação(ões)',
                $empresa->getNome(),
                $result['enviados'],
                $result['pacientes'],
                $result['ignorados'],
                $result['sem_medico'],
                $escalation['escalados'],
            ));
        }

        $io->success(sprintf(
            'Concluído — %d lembretes, %d pacientes, %d ignorados, %d sem médico, %d escalações.',
            $totalEnviados,
            $totalPacientes,
            $totalIgnorados,
            $totalSemMedico,
            $totalEscalados,
        ));

        return Command::SUCCESS;
    }
}
