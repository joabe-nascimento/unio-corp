<?php

namespace App\Command;

use App\Repository\EmpresaRepository;
use App\Service\Rh\RhEsocialService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rh-esocial-process-queue',
    description: 'Processa a fila de lotes eSocial pendentes',
)]
class RhEsocialProcessQueueCommand extends Command
{
    public function __construct(
        private RhEsocialService $esocial,
        private EmpresaRepository $empresaRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('empresa-id', null, InputOption::VALUE_REQUIRED, 'Processar apenas esta empresa')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Máximo de lotes por execução', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));
        $empresaId = $input->getOption('empresa-id');

        if ($empresaId !== null) {
            $empresa = $this->empresaRepo->find((int) $empresaId);
            if ($empresa === null) {
                $io->error('Empresa não encontrada.');

                return Command::FAILURE;
            }
            $stats = $this->esocial->processQueue($empresa, $limit);
        } else {
            $stats = $this->esocial->processGlobalQueue($limit);
        }

        $io->success(sprintf(
            'Fila processada: %d lote(s) — %d enviado(s), %d erro(s).',
            $stats['processados'],
            $stats['enviados'],
            $stats['erros'],
        ));

        return Command::SUCCESS;
    }
}
