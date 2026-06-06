<?php

namespace App\Command;

use App\Repository\EmpresaRepository;
use App\Service\Rh\RhRecrutamentoEmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rh-email-process-queue',
    description: 'Processa a fila de e-mails do RH (recrutamento, comunicação)',
)]
class RhEmailProcessQueueCommand extends Command
{
    public function __construct(
        private RhRecrutamentoEmailService $emails,
        private EmpresaRepository $empresaRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('empresa-id', null, InputOption::VALUE_REQUIRED, 'Processar apenas esta empresa')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Máximo de e-mails por execução', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));
        $empresaId = $input->getOption('empresa-id');
        $empresa = null;

        if ($empresaId !== null) {
            $empresa = $this->empresaRepo->find((int) $empresaId);
            if ($empresa === null) {
                $io->error('Empresa não encontrada.');

                return Command::FAILURE;
            }
        }

        try {
            $stats = $this->emails->processQueue($empresa, $limit);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Fila processada: %d e-mail(s) — %d enviado(s), %d erro(s).',
            $stats['processados'],
            $stats['enviados'],
            $stats['erros'],
        ));

        return Command::SUCCESS;
    }
}
