<?php

namespace App\Service\Juridico\DataJud;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente da API Pública do DataJud (CNJ) — base nacional de processos que agrega
 * metadados oficiais vindos de PJe, e-SAJ, Projudi, EPROC e demais sistemas dos
 * tribunais brasileiros. Acesso gratuito mediante cadastro em datajud-wiki.cnj.jus.br.
 *
 * @see https://datajud-wiki.cnj.jus.br/
 */
final class DataJudClient
{
    private const BASE_URL = 'https://api-publica.datajud.cnj.jus.br';
    private const TIMEOUT = 12.0;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Consulta os metadados e movimentações oficiais de um processo pelo número CNJ.
     *
     * @return array{
     *     numero: string,
     *     tribunal: string,
     *     classe: ?string,
     *     orgaoJulgador: ?string,
     *     dataAjuizamento: ?string,
     *     ultimaAtualizacao: ?string,
     *     movimentos: list<array{data: ?string, nome: string, complemento: ?string}>
     * }
     *
     * @throws DataJudException quando o número não é reconhecido, a chave é inválida
     *                          ou o tribunal está indisponível
     */
    public function consultarProcesso(string $numeroProcesso, string $apiKey): array
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new DataJudException('Chave de API do DataJud não configurada para este escritório.');
        }

        $resolvido = DataJudTribunalMap::resolver($numeroProcesso);
        if ($resolvido === null) {
            throw new DataJudException('Número de processo fora do padrão CNJ (NNNNNNN-DD.AAAA.J.TR.OOOO) — não é possível identificar o tribunal.');
        }

        $url = sprintf('%s/api_publica_%s/_search', self::BASE_URL, $resolvido['alias']);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Authorization' => 'APIKey ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'size' => 1,
                    'query' => ['match' => ['numeroProcesso' => preg_replace('/\D/', '', $resolvido['numero'])]],
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status === 401 || $status === 403) {
                throw new DataJudException('Chave de API do DataJud inválida ou sem permissão. Verifique o cadastro em datajud-wiki.cnj.jus.br.');
            }
            if ($status === 429) {
                throw new DataJudException('Limite de consultas do DataJud atingido — tente novamente em alguns minutos.');
            }
            if ($status >= 400) {
                throw new DataJudException(sprintf('DataJud retornou erro %d para o tribunal %s.', $status, $resolvido['tribunal']));
            }

            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new DataJudException('Não foi possível conectar à API pública do DataJud agora: ' . $e->getMessage(), 0, $e);
        }

        $hits = $data['hits']['hits'] ?? [];
        if ($hits === []) {
            throw new DataJudException(sprintf(
                'Processo não encontrado na base do DataJud para o %s. O DataJud tem defasagem de atualização de até 30 dias em relação ao tribunal.',
                $resolvido['tribunal'],
            ));
        }

        $fonte = $hits[0]['_source'] ?? [];
        $movimentosRaw = \is_array($fonte['movimentos'] ?? null) ? $fonte['movimentos'] : [];

        usort($movimentosRaw, static function (array $a, array $b): int {
            return strcmp((string) ($b['dataHora'] ?? ''), (string) ($a['dataHora'] ?? ''));
        });

        $movimentos = [];
        foreach (\array_slice($movimentosRaw, 0, 25) as $mov) {
            $movimentos[] = [
                'data' => isset($mov['dataHora']) ? substr((string) $mov['dataHora'], 0, 10) : null,
                'nome' => (string) ($mov['nome'] ?? 'Movimentação'),
                'complemento' => isset($mov['complementosTabelados'][0]['nome'])
                    ? (string) $mov['complementosTabelados'][0]['nome']
                    : null,
            ];
        }

        return [
            'numero' => $resolvido['numero'],
            'tribunal' => $resolvido['tribunal'],
            'classe' => $fonte['classe']['nome'] ?? null,
            'orgaoJulgador' => $fonte['orgaoJulgador']['nome'] ?? null,
            'dataAjuizamento' => isset($fonte['dataAjuizamento']) ? substr((string) $fonte['dataAjuizamento'], 0, 10) : null,
            'ultimaAtualizacao' => isset($fonte['@timestamp']) ? substr((string) $fonte['@timestamp'], 0, 10) : null,
            'movimentos' => $movimentos,
        ];
    }

    /**
     * Testa a chave de API fazendo uma consulta mínima ao TJSP (tribunal de maior volume).
     *
     * @return array{ok: bool, mensagem: string}
     */
    public function testarChave(string $apiKey): array
    {
        try {
            $response = $this->httpClient->request('POST', self::BASE_URL . '/api_publica_tjsp/_search', [
                'timeout' => self::TIMEOUT,
                'headers' => [
                    'Authorization' => 'APIKey ' . trim($apiKey),
                    'Content-Type' => 'application/json',
                ],
                'json' => ['size' => 0, 'query' => ['match_all' => new \stdClass()]],
            ]);

            $status = $response->getStatusCode();
            if ($status === 401 || $status === 403) {
                return ['ok' => false, 'mensagem' => 'Chave rejeitada pelo DataJud (401/403). Confira se copiou a chave completa.'];
            }
            if ($status >= 400) {
                return ['ok' => false, 'mensagem' => sprintf('DataJud retornou status %d ao testar a chave.', $status)];
            }

            return ['ok' => true, 'mensagem' => 'Chave validada com sucesso — conectado à base nacional do DataJud.'];
        } catch (TransportExceptionInterface $e) {
            return ['ok' => false, 'mensagem' => 'Falha de conexão com o DataJud: ' . $e->getMessage()];
        }
    }
}
