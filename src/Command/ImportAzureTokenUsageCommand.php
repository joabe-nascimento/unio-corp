<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Juridico\AzureMonitorTokenImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-azure-token-usage',
    description: 'Sincroniza uso de tokens da Azure OpenAI via Azure Monitor API',
)]
class ImportAzureTokenUsageCommand extends Command
{
    public function __construct(
        private readonly AzureMonitorTokenImporter $importer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->importer->isConfigured()) {
            $io->error('Azure Monitor não configurado.');
            $io->listing([
                'AZURE_TENANT_ID',
                'AZURE_CLIENT_ID',
                'AZURE_CLIENT_SECRET',
                'AZURE_OPENAI_RESOURCE_ID',
            ]);

            return Command::FAILURE;
        }

        $io->title('Sincronização Azure OpenAI → Unio Jurídico');

        try {
            $result = $this->importer->sync();

            $io->success('Sincronização concluída em ' . $result['synced_at']);
            $io->table(
                ['Período', 'Total Tokens', 'Requests'],
                [
                    ['Últimas 24h', number_format($result['today']['total_tokens']), number_format($result['today']['requests'])],
                    ['Mês atual', number_format($result['month']['total_tokens']), number_format($result['month']['requests'])],
                    ['Últimos 90 dias', number_format($result['lifetime']['total_tokens']), number_format($result['lifetime']['requests'])],
                ],
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
