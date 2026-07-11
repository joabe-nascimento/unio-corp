<?php

namespace App\Service\Marketing;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Notícias do momento por módulo — feed RSS (Google News, pt-BR).
 */
final class StudioModuleNewsService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
    ) {
    }

    /** @return list<array<string, string>> */
    public function headlines(string $moduleId, int $limit = 6): array
    {
        $cacheKey = 'studio_module_news_' . preg_replace('/[^a-z0-9_-]/', '', $moduleId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($moduleId, $limit): array {
            $item->expiresAfter(900);

            return $this->fetchHeadlines($moduleId, $limit);
        });
    }

    /** @return list<array<string, string>> */
    private function fetchHeadlines(string $moduleId, int $limit): array
    {
        $query = $this->queryForModule($moduleId);
        $url = 'https://news.google.com/rss/search?q=' . rawurlencode($query)
            . '&hl=pt-BR&gl=BR&ceid=BR:pt-419';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 8,
                'max_redirects' => 3,
                'headers' => [
                    'User-Agent' => 'UnioMarketingBot/1.0 (+https://uniowork.com.br)',
                    'Accept' => 'application/rss+xml, application/xml, text/xml',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $xml = @simplexml_load_string($response->getContent());
            if ($xml === false || !isset($xml->channel->item)) {
                return [];
            }

            $items = [];
            foreach ($xml->channel->item as $node) {
                if (\count($items) >= $limit) {
                    break;
                }

                $title = trim((string) ($node->title ?? ''));
                $link = trim((string) ($node->link ?? ''));
                $pubDate = trim((string) ($node->pubDate ?? ''));
                if ($title === '' || $link === '') {
                    continue;
                }

                $source = $this->extractSource($title);
                $items[] = [
                    'type' => 'news',
                    'icon' => 'fa-newspaper',
                    'text' => $this->cleanTitle($title),
                    'url' => $link,
                    'source' => $source,
                    'ago' => $this->formatAgo($pubDate),
                ];
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    private function queryForModule(string $moduleId): string
    {
        return match ($moduleId) {
            'financeiro' => 'economia finanças empresas',
            'rh' => 'recursos humanos trabalho emprego',
            'engenharia' => 'engenharia projetos infraestrutura',
            'ti' => 'tecnologia inovação TI',
            'pos-operatorio' => 'saúde hospitalar medicina',
            'operacoes' => 'gestão empresarial operações',
            default => 'negócios Brasil',
        };
    }

    private function cleanTitle(string $title): string
    {
        $parts = explode(' - ', $title);
        if (\count($parts) > 1) {
            array_pop($parts);
            $title = implode(' - ', $parts);
        }

        return trim($title);
    }

    private function extractSource(string $title): string
    {
        $parts = explode(' - ', $title);
        if (\count($parts) > 1) {
            return trim((string) end($parts));
        }

        return 'Google News';
    }

    private function formatAgo(string $pubDate): string
    {
        if ($pubDate === '') {
            return 'agora';
        }

        try {
            $dt = new \DateTimeImmutable($pubDate);
            $diff = (new \DateTimeImmutable())->getTimestamp() - $dt->getTimestamp();
            if ($diff < 3600) {
                return 'há ' . max(1, (int) floor($diff / 60)) . ' min';
            }
            if ($diff < 86400) {
                return 'há ' . max(1, (int) floor($diff / 3600)) . ' h';
            }

            return 'há ' . max(1, (int) floor($diff / 86400)) . ' dias';
        } catch (\Exception) {
            return 'hoje';
        }
    }
}
