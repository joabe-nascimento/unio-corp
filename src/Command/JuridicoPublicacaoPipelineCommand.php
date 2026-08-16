<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\EmpresaRepository;
use App\Service\Juridico\JuridicoPublicacaoPipelineService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:juridico:pipeline:reprocess',
    description: 'Reprocessa publicações DJEN pendentes (triagem → prazo → alerta)',
)]
final class JuridicoPublicacaoPipelineCommand extends Command
{
    public function __construct(
        private EmpresaRepository $empresaRepo,
        private JuridicoPublicacaoPipelineService $pipeline,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('empresa-id', null, InputOption::VALUE_OPTIONAL, 'Limitar a uma empresa')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Máximo por escritório', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = max(1, (int) $input->getOption('limit'));
        $empresaId = $input->getOption('empresa-id');

        $empresas = $empresaId !== null
            ? array_filter([$this->empresaRepo->find((int) $empresaId)])
            : $this->empresaRepo->findBy(['ativo' => true]);

        if ($empresas === []) {
            $io->error('Nenhuma empresa encontrada.');

            return Command::FAILURE;
        }

        $total = 0;
        foreach ($empresas as $empresa) {
            $n = $this->pipeline->reprocessarPendentes($empresa, $limit);
            if ($n > 0) {
                $io->writeln(sprintf('Empresa #%d: %d publicação(ões) reprocessada(s).', $empresa->getId(), $n));
            }
            $total += $n;
        }

        $io->success(sprintf('%d publicação(ões) reprocessada(s) no pipeline.', $total));

        return Command::SUCCESS;
    }
}
