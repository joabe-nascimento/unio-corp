<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\InovIdeia;
use App\Entity\InovImpactEntry;
use App\Repository\InovIdeiaRepository;
use App\Repository\InovImpactEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InovacaoImpactService
{
    public function __construct(
        private EntityManagerInterface $em,
        private InovImpactEntryRepository $repo,
        private InovIdeiaRepository $ideiaRepo,
    ) {}

    /** @return list<InovImpactEntry> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findByEmpresa($empresa);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): InovImpactEntry
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if (!$item) {
            throw new \InvalidArgumentException('Entrada de impacto não encontrada.');
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromForm(Empresa $empresa, array $data): InovImpactEntry
    {
        $titulo = trim((string) ($data['titulo'] ?? $data['title'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('Título é obrigatório.');
        }

        $entry = new InovImpactEntry();
        $entry->setEmpresa($empresa);
        $entry->setTitulo($titulo);
        $this->applyFormData($entry, $empresa, $data);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFromForm(InovImpactEntry $entry, array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? $data['title'] ?? $entry->getTitulo()));
        if ($titulo === '') {
            throw new \InvalidArgumentException('Título é obrigatório.');
        }

        $entry->setTitulo($titulo);
        $this->applyFormData($entry, $entry->getEmpresa(), $data);
        $this->em->flush();
    }

    public function delete(InovImpactEntry $entry): void
    {
        $this->em->remove($entry);
        $this->em->flush();
    }

    /** @return array<string, string> */
    public function computeTotals(array $entries): array
    {
        $withValue = array_values(array_filter(
            $entries,
            static fn ($e) => $e->getValorCapturado() && $e->getValorCapturado() !== '—'
        ));
        $withRoi = array_values(array_filter(
            $entries,
            static fn ($e) => $e->getRoi() && $e->getRoi() !== '—'
        ));

        $capturedSum = array_sum(array_map(fn ($e) => $this->parseValor($e->getValorCapturado()), $withValue));
        $projectedSum = array_sum(array_map(fn ($e) => $this->parseValor($e->getValorCapturado()), $entries));

        $roiValues = array_map(static fn ($e) => self::parseRoiFactor($e->getRoi()), $withRoi);
        $avgRoi = $roiValues !== [] ? array_sum($roiValues) / \count($roiValues) : 0.0;

        return [
            'captured' => $capturedSum > 0 ? $this->formatValor($capturedSum) : '—',
            'projected' => $projectedSum > 0 ? $this->formatValor($projectedSum) : '—',
            'roi' => $avgRoi > 0 ? number_format($avgRoi, 1, ',', '') . '×' : '—',
        ];
    }

    /**
     * @param list<InovImpactEntry> $entries
     * @param list<InovIdeia> $ideias
     * @return list<array<string, mixed>>
     */
    public function buildTimeline(array $entries, array $ideias): array
    {
        $months = [];
        $now = new \DateTimeImmutable();

        for ($i = 6; $i >= 0; --$i) {
            $month = $now->modify("-{$i} months");
            $key = $month->format('Y-m');
            $label = $this->monthLabel((int) $month->format('n'));
            $months[$key] = ['month' => $label, 'captured' => 0, 'projected' => 0];
        }

        foreach ($entries as $entry) {
            $key = $entry->getCriadoEm()->format('Y-m');
            if (!isset($months[$key])) {
                continue;
            }
            $months[$key]['captured'] += $this->parseValor($entry->getValorCapturado());
        }

        $activeCount = \count(array_filter(
            $ideias,
            static fn ($i) => !\in_array($i->getEstagio(), [InovIdeia::STAGE_ARQUIVADO, InovIdeia::STAGE_KILL], true)
        ));
        $baseProjected = max(10, $activeCount * 8);

        $i = 0;
        foreach ($months as &$row) {
            $row['captured'] = min(100, (int) round($row['captured'] / 1000));
            $row['projected'] = min(100, $baseProjected + ($i * 5));
            ++$i;
        }
        unset($row);

        return array_values($months);
    }

    private function parseValor(?string $value): float
    {
        if ($value === null || $value === '' || $value === '—') {
            return 0.0;
        }

        $normalized = strtolower(str_replace([' ', '.'], '', $value));
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^0-9.kx]/', '', $normalized) ?? '';

        if ($normalized === '') {
            return 0.0;
        }

        $multiplier = 1.0;
        if (str_contains($normalized, 'k')) {
            $multiplier = 1000.0;
            $normalized = str_replace('k', '', $normalized);
        } elseif (str_contains($normalized, 'm')) {
            $multiplier = 1_000_000.0;
            $normalized = str_replace('m', '', $normalized);
        }

        return (float) $normalized * $multiplier;
    }

    private static function parseRoiFactor(?string $roi): float
    {
        if ($roi === null || $roi === '' || $roi === '—') {
            return 0.0;
        }

        $normalized = str_replace(['×', 'x', ' '], '', strtolower($roi));
        $normalized = str_replace(',', '.', $normalized);

        return (float) $normalized;
    }

    private function formatValor(float $value): string
    {
        if ($value >= 1_000_000) {
            return 'R$ ' . number_format($value / 1_000_000, 1, ',', '') . ' M';
        }
        if ($value >= 1000) {
            return 'R$ ' . number_format($value / 1000, 0, ',', '') . ' k';
        }

        return 'R$ ' . number_format($value, 0, ',', '');
    }

    private function monthLabel(int $month): string
    {
        $labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        return $labels[$month - 1] ?? '';
    }

    /** @return array<string, mixed> */
    public function toArray(InovImpactEntry $entry): array
    {
        return [
            'id' => $entry->getId(),
            'title' => $entry->getTitulo(),
            'stage' => $entry->getEstagioLabel(),
            'value' => $entry->getValorCapturado() ?? '—',
            'roi' => $entry->getRoi() ?? '—',
            'status' => $entry->getStatus(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFormData(InovImpactEntry $entry, Empresa $empresa, array $data): void
    {
        if (isset($data['estagio_label']) || isset($data['stage'])) {
            $entry->setEstagioLabel((string) ($data['estagio_label'] ?? $data['stage'] ?? $entry->getEstagioLabel()));
        }
        if (isset($data['valor_capturado']) || isset($data['value'])) {
            $entry->setValorCapturado($this->nullIfEmpty($data['valor_capturado'] ?? $data['value'] ?? null));
        }
        if (isset($data['roi'])) {
            $entry->setRoi($this->nullIfEmpty($data['roi']));
        }
        if (isset($data['status'])) {
            $entry->setStatus((string) $data['status']);
        }
        if (isset($data['ideia_id'])) {
            $ideiaId = (int) $data['ideia_id'];
            $ideia = $ideiaId > 0 ? $this->ideiaRepo->findOneForEmpresa($empresa, $ideiaId) : null;
            $entry->setIdeia($ideia);
        }
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null || $value === '—') {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
