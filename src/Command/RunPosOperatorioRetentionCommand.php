<?php

namespace App\Command;

use App\Service\PosOperatorio\ClinicRetentionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pos-operatorio:retention',
    description: 'Anonimiza fichas encerradas além da política de retenção LGPD',
)]
final class RunPosOperatorioRetentionCommand extends Command
{
    public function __construct(
        private ClinicRetentionService $retention,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('empresa-id', null, InputOption::VALUE_OPTIONAL, 'Limitar a uma empresa')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Apenas contar elegíveis');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $empresaId = $input->getOption('empresa-id');
        $dryRun = (bool) $input->getOption('dry-run');
        $result = $this->retention->runAll(
            $empresaId !== null ? (int) $empresaId : null,
            $dryRun,
        );

        $io->success(sprintf(
            'Retenção%s: %d clínica(s), %d elegível(is), %d anonimizado(s).',
            $dryRun ? ' (dry-run)' : '',
            $result['empresas'],
            $result['elegiveis'],
            $result['anonimizados'],
        ));

        return Command::SUCCESS;
    }
}
