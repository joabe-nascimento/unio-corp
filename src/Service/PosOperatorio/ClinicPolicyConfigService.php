<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;

/**
 * Política de continuidade por clínica (SLA, escada, retenção, canais).
 * Persistida em integracoes.policy via ClinicConfigStore — sem migration.
 */
final class ClinicPolicyConfigService
{
    public function __construct(
        private ClinicConfigStore $store,
    ) {}

    /**
     * @return array{
     *     sla: array{P1: int, P2: int, P3: int, P4: int},
     *     escalacao_horas: list<int>,
     *     canais: array{in_app: bool, email: bool, whatsapp: bool, sms: bool},
     *     triagem: array{dor_p1_min: float, dor_p2_min: float, febre_p2_min: float},
     *     retencao_dias: int,
     *     alta_token: string,
     *     continuity_lead: string
     * }
     */
    public function get(Empresa $empresa): array
    {
        $stored = $this->store->read($empresa, 'integracoes');
        $policy = \is_array($stored['policy'] ?? null) ? $stored['policy'] : [];

        return $this->normalize($policy);
    }

    /** @param array<string, mixed> $input */
    public function save(Empresa $empresa, array $input): void
    {
        $current = $this->store->read($empresa, 'integracoes');
        $current['policy'] = $this->normalize($input);
        $this->store->write($empresa, 'integracoes', $current);
    }

    public function slaMinutes(Empresa $empresa, string $prioridade): int
    {
        $sla = $this->get($empresa)['sla'];

        return $sla[$prioridade] ?? match ($prioridade) {
            'P1' => 15,
            'P2' => 60,
            'P3' => 240,
            default => 1440,
        };
    }

    /** @return array{P1: int, P2: int, P3: int, P4: int} */
    public function defaultSla(): array
    {
        return ['P1' => 15, 'P2' => 60, 'P3' => 240, 'P4' => 1440];
    }

    /**
     * @param array<string, mixed> $policy
     *
     * @return array{
     *     sla: array{P1: int, P2: int, P3: int, P4: int},
     *     escalacao_horas: list<int>,
     *     canais: array{in_app: bool, email: bool, whatsapp: bool, sms: bool},
     *     triagem: array{dor_p1_min: float, dor_p2_min: float, febre_p2_min: float},
     *     retencao_dias: int,
     *     alta_token: string,
     *     continuity_lead: string
     * }
     */
    private function normalize(array $policy): array
    {
        $slaIn = \is_array($policy['sla'] ?? null) ? $policy['sla'] : [];
        $canaisIn = \is_array($policy['canais'] ?? null) ? $policy['canais'] : [];
        $triagemIn = \is_array($policy['triagem'] ?? null) ? $policy['triagem'] : [];
        $horas = $policy['escalacao_horas'] ?? [4, 8, 24];
        if (!\is_array($horas)) {
            $horas = [4, 8, 24];
        }

        return [
            'sla' => [
                'P1' => max(5, min(240, (int) ($slaIn['P1'] ?? 15))),
                'P2' => max(15, min(720, (int) ($slaIn['P2'] ?? 60))),
                'P3' => max(60, min(1440, (int) ($slaIn['P3'] ?? 240))),
                'P4' => max(240, min(10080, (int) ($slaIn['P4'] ?? 1440))),
            ],
            'escalacao_horas' => array_values(array_unique(array_filter(
                array_map('intval', $horas),
                static fn (int $h): bool => $h > 0 && $h <= 72,
            ))) ?: [4, 8, 24],
            'canais' => [
                'in_app' => (bool) ($canaisIn['in_app'] ?? true),
                'email' => (bool) ($canaisIn['email'] ?? true),
                'whatsapp' => (bool) ($canaisIn['whatsapp'] ?? true),
                'sms' => (bool) ($canaisIn['sms'] ?? true),
            ],
            'triagem' => [
                'dor_p1_min' => (float) ($triagemIn['dor_p1_min'] ?? 8),
                'dor_p2_min' => (float) ($triagemIn['dor_p2_min'] ?? 6),
                'febre_p2_min' => (float) ($triagemIn['febre_p2_min'] ?? 38.5),
            ],
            'retencao_dias' => max(30, min(3650, (int) ($policy['retencao_dias'] ?? 365))),
            'alta_token' => trim((string) ($policy['alta_token'] ?? '')),
            'continuity_lead' => trim((string) ($policy['continuity_lead'] ?? 'Ninguém fica sem resposta.')),
        ];
    }
}
