<?php

namespace App\Contract;

/**
 * Contrato do motor de IA jurídica.
 *
 * Implementado por JurisFlowAiClient (HTTP real) e NullLegalAiClient (testes/fallback).
 * Qualquer consumidor deve declarar esta interface em vez da classe concreta para
 * facilitar substituição e testes unitários sem rede.
 */
interface LegalAiClientInterface
{
    /** Retorna true se o serviço está acessível e respondendo ao health-check. */
    public function isAvailable(): bool;

    /**
     * Envia uma mensagem ao assistente jurídico (Sasha) e retorna a resposta.
     *
     * @param list<array{role: string, content: string}> $history
     * @param array<string, mixed>                        $context  Chaves úteis: escritorio_id, mode, numero_processo_atual
     *
     * @return array{reply: string, source: string, suggested_actions: list<string>}|null
     */
    public function chat(string $message, array $history = [], array $context = []): ?array;

    /**
     * Pesquisa jurisprudencial estruturada por tema, tribunal e período.
     *
     * @return array{
     *   resultados: list<array{tribunal: string, tema: string, resultado: ?string, relevancia: string, referencia: ?string, resumo: ?string}>,
     *   disclaimer: string
     * }|null
     */
    public function pesquisarJurisprudencia(
        string $tema,
        string $tribunal = 'Todos',
        string $periodo = '',
        string $areaJuridica = 'Geral',
        string $escritorioId = '',
    ): ?array;

    /** Resume um texto/peça processual. */
    public function resumirDocumento(string $texto, string $escritorioId = ''): ?string;

    /** Analisa riscos e cláusulas de um contrato. */
    public function analisarContrato(string $texto, string $escritorioId = ''): ?string;

    /** Gera uma minuta (petição, contrato, procuração, etc.). */
    public function gerarMinuta(string $tipo, string $descricao, string $escritorioId = ''): ?string;

    /** Analisa uma sentença identificando chances de recurso. */
    public function analisarSentenca(string $texto, string $escritorioId = ''): ?string;

    /** Compara dois documentos e retorna as diferenças relevantes. */
    public function compararDocumentos(string $textoA, string $textoB, string $escritorioId = ''): ?string;

    /**
     * Indexa um documento na base RAG do escritório (best-effort, nunca lança exceção).
     *
     * @return bool true se indexado com sucesso
     */
    public function indexarDocumentoRag(
        string $escritorioId,
        string $source,
        string $title,
        string $content,
        string $category = 'Peça do escritório',
    ): bool;

    /**
     * Busca trechos semelhantes na base RAG do escritório.
     *
     * @return list<array{document_id: string, document_title: string, category: string, content: string, score: float, source: string}>
     */
    public function buscarNaRag(string $escritorioId, string $query, int $limit = 8): array;

    /** Status resumido do stack (LLM, RAG, versão) para painéis de diagnóstico. */
    public function status(): ?array;

    /**
     * Submete um job assíncrono (ex: document.analyze, rag.reindex).
     *
     * @param array<string, mixed> $payload
     *
     * @return array{job_id?: string, status?: string, result?: array<string, mixed>}
     */
    public function submitJob(string $type, string $escritorioId, array $payload = []): array;

    /**
     * Extrai metadados estruturados de um texto processual (número CNJ, tipo de peça).
     *
     * @return array{text?: string, metadata?: array<string, mixed>}
     */
    public function extractMetadata(string $texto, string $escritorioId = ''): array;

    /**
     * Redige/anonimiza PII (CPF, CNPJ, OAB, e-mail, telefone) de um texto.
     * Em caso de falha, aplica regex local de CPF como fallback — nunca lança exceção.
     */
    public function redactPii(string $texto): string;
}
