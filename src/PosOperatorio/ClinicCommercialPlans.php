<?php

namespace App\PosOperatorio;

/**
 * Planos comerciais públicos Unio Saúde — alinhados ao escopo entregue (não ERP completo).
 * Comparativo de mercado (jul/2026): OnDoctor ~R$79,90/user · Feegow ~R$129/prof · Clinicorp ~R$127/clínica.
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
                'preco_nota' => 'Recomendado: stack Unio completa de hoje',
                'destaque' => true,
                'cta' => 'Escolher Clínica',
                'max_beneficiarios' => 500,
                'inclui' => [
                    'Tudo do Essencial',
                    'Agenda (dia/semana + status + WhatsApp manual)',
                    'Carteirinha, comprovante e guia médico',
                    'Sala crítica, plantão e recepção',
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
                    'Tudo do Clínica',
                    'White-label, branding e onboarding',
                    'Limites altos / multi-unidade',
                    'Prioridade no roadmap (fatura, TISS, WA live)',
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
