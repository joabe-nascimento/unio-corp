<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importa dados históricos de uso de tokens da Azure OpenAI para o sistema local.
 *
 * A Azure Monitoring API fornece métricas agregadas de uso (requests, tokens processados).
 * Este comando busca essas métricas e as consolida no formato esperado pelo JurisFlow AI.
 */
#[AsCommand(
    name: 'app:import-azure-token-usage',
    description: 'Importa dados históricos de uso de tokens da Azure OpenAI'
)]
class ImportAzureTokenUsageCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('azure-endpoint', null, InputOption::VALUE_REQUIRED, 'Endpoint da Azure OpenAI (ex: https://your-resource.openai.azure.com/)')
            ->addOption('azure-key', null, InputOption::VALUE_REQUIRED, 'Chave de API da Azure OpenAI')
            ->addOption('resource-id', null, InputOption::VALUE_REQUIRED, 'Resource ID do Azure OpenAI (ex: /subscriptions/{sub}/resourceGroups/{rg}/providers/Microsoft.CognitiveServices/accounts/{name})')
            ->addOption('management-token', null, InputOption::VALUE_REQUIRED, 'Token de acesso do Azure Resource Manager (az account get-access-token --query accessToken -o tsv)')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Número de dias históricos para importar', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Apenas exibe os dados sem atualizar o JurisFlow AI')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $azureEndpoint = $input->getOption('azure-endpoint');
        $azureKey = $input->getOption('azure-key');
        $resourceId = $input->getOption('resource-id');
        $managementToken = $input->getOption('management-token');
        $days = (int) ($input->getOption('days') ?? 30);
        $dryRun = $input->getOption('dry-run');

        if (empty($resourceId) || empty($managementToken)) {
            $io->error('São necessários --resource-id e --management-token para consultar métricas da Azure.');
            $io->note('Para obter o token: az login && az account get-access-token --query accessToken -o tsv');
            $io->note('Resource ID: /subscriptions/{subscription-id}/resourceGroups/{resource-group}/providers/Microsoft.CognitiveServices/accounts/{account-name}');

            return Command::FAILURE;
        }

        $io->title('Importação de dados históricos da Azure OpenAI');
        $io->text([
            sprintf('Resource ID: %s', $resourceId),
            sprintf('Período: últimos %d dias', $days),
            sprintf('Modo: %s', $dryRun ? 'DRY RUN (não vai salvar)' : 'PRODUÇÃO'),
        ]);

        $endTime = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startTime = $endTime->modify(sprintf('-%d days', $days));

        $io->section('1. Consultando métricas de tokens da Azure...');

        try {
            $metrics = $this->fetchAzureMetrics(
                $resourceId,
                $managementToken,
                $startTime,
                $endTime
            );

            $io->success(sprintf('Métricas obtidas: %d pontos de dados', count($metrics)));

            if (empty($metrics)) {
                $io->warning('Nenhuma métrica encontrada no período especificado.');

                return Command::SUCCESS;
            }

            $io->section('2. Agregando dados...');
            $aggregated = $this->aggregateMetrics($metrics, $startTime);

            $io->table(
                ['Período', 'Total Tokens', 'Requests'],
                [
                    ['Hoje', number_format($aggregated['today']['total_tokens']), number_format($aggregated['today']['requests'])],
                    ['Mês atual', number_format($aggregated['month']['total_tokens']), number_format($aggregated['month']['requests'])],
                    ['Total (lifetime)', number_format($aggregated['lifetime']['total_tokens']), number_format($aggregated['lifetime']['requests'])],
                ]
            );

            if ($dryRun) {
                $io->note('DRY RUN: Os dados acima NÃO foram salvos.');

                return Command::SUCCESS;
            }

            $io->section('3. Atualizando JurisFlow AI...');
            $this->updateJurisFlowUsage($aggregated, $io);

            $io->success('Importação concluída com sucesso!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Erro ao importar dados: %s', $e->getMessage()));

            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Consulta a Azure Monitoring API para obter métricas de uso.
     *
     * @return array<array{timestamp: string, value: float}>
     */
    private function fetchAzureMetrics(
        string $resourceId,
        string $token,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): array {
        $url = sprintf(
            'https://management.azure.com%s/providers/Microsoft.Insights/metrics',
            $resourceId
        );

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'query' => [
                'api-version' => '2023-10-01',
                'metricnames' => 'TokenTransaction',
                'aggregation' => 'total',
                'interval' => 'PT1H',
                'timespan' => sprintf(
                    '%s/%s',
                    $start->format('Y-m-d\TH:i:s\Z'),
                    $end->format('Y-m-d\TH:i:s\Z')
                ),
            ],
        ]);

        $data = $response->toArray();
        $metrics = [];

        foreach ($data['value'] ?? [] as $metric) {
            foreach ($metric['timeseries'] ?? [] as $series) {
                foreach ($series['data'] ?? [] as $point) {
                    if (isset($point['timeStamp'], $point['total']) && $point['total'] > 0) {
                        $metrics[] = [
                            'timestamp' => $point['timeStamp'],
                            'value' => (float) $point['total'],
                        ];
                    }
                }
            }
        }

        return $metrics;
    }

    /**
     * @param array<array{timestamp: string, value: float}> $metrics
     *
     * @return array{today: array, month: array, lifetime: array}
     */
    private function aggregateMetrics(array $metrics, \DateTimeImmutable $startTime): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $todayStart = $now->setTime(0, 0, 0);
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);

        $today = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'requests' => 0];
        $month = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'requests' => 0];
        $lifetime = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0, 'requests' => 0];

        foreach ($metrics as $point) {
            $timestamp = new \DateTimeImmutable($point['timestamp'], new \DateTimeZone('UTC'));
            $tokens = (int) $point['value'];

            // Estimar prompt:completion em 1:1 (Azure não separa por padrão)
            $promptTokens = (int) ($tokens * 0.5);
            $completionTokens = $tokens - $promptTokens;

            $lifetime['total_tokens'] += $tokens;
            $lifetime['prompt_tokens'] += $promptTokens;
            $lifetime['completion_tokens'] += $completionTokens;
            ++$lifetime['requests'];

            if ($timestamp >= $monthStart) {
                $month['total_tokens'] += $tokens;
                $month['prompt_tokens'] += $promptTokens;
                $month['completion_tokens'] += $completionTokens;
                ++$month['requests'];
            }

            if ($timestamp >= $todayStart) {
                $today['total_tokens'] += $tokens;
                $today['prompt_tokens'] += $promptTokens;
                $today['completion_tokens'] += $completionTokens;
                ++$today['requests'];
            }
        }

        return [
            'today' => $today,
            'month' => $month,
            'lifetime' => $lifetime,
        ];
    }

    /**
     * @param array{today: array, month: array, lifetime: array} $aggregated
     */
    private function updateJurisFlowUsage(array $aggregated, SymfonyStyle $io): void
    {
        // Atualizar via HTTP no endpoint do JurisFlow AI
        $jurisflowUrl = $_ENV['JURISFLOW_AI_BASE_URL'] ?? 'http://localhost:8090';

        try {
            $response = $this->httpClient->request('POST', $jurisflowUrl . '/v1/usage/import', [
                'json' => $aggregated,
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $io->text('✓ JurisFlow AI atualizado com sucesso');
            } else {
                $io->warning(sprintf('JurisFlow AI retornou status %d', $response->getStatusCode()));
            }
        } catch (\Exception $e) {
            $io->warning(sprintf('Não foi possível atualizar JurisFlow AI: %s', $e->getMessage()));
            $io->text('Os dados foram agregados mas não foram sincronizados com o serviço AI.');
        }
    }
}
