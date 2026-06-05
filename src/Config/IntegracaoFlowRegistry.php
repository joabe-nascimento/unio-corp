<?php

namespace App\Config;

/**
 * Ontologia de fluxos de negócio — malha causal cross-núcleo (Observatório Causal).
 */
final class IntegracaoFlowRegistry
{
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IDLE = 'idle';

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            [
                'key' => 'admissao_provisionamento',
                'titulo' => 'Admissão → AD → Slack',
                'descricao' => 'Provisionamento de conta e aviso ao time quando uma admissão RH é concluída.',
                'evento_gatilho' => 'rh.admissao.concluida',
                'hubs' => ['rh', 'integracoes', 'ti'],
                'conectores' => ['active_directory', 'slack'],
                'webhook_evento' => 'rh.usuario.criado',
            ],
            [
                'key' => 'folha_totvs_sync',
                'titulo' => 'Folha TOTVS — sincronização',
                'descricao' => 'Importação diária de registros de folha do ERP para a plataforma.',
                'evento_gatilho' => 'rh.folha.fechamento',
                'hubs' => ['rh', 'integracoes'],
                'conectores' => ['totvs'],
                'webhook_evento' => null,
            ],
            [
                'key' => 'esocial_compliance',
                'titulo' => 'eSocial — compliance S-2200',
                'descricao' => 'Envio e retorno de eventos trabalhistas com impacto em admissões pendentes.',
                'evento_gatilho' => 'rh.esocial.evento',
                'hubs' => ['rh', 'integracoes', 'ti'],
                'conectores' => ['esocial'],
                'webhook_evento' => null,
            ],
            [
                'key' => 'demissao_offboarding',
                'titulo' => 'Demissão → revogação AD',
                'descricao' => 'Desligamento RH dispara revogação de acessos e evento eSocial S-2299.',
                'evento_gatilho' => 'rh.demissao.concluida',
                'hubs' => ['rh', 'integracoes', 'ti'],
                'conectores' => ['active_directory', 'esocial'],
                'webhook_evento' => 'rh.usuario.desligado',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $flow) {
            if ($flow['key'] === $key) {
                return $flow;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function hubLabels(): array
    {
        return [
            'rh' => 'Núcleo RH',
            'integracoes' => 'Integrações',
            'ti' => 'Núcleo TI',
            'inovacao' => 'Núcleo Inovação',
        ];
    }

    /** @return array<string, string> */
    public static function hubIcons(): array
    {
        return [
            'rh' => 'fa-users',
            'integracoes' => 'fa-plug',
            'ti' => 'fa-headset',
            'inovacao' => 'fa-lightbulb',
        ];
    }

    /** @return array<string, string> */
    public static function nodeTypeLabels(): array
    {
        return [
            'evento_negocio' => 'Evento de negócio',
            'mapeamento' => 'Transformação',
            'conector' => 'Conector',
            'webhook' => 'Webhook',
            'efeito_hub' => 'Efeito downstream',
        ];
    }
}
