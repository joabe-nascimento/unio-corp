<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiKbArtigo;
use App\Repository\TiKbArtigoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TiKbService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiKbArtigoRepository $repository,
    ) {}

    public function ensureInitialized(Empresa $empresa): void
    {
        if ($this->repository->countByEmpresa($empresa) > 0) {
            return;
        }

        foreach (TiReferenceData::knowledgeBase() as $item) {
            $kb = new TiKbArtigo();
            $kb->setEmpresa($empresa)
                ->setCodigo((string) $item['id'])
                ->setTitulo((string) $item['title'])
                ->setResumo((string) $item['title'])
                ->setConteudo('Artigo de referência para triagem Helia. Palavras-chave: ' . implode(', ', $item['keywords'] ?? []))
                ->setCategoria('geral')
                ->setTags($item['keywords'] ?? []);
            $this->em->persist($kb);
        }
        $this->em->flush();
    }

    /** @return list<array<string, mixed>> */
    public function list(Empresa $empresa): array
    {
        $this->ensureInitialized($empresa);

        return array_map(static fn (TiKbArtigo $k) => $k->toArray(), $this->repository->findByEmpresa($empresa));
    }

    /** @return list<array<string, mixed>> */
    public function search(Empresa $empresa, string $query, int $limit = 10): array
    {
        $this->ensureInitialized($empresa);
        if (trim($query) === '') {
            return $this->list($empresa);
        }

        return array_map(
            static fn (TiKbArtigo $k) => $k->toArray(),
            $this->repository->search($empresa, $query, $limit),
        );
    }

    /** @return list<array<string, mixed>> */
    public function matchForText(Empresa $empresa, string $text, int $limit = 5): array
    {
        $this->ensureInitialized($empresa);
        $text = mb_strtolower($text);
        $scored = [];

        foreach ($this->repository->findByEmpresa($empresa) as $article) {
            $score = 0;
            foreach ($article->getTags() as $tag) {
                if ($tag !== '' && str_contains($text, mb_strtolower($tag))) {
                    $score += 20;
                }
            }
            if (str_contains($text, mb_strtolower($article->getTitulo()))) {
                $score += 15;
            }
            if ($score > 0) {
                $row = $article->toArray();
                $row['match'] = min(98, 60 + $score);
                $scored[] = $row;
            }
        }

        usort($scored, static fn ($a, $b) => ($b['match'] ?? 0) <=> ($a['match'] ?? 0));

        return \array_slice($scored, 0, $limit);
    }

    public function load(Empresa $empresa, int $id): TiKbArtigo
    {
        $item = $this->repository->find($id);
        if (!$item || $item->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Artigo não encontrado.');
        }

        return $item;
    }

    public function create(Empresa $empresa, array $data): TiKbArtigo
    {
        $num = $this->repository->nextCodigoNumber($empresa);
        $kb = new TiKbArtigo();
        $kb->setEmpresa($empresa)
            ->setCodigo('KB-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT))
            ->setTitulo($this->requireString($data, 'titulo'))
            ->setResumo($this->requireString($data, 'resumo'))
            ->setConteudo(trim((string) ($data['conteudo'] ?? '')) ?: null)
            ->setCategoria(trim((string) ($data['categoria'] ?? 'geral')) ?: 'geral')
            ->setTags($this->parseTags($data['tags'] ?? ''));
        $this->em->persist($kb);
        $this->em->flush();

        return $kb;
    }

    public function update(TiKbArtigo $kb, array $data): void
    {
        $kb->setTitulo($this->requireString($data, 'titulo'))
            ->setResumo($this->requireString($data, 'resumo'))
            ->setConteudo(trim((string) ($data['conteudo'] ?? '')) ?: null)
            ->setCategoria(trim((string) ($data['categoria'] ?? $kb->getCategoria())) ?: 'geral')
            ->setTags($this->parseTags($data['tags'] ?? implode(', ', $kb->getTags())))
            ->touch();
        $this->em->flush();
    }

    public function delete(TiKbArtigo $kb): void
    {
        $this->em->remove($kb);
        $this->em->flush();
    }

    /** @return list<string> */
    private function parseTags(mixed $raw): array
    {
        if (\is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }

    private function requireString(array $data, string $key): string
    {
        $v = trim((string) ($data[$key] ?? ''));
        if ($v === '') {
            throw new \InvalidArgumentException('Campo obrigatório: ' . $key);
        }

        return $v;
    }
}
