<?php

namespace App\Service\Sasha;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente HTTP para o serviço Python Sasha AI (services/Sasha-ai).
 */
final class SashaClient
{
    private const SLA_MINUTES = ['P1' => 15, 'P2' => 60, 'P3' => 240, 'P4' => 1440];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private bool $enabled,
        private string $baseUrl,
        private string $apiKey,
    ) {}

    public function isAvailable(): bool
    {
        if (!$this->enabled || $this->baseUrl === '') {
            return false;
        }

        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/health', [
                'timeout' => 3,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param list<array{role: string, content: string}> $history
     * @param array<string, mixed> $context
     *
     * @return array{reply: string, source: string, suggested_actions: list<string>}|null
     */
    public function chat(string $message, array $history = [], array $context = []): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/chat', [
                'headers' => $this->headers(),
                'json' => [
                    'message' => $message,
                    'history' => array_map(static fn (array $m) => [
                        'role' => $m['role'],
                        'content' => $m['content'],
                    ], $history),
                    'context' => $context,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray(false);

            return [
                'reply' => (string) ($data['reply'] ?? ''),
                'source' => (string) ($data['source'] ?? 'unknown'),
                'suggested_actions' => $data['suggested_actions'] ?? [],
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('Sasha AI indisponível: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $respostas
     * @param array<string, mixed> $regrasProtocolo
     *
     * @return array{prioridade: string, score_risco: int, motivo: string, acoes_sugeridas: list<string>, requer_contato_imediato: bool}|null
     */
    public function evaluateTriage(
        array $respostas,
        ?string $pacienteCodigo = null,
        ?string $procedimento = null,
        ?int $diaPosOperatorio = null,
        array $regrasProtocolo = [],
    ): ?array {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/triage/evaluate', [
                'headers' => $this->headers(),
                'json' => [
                    'paciente_codigo' => $pacienteCodigo,
                    'procedimento' => $procedimento,
                    'dia_pos_operatorio' => $diaPosOperatorio,
                    'respostas' => $respostas,
                    'regras_protocolo' => $regrasProtocolo !== [] ? $regrasProtocolo : new \stdClass(),
                ],
                'timeout' => 15,
            ]);

            return $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->warning('Triagem Sasha indisponível: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $kpis
     * @param list<array<string, mixed>> $alertasAbertos
     * @param list<array<string, mixed>> $pacientesPendentes
     *
     * @return array{text: string, bullets: list<string>, action: string}|null
     */
    public function hubInsight(
        string $hub,
        array $kpis = [],
        array $alertasAbertos = [],
        array $pacientesPendentes = [],
    ): ?array {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/insights/hub', [
                'headers' => $this->headers(),
                'json' => [
                    'hub' => $hub,
                    'kpis' => $kpis,
                    'alertas_abertos' => $alertasAbertos,
                    'pacientes_pendentes' => $pacientesPendentes,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);

            return [
                'text' => (string) ($data['text'] ?? ''),
                'bullets' => $data['bullets'] ?? [],
                'action' => (string) ($data['action'] ?? 'Ver plano sugerido'),
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('Insight Sasha indisponível: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    public static function slaMinutesForPriority(string $prioridade): int
    {
        return self::SLA_MINUTES[$prioridade] ?? 1440;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-Api-Key' => $this->apiKey,
        ];
    }
}
