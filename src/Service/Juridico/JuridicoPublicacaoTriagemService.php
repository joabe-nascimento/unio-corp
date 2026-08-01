<?php

namespace App\Service\Juridico;

use App\Entity\JuridicoPublicacao;
use Psr\Log\LoggerInterface;

/**
 * Triagem de publicações via Sasha/JurisFlow — classificação, resumo e sugestão de prazo.
 */
final class JuridicoPublicacaoTriagemService
{
    public function __construct(
        private JurisFlowAiClient $ai,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{classificacao: string, resumo: string, acao: string, prazo_dias: ?int, tipo_prazo: ?string}|null
     */
    public function triar(JuridicoPublicacao $publicacao): ?array
    {
        if (!$this->ai->isAvailable()) {
            return null;
        }

        $texto = trim((string) $publicacao->getTexto());
        if ($texto === '') {
            return null;
        }

        $prompt = $this->montarPrompt($publicacao, $texto);
        $result = $this->ai->chat($prompt, [], [
            'escritorio_id' => (string) $publicacao->getEmpresa()->getId(),
            'mode' => 'standard',
        ]);

        if ($result === null) {
            return null;
        }

        return $this->parseResposta($result['reply'] ?? '');
    }

    public function aplicarResultado(JuridicoPublicacao $publicacao, array $triagem): void
    {
        $publicacao
            ->setIaClassificacao($triagem['classificacao'] ?? null)
            ->setIaResumo($triagem['resumo'] ?? null)
            ->setIaSugestaoAcao($triagem['acao'] ?? null)
            ->setIaSugestaoPrazoDias(isset($triagem['prazo_dias']) ? (int) $triagem['prazo_dias'] : null)
            ->setIaSugestaoTipoPrazo($triagem['tipo_prazo'] ?? null)
            ->setStatus(JuridicoPublicacao::STATUS_TRIAGEM)
            ->setTriadaEm(new \DateTimeImmutable())
            ->touch();
    }

    private function montarPrompt(JuridicoPublicacao $publicacao, string $texto): string
    {
        $meta = array_filter([
            'Processo' => $publicacao->getNumeroProcesso(),
            'Tribunal' => $publicacao->getTribunal(),
            'Tipo comunicação' => $publicacao->getTipoComunicacao(),
            'Tipo documento' => $publicacao->getTipoDocumento(),
            'Órgão' => $publicacao->getOrgao(),
        ]);

        $metaLines = '';
        foreach ($meta as $k => $v) {
            $metaLines .= "- {$k}: {$v}\n";
        }

        $trecho = mb_substr($texto, 0, 6000);

        return <<<PROMPT
Você é a Sasha, assistente jurídica. Analise esta publicação do DJEN e responda APENAS com JSON válido (sem markdown), no formato:
{"classificacao":"intimacao|despacho|sentenca|decisao|outros","resumo":"...","acao":"próximo passo em linguagem clara","prazo_dias":15,"tipo_prazo":"contestação|recurso|manifestação|cumprimento|outros ou null"}

Metadados:
{$metaLines}

Texto da publicação:
{$trecho}
PROMPT;
    }

    /**
     * @return array{classificacao: string, resumo: string, acao: string, prazo_dias: ?int, tipo_prazo: ?string}|null
     */
    private function parseResposta(string $reply): ?array
    {
        $reply = trim($reply);
        if ($reply === '') {
            return null;
        }

        if (preg_match('/\{[\s\S]*\}/', $reply, $m)) {
            $reply = $m[0];
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($reply, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->info('Triagem publicação: JSON inválido da IA — usando fallback textual');

            return [
                'classificacao' => 'outros',
                'resumo' => mb_substr($reply, 0, 500),
                'acao' => 'Revisar publicação manualmente.',
                'prazo_dias' => null,
                'tipo_prazo' => null,
            ];
        }

        return [
            'classificacao' => (string) ($data['classificacao'] ?? 'outros'),
            'resumo' => (string) ($data['resumo'] ?? ''),
            'acao' => (string) ($data['acao'] ?? ''),
            'prazo_dias' => isset($data['prazo_dias']) && is_numeric($data['prazo_dias']) ? (int) $data['prazo_dias'] : null,
            'tipo_prazo' => isset($data['tipo_prazo']) && $data['tipo_prazo'] !== null ? (string) $data['tipo_prazo'] : null,
        ];
    }
}
