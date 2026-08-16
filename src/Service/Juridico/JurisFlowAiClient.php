<?php

namespace App\Service\Juridico;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente HTTP para o motor de IA jurídica (JurisFlow AI Service — LangChain + RAG + Agents).
 *
 * O serviço é multi-vertical (app/verticals/legal) e multi-tenant via `escritorio_id`.
 * Mantém a mesma "forma" de resposta do SashaClient para que o mesmo chat (Lumen/Vitória)
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
            $this->nudgeWatchdog();

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
        $mode = strtolower(trim((string) ($context['mode'] ?? 'standard')));
        if (!\in_array($mode, ['standard', 'superior', 'lex', 'premium'], true)) {
            $mode = 'standard';
        }
        if (\in_array($mode, ['lex', 'premium'], true)) {
            $mode = 'superior';
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/assistant/Sasha/chat', [
                'json' => [
                    'message' => $message,
                    'escritorio_id' => $escritorioId,
                    'use_rag' => true,
                    'mode' => $mode,
                    'history' => array_map(static fn (array $m) => [
                        'role' => $m['role'],
                        'content' => $m['content'],
                    ], $history),
                    'time_context' => [
                        'date' => $this->formatDatePtBr($now),
                        'time' => $now->format('H:i'),
                        'period' => $this->periodOfDay((int) $now->format('G')),
                    ],
                    'numero_processo_atual' => $context['numero_processo_atual'] ?? null,
                ],
                'timeout' => $mode === 'superior' ? 90 : 45,
            ]);

            $data = $response->toArray(false);
            $reply = trim((string) ($data['answer'] ?? ''));

            // Resposta vazia ou fallback antigo do JurisFlow = falha real (não mascarar).
            if ($reply === '' || str_contains(mb_strtolower($reply), 'instabilidade no provedor de ia')) {
                $this->logger->warning('IA jurídica (JurisFlow) retornou resposta inválida/vazia');

                return null;
            }

            return [
                'reply' => $reply,
                'source' => 'jurisflow',
                'suggested_actions' => [],
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('IA jurídica (JurisFlow) indisponível: {msg}', ['msg' => $e->getMessage()]);
            if ($this->isConnectionError($e)) {
                $this->nudgeWatchdog();
                usleep(1_800_000);

                try {
                    $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/assistant/Sasha/chat', [
                        'json' => [
                            'message' => $message,
                            'escritorio_id' => $escritorioId,
                            'use_rag' => true,
                            'mode' => $mode,
                            'history' => array_map(static fn (array $m) => [
                                'role' => $m['role'],
                                'content' => $m['content'],
                            ], $history),
                            'time_context' => [
                                'date' => $this->formatDatePtBr($now),
                                'time' => $now->format('H:i'),
                                'period' => $this->periodOfDay((int) $now->format('G')),
                            ],
                            'numero_processo_atual' => $context['numero_processo_atual'] ?? null,
                        ],
                        'timeout' => $mode === 'superior' ? 90 : 45,
                    ]);
                    $data = $response->toArray(false);
                    $reply = trim((string) ($data['answer'] ?? ''));
                    if ($reply !== '' && !str_contains(mb_strtolower($reply), 'instabilidade no provedor de ia')) {
                        return [
                            'reply' => $reply,
                            'source' => 'jurisflow',
                            'suggested_actions' => [],
                        ];
                    }
                } catch (\Throwable) {
                    return null;
                }
            }

            return null;
        }
    }

    /**
     * Pesquisa jurisprudencial estruturada (motor de IA "Jurisprudência IA").
     *
     * Retorna uma lista de julgados/teses (tribunal, resumo, referência, relevância)
     * prontos para virar registros na biblioteca de jurisprudência do escritório.
     *
     * @return array{resultados: list<array{tribunal: string, tema: string, resultado: ?string, relevancia: string, referencia: ?string, resumo: ?string}>, disclaimer: string}|null
     */
    public function pesquisarJurisprudencia(
        string $tema,
        string $tribunal = 'Todos',
        string $periodo = '',
        string $areaJuridica = 'Geral',
        string $escritorioId = '',
    ): ?array {
        if (!$this->enabled) {
            return null;
        }

        $escritorioId = $escritorioId !== '' ? $escritorioId : $this->defaultEscritorioId;
        if ($escritorioId === '') {
            $escritorioId = 'default';
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/chains/jurisprudence-search', [
                'json' => [
                    'tema' => $tema,
                    'tribunal' => $tribunal ?: 'Todos',
                    'periodo' => $periodo,
                    'area_juridica' => $areaJuridica ?: 'Geral',
                    'escritorio_id' => $escritorioId,
                ],
                'timeout' => 75,
            ]);

            $data = $response->toArray(false);

            return [
                'resultados' => \is_array($data['resultados'] ?? null) ? $data['resultados'] : [],
                'disclaimer' => (string) ($data['disclaimer'] ?? ''),
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('Pesquisa de jurisprudência (JurisFlow) indisponível: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /** Resume um texto/peça processual usando a chain `summarize` do JurisFlow. */
    public function resumirDocumento(string $texto, string $escritorioId = ''): ?string
    {
        return $this->callTextChain('/v1/chains/summarize', ['text' => $texto], 'summary', $escritorioId);
    }

    /** Analisa riscos/cláusulas de um contrato usando a chain `contract-analysis`. */
    public function analisarContrato(string $texto, string $escritorioId = ''): ?string
    {
        return $this->callTextChain('/v1/chains/contract-analysis', ['contract_text' => $texto], 'analysis', $escritorioId);
    }

    /** Gera uma minuta (petição, contrato, procuração, etc.) usando a chain `document-generation`. */
    public function gerarMinuta(string $tipo, string $descricao, string $escritorioId = ''): ?string
    {
        return $this->callTextChain(
            '/v1/chains/document-generation',
            ['document_type' => $tipo, 'data' => $descricao],
            'document',
            $escritorioId,
        );
    }

    /** Analisa uma sentença identificando chances de recurso usando a chain `sentence-analysis`. */
    public function analisarSentenca(string $texto, string $escritorioId = ''): ?string
    {
        return $this->callTextChain('/v1/chains/sentence-analysis', ['sentence_text' => $texto], 'analysis', $escritorioId);
    }

    /** Compara dois documentos usando a chain `document-comparison`. */
    public function compararDocumentos(string $textoA, string $textoB, string $escritorioId = ''): ?string
    {
        return $this->callTextChain(
            '/v1/chains/document-comparison',
            ['document_a' => $textoA, 'document_b' => $textoB],
            'comparison',
            $escritorioId,
        );
    }

    /**
     * Helper comum às chains de texto (resumo, análise de contrato, geração de
     * documento): mesmo contrato de payload (`escritorio_id` + campos próprios),
     * mesma resposta (um campo de texto), mesmo tratamento de erro.
     *
     * @param array<string, mixed> $extraPayload
     */
    private function callTextChain(string $path, array $extraPayload, string $responseField, string $escritorioId): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $escritorioId = $escritorioId !== '' ? $escritorioId : $this->defaultEscritorioId;
        if ($escritorioId === '') {
            $escritorioId = 'default';
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . $path, [
                'json' => array_merge($extraPayload, ['escritorio_id' => $escritorioId]),
                'timeout' => 75,
            ]);

            $data = $response->toArray(false);

            return (string) ($data[$responseField] ?? '');
        } catch (\Throwable $e) {
            $this->logger->warning('Chain do JurisFlow ({path}) indisponível: {msg}', ['path' => $path, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Indexa um documento na base de conhecimento (RAG) do JurisFlow, escopada por
     * escritório. Usado para "sugerir peças similares" — reaproveita o RAG genérico
     * já existente do serviço, sem pipeline de embeddings próprio.
     *
     * Best-effort: nunca lança exceção, apenas loga e retorna false em caso de falha
     * (o RAG é um "nice to have" em memória, não pode travar o fluxo principal).
     */
    public function indexarDocumentoRag(string $escritorioId, string $source, string $title, string $content, string $category = 'Peça do escritório'): bool
    {
        if (!$this->enabled || trim($content) === '') {
            return false;
        }

        try {
            $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/rag/' . rawurlencode($escritorioId) . '/documents', [
                'json' => [
                    'title' => $title,
                    'content' => $content,
                    'category' => $category,
                    'source' => $source,
                ],
                'timeout' => 8,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->info('Falha ao indexar documento no RAG do JurisFlow (não crítico): {msg}', ['msg' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Busca trechos semelhantes na base de conhecimento (RAG) de um escritório.
     *
     * @return list<array{document_id: string, document_title: string, category: string, content: string, score: float, source: string}>
     */
    public function buscarNaRag(string $escritorioId, string $query, int $limit = 8): array
    {
        if (!$this->enabled || trim($query) === '') {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/v1/rag/' . rawurlencode($escritorioId) . '/search', [
                'json' => ['query' => $query, 'limit' => $limit],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);

            return \is_array($data['chunks'] ?? null) ? $data['chunks'] : [];
        } catch (\Throwable $e) {
            $this->logger->info('Falha ao buscar no RAG do JurisFlow (não crítico): {msg}', ['msg' => $e->getMessage()]);

            return [];
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

    private function isConnectionError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'connection refused')
            || str_contains($msg, 'failed to connect')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'connection reset');
    }

    /** Religa o JurisFlow na HostGator sem bloquear o request (nohup). */
    private function nudgeWatchdog(): void
    {
        $script = '/home2/joabef36/jurisflow-ai/scripts/watchdog-hostgator.sh';
        $lock = '/home2/joabef36/jurisflow-ai/.php-nudge.lock';
        if (!is_file($script)) {
            return;
        }
        if (is_file($lock) && (time() - (int) filemtime($lock)) < 40) {
            return;
        }
        @touch($lock);
        @exec('nohup bash ' . escapeshellarg($script) . ' >/dev/null 2>&1 &');
    }

    private function periodOfDay(int $hour): string
    {
        return match (true) {
            $hour < 6 => 'madrugada',
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
