<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiProblema;
use App\Repository\TiChamadoRepository;
use App\Repository\TiProblemaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TiProblemaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiProblemaRepository $repository,
        private TiChamadoRepository $chamadoRepository,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(Empresa $empresa): array
    {
        $items = [];
        foreach ($this->repository->findByEmpresa($empresa) as $problema) {
            $items[] = $problema->toArray($this->countTickets($problema));
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    public function detectRecurring(Empresa $empresa, int $minCount = 2): array
    {
        $chamados = $this->chamadoRepository->findByEmpresa($empresa);
        $groups = [];
        foreach ($chamados as $chamado) {
            if ($chamado->getStatus() === \App\Entity\TiChamado::STATUS_RESOLVIDO) {
                continue;
            }
            $key = $chamado->getCategoria() . '|' . mb_strtolower(mb_substr($chamado->getTitulo(), 0, 40));
            $groups[$key]['category'] = $chamado->getCategoria();
            $groups[$key]['title'] = $chamado->getTitulo();
            $groups[$key]['tickets'][] = $chamado->getCodigo();
        }

        $since30 = (new \DateTimeImmutable())->modify('-30 days');
        $similar = [];
        $open = array_filter($chamados, static fn ($c) => $c->getStatus() !== \App\Entity\TiChamado::STATUS_RESOLVIDO);
        $openArr = array_values($open);
        for ($i = 0; $i < \count($openArr); $i++) {
            for ($j = $i + 1; $j < \count($openArr); $j++) {
                $a = $openArr[$i];
                $b = $openArr[$j];
                if ($a->getAbertoEm() < $since30 && $b->getAbertoEm() < $since30) {
                    continue;
                }
                $tokA = $this->tokenize($a->getTitulo());
                $tokB = $this->tokenize($b->getTitulo());
                $score = $this->jaccardSimilarity($tokA, $tokB);
                if ($score >= 0.4 || ($a->getCategoria() === $b->getCategoria() && $score >= 0.25)) {
                    $key = $a->getCategoria() . '|' . mb_strtolower(mb_substr($a->getTitulo(), 0, 40));
                    if (!isset($groups[$key])) {
                        $groups[$key] = ['category' => $a->getCategoria(), 'title' => $a->getTitulo(), 'tickets' => []];
                    }
                    if (!\in_array($a->getCodigo(), $groups[$key]['tickets'], true)) {
                        $groups[$key]['tickets'][] = $a->getCodigo();
                    }
                    if (!\in_array($b->getCodigo(), $groups[$key]['tickets'], true)) {
                        $groups[$key]['tickets'][] = $b->getCodigo();
                    }
                    $groups[$key]['similarity_score'] = (int) round($score * 100);
                    $groups[$key]['suggested_problema_titulo'] = $a->getTitulo();
                }
            }
        }

        $suggestions = [];
        foreach ($groups as $group) {
            if (\count($group['tickets'] ?? []) >= $minCount) {
                $suggestions[] = [
                    'title' => $group['title'],
                    'category' => $group['category'],
                    'ticket_ids' => $group['tickets'],
                    'count' => \count($group['tickets']),
                    'similarity_score' => $group['similarity_score'] ?? null,
                    'suggested_problema_titulo' => $group['suggested_problema_titulo'] ?? null,
                ];
            }
        }

        return $suggestions;
    }

    /** @return list<array<string, mixed>> */
    public function detectSimilarToTicket(Empresa $empresa, array $ticket, int $days = 30): array
    {
        $similar = [];
        $since = (new \DateTimeImmutable())->modify("-{$days} days");
        $tokens = $this->tokenize((string) ($ticket['title'] ?? ''));
        foreach ($this->chamadoRepository->findByEmpresa($empresa) as $c) {
            if ($c->getCodigo() === ($ticket['id'] ?? '')) {
                continue;
            }
            if ($c->getAbertoEm() < $since) {
                continue;
            }
            $otherTokens = $this->tokenize($c->getTitulo());
            $score = $this->jaccardSimilarity($tokens, $otherTokens);
            if ($score >= 0.35 || ($c->getCategoria() === ($ticket['category'] ?? '') && $score >= 0.2)) {
                $similar[] = [
                    'id' => $c->getCodigo(),
                    'title' => $c->getTitulo(),
                    'score' => (int) round($score * 100),
                    'category' => $c->getCategoria(),
                    'status' => $c->getStatus(),
                ];
            }
        }
        usort($similar, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return \array_slice($similar, 0, 5);
    }

    private function tokenize(string $text): array
    {
        $clean = preg_replace('/[^a-z0-9\s]/ui', ' ', $text) ?? '';
        $clean = mb_strtolower($clean);

        return array_values(array_filter(array_unique(explode(' ', $clean)), static fn ($w) => mb_strlen($w) > 2));
    }

    private function jaccardSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $intersection = \count(array_intersect($a, $b));
        $union = \count(array_unique(array_merge($a, $b)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    public function create(Empresa $empresa, array $data): TiProblema
    {
        $num = $this->repository->nextCodigoNumber($empresa);
        $p = new TiProblema();
        $p->setEmpresa($empresa)
            ->setCodigo('PR-' . str_pad((string) $num, 4, '0', STR_PAD_LEFT))
            ->setTitulo($this->requireString($data, 'titulo'))
            ->setResumo($this->requireString($data, 'resumo'))
            ->setStatus((string) ($data['status'] ?? TiProblema::STATUS_ABERTO))
            ->setPrioridade((string) ($data['prioridade'] ?? 'P3'))
            ->setCategoria((string) ($data['categoria'] ?? 'sistema'))
            ->setCausaRaiz(trim((string) ($data['causa_raiz'] ?? '')) ?: null);
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    public function load(Empresa $empresa, int $id): TiProblema
    {
        $p = $this->repository->find($id);
        if (!$p || $p->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Problema não encontrado.');
        }

        return $p;
    }

    public function update(TiProblema $problema, array $data): void
    {
        $problema->setTitulo($this->requireString($data, 'titulo'))
            ->setResumo($this->requireString($data, 'resumo'))
            ->setStatus((string) ($data['status'] ?? $problema->getStatus()))
            ->setPrioridade((string) ($data['prioridade'] ?? $problema->getPrioridade()))
            ->setCategoria((string) ($data['categoria'] ?? $problema->getCategoria()))
            ->setCausaRaiz(trim((string) ($data['causa_raiz'] ?? '')) ?: null)
            ->touch();
        $this->em->flush();
    }

    public function delete(TiProblema $problema): void
    {
        $this->em->remove($problema);
        $this->em->flush();
    }

    /** @return list<array<string, mixed>> */
    public function ticketsForProblema(TiProblema $problema): array
    {
        $rows = [];
        foreach ($this->chamadoRepository->findByEmpresa($problema->getEmpresa()) as $chamado) {
            if ($chamado->getProblema()?->getId() === $problema->getId()) {
                $rows[] = [
                    'id' => $chamado->getCodigo(),
                    'title' => $chamado->getTitulo(),
                    'status' => $chamado->getStatus(),
                    'priority' => $chamado->getPrioridade(),
                ];
            }
        }

        return $rows;
    }

    private function countTickets(TiProblema $problema): int
    {
        return \count($this->ticketsForProblema($problema));
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
