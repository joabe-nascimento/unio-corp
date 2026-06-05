<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiAtivo;
use App\Entity\TiManutencao;
use App\Repository\TiAtivoRepository;
use App\Repository\TiIntegracaoLogRepository;
use App\Repository\TiIntegracaoRepository;
use App\Repository\TiLicencaRepository;
use App\Repository\TiManutencaoRepository;

final class TiInfraService
{
    public function __construct(
        private TiInfraSeedService $seedService,
        private TiAtivoRepository $ativoRepo,
        private TiLicencaRepository $licencaRepo,
        private TiIntegracaoRepository $integracaoRepo,
        private TiIntegracaoLogRepository $logRepo,
        private TiManutencaoRepository $manutencaoRepo,
    ) {}

    public function ensureInitialized(Empresa $empresa): void
    {
        $this->seedService->seedIfEmpty($empresa);
    }

    /** @return array{total: int, ativos: int, estoque: int, sem_owner: int} */
    public function assetStats(Empresa $empresa): array
    {
        $this->ensureInitialized($empresa);

        return [
            'total' => $this->ativoRepo->countByEmpresa($empresa),
            'ativos' => $this->ativoRepo->countByEmpresaAndStatus($empresa, TiAtivo::STATUS_ATIVO),
            'estoque' => $this->ativoRepo->countByEmpresaAndStatus($empresa, TiAtivo::STATUS_ESTOQUE),
            'sem_owner' => $this->ativoRepo->countSemResponsavel($empresa),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function assets(Empresa $empresa): array
    {
        $this->ensureInitialized($empresa);

        return array_map(
            static fn ($a) => $a->toArray(),
            $this->ativoRepo->findByEmpresa($empresa),
        );
    }

    /** @return list<array{label: string, pct: int, color: string}> */
    public function assetDnaStages(Empresa $empresa): array
    {
        $this->ensureInitialized($empresa);
        $items = $this->ativoRepo->findByEmpresa($empresa);
        if ($items === []) {
            return $this->defaultDnaStages();
        }

        $buckets = [
            'Aquisição' => ['max' => 20, 'color' => '#64748B', 'count' => 0],
            'Deploy' => ['max' => 35, 'color' => '#06B6D4', 'count' => 0],
            'Operação' => ['max' => 70, 'color' => '#10B981', 'count' => 0],
            'Manutenção' => ['max' => 90, 'color' => '#F59E0B', 'count' => 0],
            'Baixa' => ['max' => 100, 'color' => '#EF4444', 'count' => 0],
        ];

        foreach ($items as $ativo) {
            $ciclo = $ativo->getCicloPct();
            if ($ativo->getStatus() === TiAtivo::STATUS_MANUTENCAO) {
                ++$buckets['Manutenção']['count'];
            } elseif ($ciclo <= 20) {
                ++$buckets['Aquisição']['count'];
            } elseif ($ciclo <= 35) {
                ++$buckets['Deploy']['count'];
            } elseif ($ciclo <= 70) {
                ++$buckets['Operação']['count'];
            } elseif ($ciclo <= 90) {
                ++$buckets['Manutenção']['count'];
            } else {
                ++$buckets['Baixa']['count'];
            }
        }

        $total = max(1, \count($items));
        $stages = [];
        foreach ($buckets as $label => $meta) {
            $stages[] = [
                'label' => $label,
                'pct' => (int) round($meta['count'] / $total * 100),
                'color' => $meta['color'],
            ];
        }

        return $stages;
    }

    /** @return list<array{label: string, pct: int, color: string}> */
    private function defaultDnaStages(): array
    {
        return [
            ['label' => 'Aquisição', 'pct' => 0, 'color' => '#64748B'],
            ['label' => 'Deploy', 'pct' => 0, 'color' => '#06B6D4'],
            ['label' => 'Operação', 'pct' => 0, 'color' => '#10B981'],
            ['label' => 'Manutenção', 'pct' => 0, 'color' => '#F59E0B'],
            ['label' => 'Baixa', 'pct' => 0, 'color' => '#EF4444'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function licenses(Empresa $empresa): array
    {
        $this->ensureInitialized($empresa);

        return array_map(
            static fn ($l) => $l->toArray(),
            $this->licencaRepo->findByEmpresa($empresa),
        );
    }

    /** @return array{count: int, cost_label: string, avg_burn: int} */
    public function licenseKpis(Empresa $empresa): array
    {
        $licenses = $this->licencaRepo->findByEmpresa($empresa);
        if ($licenses === []) {
            return ['count' => 0, 'cost_label' => 'R$ 0', 'avg_burn' => 0];
        }

        $cost = 0.0;
        $burn = 0;
        foreach ($licenses as $lic) {
            $cost += (float) $lic->getCustoMensal();
            $burn += $lic->burnPct();
        }

        return [
            'count' => \count($licenses),
            'cost_label' => 'R$ ' . number_format($cost, 1, ',', '.') . ' k',
            'avg_burn' => (int) round($burn / \count($licenses)),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function licenseAlerts(Empresa $empresa): array
    {
        $alerts = [];
        $now = new \DateTimeImmutable();
        foreach ($this->licencaRepo->findByEmpresa($empresa) as $lic) {
            if ($lic->getUsed() >= $lic->getSeats()) {
                $alerts[] = ['type' => 'seats', 'name' => $lic->getNome(), 'detail' => 'Seats esgotados'];
            }
            if ($lic->getRenovacaoEm() <= $now->modify('+30 days')) {
                $alerts[] = ['type' => 'renewal', 'name' => $lic->getNome(), 'detail' => 'Renova em ' . $lic->getRenovacaoEm()->format('d/m/Y')];
            }
        }

        return $alerts;
    }

    /** @return list<array<string, mixed>> */
    public function licenseRenewalAlerts(Empresa $empresa, int $days = 45): array
    {
        $this->ensureInitialized($empresa);
        $alerts = [];
        foreach ($this->licencaRepo->findByEmpresa($empresa) as $lic) {
            $daysLeft = (int) \round(($lic->getRenovacaoEm()->getTimestamp() - \time()) / 86400);
            if ($daysLeft <= $days && $daysLeft >= 0) {
                $alerts[] = [
                    'id'        => $lic->getId(),
                    'name'      => $lic->getNome(),
                    'days_left' => $daysLeft,
                    'renewal'   => $lic->getRenovacaoEm()->format('d/m/Y'),
                    'cost'      => $lic->toArray()['cost'],
                    'burn'      => $lic->burnPct(),
                    'urgency'   => $daysLeft <= 7 ? 'danger' : ($daysLeft <= 15 ? 'warning' : 'info'),
                ];
            }
        }
        \usort($alerts, static fn ($a, $b) => $a['days_left'] <=> $b['days_left']);

        return $alerts;
    }

    /** @return list<array<string, mixed>> */
    public function integrations(Empresa $empresa): array
    {
        $this->ensureInitialized($empresa);

        return array_map(
            static fn ($i) => $i->toArray(),
            $this->integracaoRepo->findByEmpresa($empresa),
        );
    }

    public function integrationCount(Empresa $empresa): int
    {
        $this->ensureInitialized($empresa);

        return $this->integracaoRepo->countByEmpresa($empresa);
    }

    /** @return list<array<string, mixed>> */
    public function integrationLogs(Empresa $empresa, int $limit = 20): array
    {
        $this->ensureInitialized($empresa);

        return array_map(
            static fn ($l) => $l->toArray(),
            $this->logRepo->findRecentByEmpresa($empresa, $limit),
        );
    }

    /** @return list<array<string, mixed>> */
    public function maintenances(Empresa $empresa): array
    {
        $this->ensureInitialized($empresa);

        return array_map(
            static fn ($m) => $m->toArray(),
            $this->manutencaoRepo->findByEmpresa($empresa),
        );
    }

    public function scheduledMaintenanceCount(Empresa $empresa): int
    {
        $this->ensureInitialized($empresa);

        return $this->manutencaoRepo->countByEmpresaAndStatus($empresa, TiManutencao::STATUS_SCHEDULED);
    }
}
