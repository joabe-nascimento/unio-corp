<?php

namespace App\Service\Organismo;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\RhFeriasRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\DashboardStatsService;

/**
 * Agrega sinais vitais e cenas da colônia para o Pulso.
 */
final class PulsoService
{
    public function __construct(
        private PulsoCenaProvider $cenaProvider,
        private DashboardStatsService $dashboardStats,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhFeriasRepository $feriasRepo,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshot(User $user, ?Empresa $empresa, string $layout, int $empresasCount): array
    {
        $cenas = $this->cenaProvider->getCenas($user, $empresa);
        $ativas = 0;
        $aguardando = 0;

        foreach ($cenas as $cena) {
            if (($cena['estado'] ?? '') === 'ativa') {
                ++$ativas;
            } else {
                ++$aguardando;
            }
        }

        return [
            'colonia' => $empresa !== null ? [
                'id' => $empresa->getId(),
                'nome' => $empresa->getNome(),
                'slug' => $empresa->getSlug(),
            ] : null,
            'pulso' => [
                'nivel' => $this->resolveNivel($ativas, $aguardando),
                'cenas_ativas' => $ativas,
                'cenas_aguardando' => $aguardando,
                'headline' => $empresa !== null
                    ? $this->dashboardStats->getLayoutHeadline($layout, $empresa)
                    : 'Selecione uma colônia para ver o pulso',
            ],
            'cenas' => $cenas,
            'sinais' => $this->buildSinais($user, $empresa),
            'kpis' => $this->dashboardStats->getKpis($user, $empresa, $layout, $empresasCount),
        ];
    }

    /**
     * @return list<array{tipo: string, valor: int|string, rotulo: string, url?: string}>
     */
    private function buildSinais(User $user, ?Empresa $empresa): array
    {
        if ($empresa === null) {
            return [];
        }

        $sinais = [];

        $admissoes = $this->onboardingRepo->countOpenByEmpresa($empresa);
        if ($admissoes > 0) {
            $sinais[] = [
                'tipo' => 'admissoes_abertas',
                'valor' => $admissoes,
                'rotulo' => 'Admissões em andamento',
            ];
        }

        $ferias = $this->feriasRepo->countByStatus($empresa, \App\Entity\RhFerias::STATUS_SOLICITADA);
        if ($ferias > 0) {
            $sinais[] = [
                'tipo' => 'ferias_pendentes',
                'valor' => $ferias,
                'rotulo' => 'Férias aguardando aprovação',
            ];
        }

        if ($sinais === []) {
            $sinais[] = [
                'tipo' => 'pulso_estavel',
                'valor' => '—',
                'rotulo' => 'Pulso estável — nenhum sinal urgente',
            ];
        }

        return $sinais;
    }

    private function resolveNivel(int $ativas, int $aguardando): string
    {
        if ($ativas >= 5) {
            return 'intenso';
        }
        if ($ativas >= 2 || $aguardando >= 4) {
            return 'atento';
        }

        return 'saudavel';
    }
}
