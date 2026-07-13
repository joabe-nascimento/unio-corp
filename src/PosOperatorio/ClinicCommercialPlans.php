<?php

namespace App\PosOperatorio;

/**
 * Planos comerciais públicos Unio Saúde — alinhados ao escopo entregue.
 *
 * Comparativo de mercado (interno, jul/2026) — NÃO exibir na landing:
 * OnDoctor ~R$79,90/user · Feegow ~R$129/prof · Clinicorp ~R$127/clínica.
 * Documentação: docs/UNIOSAUDE_PRECOS_MERCADO.md
 */
final class ClinicCommercialPlans
{
    public const ESSENCIAL = 'essencial';
    public const CLINICA = 'clinica';
    public const REDE = 'rede';

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            [
                'id' => self::ESSENCIAL,
                'nome' => 'Essencial',
                'preco' => 'R$ 189',
                'preco_sufixo' => '/ clínica / mês',
                'preco_nota' => 'Equipe de recepção e enfermagem inclusa',
                'destaque' => false,
                'cta' => 'Começar no Essencial',
                'max_beneficiarios' => 100,
                'inclui' => [
                    'Pacientes, protocolos e questionários',
                    'Alertas P1–P4 e fila do dia',
                    'Portal do paciente',
                    'Até 100 pacientes ativos',
                ],
            ],
            [
                'id' => self::CLINICA,
                'nome' => 'Clínica',
                'preco' => 'R$ 279',
                'preco_sufixo' => '/ clínica / mês',
                'preco_nota' => 'Recomendado: operação, TISS e CRM comercial',
                'destaque' => true,
                'cta' => 'Escolher Clínica',
                'max_beneficiarios' => 500,
                'inclui' => [
                    'Tudo do Essencial',
                    'Agenda, atendimento e contas',
                    'Convênios, guias TISS, lote e XML',
                    'Carteirinha, comprovante e guia médico',
                    'Sala crítica, plantão e recepção',
                    'CRM comercial (leads, pipeline e clientes)',
                    'Até 500 pacientes ativos',
                ],
            ],
            [
                'id' => self::REDE,
                'nome' => 'Rede',
                'preco' => 'Sob consulta',
                'preco_sufixo' => '',
                'preco_nota' => 'A partir de ~R$ 499/mês ou cotação. White-label e multi-unidade',
                'destaque' => false,
                'cta' => 'Falar com especialista',
                'max_beneficiarios' => 2000,
                'inclui' => [
                    'Tudo do Clínica (inclui CRM)',
                    'White-label, branding e onboarding',
                    'Limites altos / multi-unidade',
                    'Prioridade em integrações e suporte',
                ],
            ],
        ];
    }

    /** @return list<array{id: string, label: string, icon: string}> */
    public static function landingSpecialties(): array
    {
        return [
            ['id' => 'plastica', 'label' => 'Cirurgia plástica / estética', 'icon' => 'fa-spa'],
            ['id' => 'ortopedia', 'label' => 'Ortopedia', 'icon' => 'fa-bone'],
            ['id' => 'bariatrica', 'label' => 'Bariátrica', 'icon' => 'fa-heartbeat'],
            ['id' => 'dayclinic', 'label' => 'Day clinic / ambulatorial', 'icon' => 'fa-hospital'],
            ['id' => 'rede', 'label' => 'Rede multidisciplinar', 'icon' => 'fa-network-wired'],
            ['id' => 'outras', 'label' => 'Outras cirúrgicas', 'icon' => 'fa-plus'],
        ];
    }

    public static function defaultPlanId(): string
    {
        return self::CLINICA;
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $plan) {
            if ($plan['id'] === $id) {
                return $plan;
            }
        }

        return null;
    }
}
