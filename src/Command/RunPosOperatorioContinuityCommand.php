<?php

namespace App\Command;

use App\Service\PosOperatorio\ClinicContinuityService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pos-operatorio:continuity',
    description: 'Continuidade clínica — lembretes de questionário e escalação de alertas',
)]
final class RunPosOperatorioContinuityCommand extends Command
{
    public function __construct(
        private ClinicContinuityService $continuity,
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
        $result = $this->continuity->runAll($empresaId !== null ? (int) $empresaId : null);

        $io->success(sprintf(
            'Continuidade: %d clínica(s), %d lembrete(s), %d escalação(ões).',
            $result['empresas'],
            $result['lembretes'],
            $result['escalacoes'],
        ));

        return Command::SUCCESS;
    }
}
