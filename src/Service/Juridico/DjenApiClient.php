<?php

namespace App\Service\Juridico;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Cliente da API pública do DJEN / Comunica PJe (CNJ).
 *
 * @see https://comunicaapi.pje.jus.br/api/v1
 */
final class DjenApiClient
{
    private const BASE_URL = 'https://comunicaapi.pje.jus.br/api/v1';
    private const TIMEOUT = 20.0;
    private const ITENS_POR_PAGINA = 50;

    /** @var list<string> */
    private const OAB_SUFFIXES = ['', '-O', '-A', '-N', '-B', '-S', '-E'];

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Busca comunicações por OAB em uma janela de datas (inclusive).
     *
     * @return list<array<string, mixed>>
     */
    public function buscarPorOab(string $numeroOab, string $ufOab, \DateTimeImmutable $inicio, \DateTimeImmutable $fim): array
    {
        $numeroOab = preg_replace('/\D+/', '', $numeroOab) ?? '';
        $ufOab = strtoupper(trim($ufOab));

        if ($numeroOab === '' || strlen($ufOab) !== 2) {
            return [];
        }

        $items = [];
        $seen = [];

        foreach (self::OAB_SUFFIXES as $suffix) {
            $variantItems = $this->fetchAllPages([
                'numeroOab' => $numeroOab . $suffix,
                'ufOab' => $ufOab,
                'dataDisponibilizacaoInicio' => $inicio->format('Y-m-d'),
                'dataDisponibilizacaoFim' => $fim->format('Y-m-d'),
            ]);

            foreach ($variantItems as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, string> $baseQuery
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAllPages(array $baseQuery): array
    {
        $items = [];
        $pagina = 1;
        $total = null;

        while (true) {
            $query = array_merge($baseQuery, [
                'pagina' => (string) $pagina,
                'itensPorPagina' => (string) self::ITENS_POR_PAGINA,
            ]);

            $data = $this->request($query);
            if ($data === null) {
                break;
            }

            $count = (int) ($data['count'] ?? 0);
            $pageItems = \is_array($data['items'] ?? null) ? $data['items'] : [];

            if ($total === null) {
                $total = $count;
            }

            if ($pageItems === [] && $count > \count($items)) {
                // Página vazia transitória — uma retentativa antes de desistir.
                usleep(400_000);
                $retry = $this->request($query);
                $pageItems = \is_array($retry['items'] ?? null) ? $retry['items'] : [];
            }

            foreach ($pageItems as $item) {
                if (\is_array($item)) {
                    $items[] = $item;
                }
            }

            if (\count($pageItems) === 0 || \count($items) >= $count || $pagina >= 200) {
                break;
            }

            ++$pagina;
        }

        return $items;
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>|null
     */
    private function request(array $query): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . '/comunicacao', [
                'query' => $query,
                'timeout' => self::TIMEOUT,
                'headers' => ['Accept' => 'application/json'],
            ]);

            $status = $response->getStatusCode();
            if ($status === 403) {
                $this->logger->warning('DJEN API retornou 403 — verifique se o servidor está em IP brasileiro');

                return null;
            }

            if ($status >= 400) {
                $this->logger->warning('DJEN API erro HTTP {status}', ['status' => $status]);

                return null;
            }

            $data = $response->toArray(false);

            return \is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            $this->logger->warning('DJEN API indisponível: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Baixa a certidão oficial em PDF da comunicação DJEN.
     *
     * @return array{content: string, content_type: string}|null
     */
    public function baixarCertidao(string $hash): ?array
    {
        $hash = trim($hash);
        if ($hash === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                self::BASE_URL . '/comunicacao/' . rawurlencode($hash) . '/certidao',
                [
                    'timeout' => 30.0,
                    'headers' => ['Accept' => 'application/pdf,application/octet-stream,*/*'],
                ],
            );

            $status = $response->getStatusCode();
            if ($status >= 400) {
                $this->logger->warning('DJEN certidão HTTP {status} para hash {hash}', [
                    'status' => $status,
                    'hash' => $hash,
                ]);

                return null;
            }

            $content = $response->getContent(false);
            if ($content === '') {
                return null;
            }

            $headers = $response->getHeaders(false);
            $contentType = $headers['content-type'][0] ?? 'application/pdf';

            return [
                'content' => $content,
                'content_type' => $contentType,
            ];
        } catch (\Throwable $e) {
            $this->logger->warning('DJEN certidão indisponível: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    public static function sanitizarHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if (strlen($text) > 200_000) {
            $text = substr($text, 0, 200_000) . '… [texto truncado]';
        }

        return $text !== '' ? $text : null;
    }
}
