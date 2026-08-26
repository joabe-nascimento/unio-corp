<?php

namespace App\Service\Juridico;

use App\Contract\LegalAiClientInterface;

/**
 * Implementação nula do cliente de IA jurídica.
 *
 * Usada em testes unitários e como fallback quando o JurisFlow está desabilitado.
 * Nunca faz chamadas de rede — retorna null/false/[] para todos os métodos.
 */
final class NullLegalAiClient implements LegalAiClientInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function chat(string $message, array $history = [], array $context = []): ?array
    {
        return null;
    }

    public function pesquisarJurisprudencia(
        string $tema,
        string $tribunal = 'Todos',
        string $periodo = '',
        string $areaJuridica = 'Geral',
        string $escritorioId = '',
    ): ?array {
        return null;
    }

    public function resumirDocumento(string $texto, string $escritorioId = ''): ?string
    {
        return null;
    }

    public function analisarContrato(string $texto, string $escritorioId = ''): ?string
    {
        return null;
    }

    public function gerarMinuta(string $tipo, string $descricao, string $escritorioId = ''): ?string
    {
        return null;
    }

    public function analisarSentenca(string $texto, string $escritorioId = ''): ?string
    {
        return null;
    }

    public function compararDocumentos(string $textoA, string $textoB, string $escritorioId = ''): ?string
    {
        return null;
    }

    public function indexarDocumentoRag(
        string $escritorioId,
        string $source,
        string $title,
        string $content,
        string $category = 'Peça do escritório',
    ): bool {
        return false;
    }

    public function buscarNaRag(string $escritorioId, string $query, int $limit = 8): array
    {
        return [];
    }

    public function status(): ?array
    {
        return null;
    }

    public function submitJob(string $type, string $escritorioId, array $payload = []): array
    {
        return ['status' => 'disabled'];
    }

    public function extractMetadata(string $texto, string $escritorioId = ''): array
    {
        return [];
    }

    public function redactPii(string $texto): string
    {
        return preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '[CPF]', $texto) ?? $texto;
    }
}
