<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\JuridicoDocumentoRepository;
use App\Service\Juridico\JuridicoDocumentoRagSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Repopula a base de conhecimento (RAG) do JurisFlow com todos os documentos
 * já existentes na biblioteca dos escritórios — necessário rodar após todo
 * restart do processo Python, já que o store do RAG é em memória (TF-IDF,
 * sem persistência em disco no plano atual do HostGator).
 *
 * Uso:
 *   php bin/console app:juridico:rag:sync
 *   php bin/console app:juridico:rag:sync --empresa-id=5
 */
#[AsCommand(
    name: 'app:juridico:rag:sync',
    description: 'Reindexa os documentos da biblioteca jurídica no RAG do JurisFlow (necessário após restart do serviço Python)',
)]
final class JuridicoRagSyncCommand extends Command
{
    public function __construct(
        private JuridicoDocumentoRepository $documentoRepo,
        private JuridicoDocumentoRagSyncService $ragSync,
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
        $empresaIdFiltro = $input->getOption('empresa-id');

        $documentos = $this->documentoRepo->findAll();
        if ($empresaIdFiltro !== null) {
            $alvo = (int) $empresaIdFiltro;
            $documentos = array_values(array_filter(
                $documentos,
                static fn ($d) => $d->getEmpresa()->getId() === $alvo,
            ));
        }

        if ($documentos === []) {
            $io->warning('Nenhum documento encontrado para sincronizar.');

            return Command::SUCCESS;
        }

        $io->progressStart(\count($documentos));
        $sucesso = 0;
        $falha = 0;

        foreach ($documentos as $documento) {
            if ($this->ragSync->sync($documento)) {
                ++$sucesso;
            } else {
                ++$falha;
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success(sprintf(
            '%d documento(s) indexado(s) com sucesso, %d ignorado(s)/falhou(aram) (formato não suportado, texto vazio ou serviço indisponível).',
            $sucesso,
            $falha,
        ));

        return Command::SUCCESS;
    }
}
