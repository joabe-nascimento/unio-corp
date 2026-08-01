<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\EmpresaRepository;
use App\Service\Juridico\JuridicoPublicacaoCapturaService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:juridico:capturar-publicacoes',
    description: 'Captura publicações do DJEN por OAB e executa triagem IA (cron diário)',
)]
final class JuridicoCapturarPublicacoesCommand extends Command
{
    public function __construct(
        private JuridicoPublicacaoCapturaService $captura,
        private EmpresaRepository $empresaRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('empresa-id', null, InputOption::VALUE_OPTIONAL, 'Limitar a uma empresa')
            ->addOption('dias', null, InputOption::VALUE_OPTIONAL, 'Janela de dias para consulta', '2')
            ->addOption('sem-triagem', null, InputOption::VALUE_NONE, 'Não triar novas publicações com IA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dias = max(1, (int) $input->getOption('dias'));
        $triar = !$input->getOption('sem-triagem');
        $empresaId = $input->getOption('empresa-id');

        if ($empresaId !== null) {
            $empresa = $this->empresaRepo->find((int) $empresaId);
            if ($empresa === null) {
                $io->error('Empresa não encontrada.');

                return Command::FAILURE;
            }
            $stats = $this->captura->capturarEmpresa($empresa, $dias, $triar);
        } else {
            $stats = $this->captura->capturarTodas($dias, $triar);
        }

        $io->success(sprintf(
            'Captura DJEN — %d nova(s), %d atualizada(s), %d triada(s), %d prazo(s) automático(s), %d erro(s).',
            $stats['novas'],
            $stats['atualizadas'],
            $stats['triadas'],
            $stats['prazos'] ?? 0,
            $stats['erros'],
        ));

        return Command::SUCCESS;
    }
}
