<?php

namespace App\Service\Juridico;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente HTTP para o motor de IA jurídica (JurisFlow AI Service — LangChain + RAG + Agents).
 *
 * O serviço é multi-vertical (app/verticals/legal) e multi-tenant via `escritorio_id`.
 * Mantém a mesma "forma" de resposta do VitoriaClient para que o mesmo chat (Lumen/Vitória)
 * do shell Organismo funcione sem alterações no front-end, apenas trocando o backend
 * quando a identidade ativa é a Unio Jurídico.
 */
final class JurisFlowAiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private bool $enabled,
        private string $baseUrl,
        private string $defaultEscritorioId,
    ) {
    }

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
     * @param array<string, mixed>                        $context
     *
     * @return array{reply: string, source: string, suggested_actions: list<string>}|null
     */
    public function chat(string $message, array $history = [], array $context = []): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $escritorioId = (string) ($context['escritorio_id'] ?? $this->defaultEscritorioId);
        if ($escritorioId === '') {
            $escritorioId = 'default';
        }

        $now = new \DateTimeImmutable('now');

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/assistant/bruna/chat', [
                'json' => [
                    'message' => $message,
                    'escritorio_id' => $escritorioId,
                    'use_rag' => true,
                    'history' => array_map(static fn (array $m) => [
                        'role' => $m['role'],
                        'content' => $m['content'],
                    ], $history),
                    'time_context' => [
                        'date' => $this->formatDatePtBr($now),
                        'time' => $now->format('H:i'),
                        'period' => $this->periodOfDay((int) $now->format('G')),
                    ],
                ],
                'timeout' => 45,
            ]);

            $data = $response->toArray(false);

            return [
                'reply' => (string) ($data['answer'] ?? ''),
                'source' => 'jurisflow',
                'suggested_actions' => [],
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('IA jurídica (JurisFlow) indisponível: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /** Status resumido do stack (vertical, LLM, RAG) — usado em painéis de diagnóstico. */
    public function status(): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/v1/status', [
                'timeout' => 5,
            ]);

            return $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->warning('Status IA jurídica indisponível: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    private function periodOfDay(int $hour): string
    {
        return match (true) {
            $hour < 12 => 'manhã',
            $hour < 18 => 'tarde',
            default => 'noite',
        };
    }

    private function formatDatePtBr(\DateTimeImmutable $date): string
    {
        $dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
            7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];

        return sprintf(
            '%s, %d de %s de %d',
            $dias[(int) $date->format('w')],
            (int) $date->format('j'),
            $meses[(int) $date->format('n')],
            (int) $date->format('Y'),
        );
    }
}
