<?php

namespace App\Config;

/**
 * Catálogo estático de conectores disponíveis no Núcleo Integrações.
 */
final class IntegracaoCatalogRegistry
{
    /** @var list<array{id: string, nome: string, categoria: string, icon: string, badge: string, descricao: string, hubs: list<string>, eventos: list<string>}> */
    public const ITEMS = [
        [
            'id' => 'slack',
            'nome' => 'Slack',
            'categoria' => 'produtividade',
            'icon' => 'fa-comment-dots',
            'badge' => 'oficial',
            'descricao' => 'Notificações, comandos slash e alertas operacionais nos canais da equipe.',
            'hubs' => ['RH', 'TI', 'Pessoas'],
            'eventos' => ['chamado.aberto', 'admissao.concluida', 'ferias.aprovada'],
        ],
        [
            'id' => 'microsoft365',
            'nome' => 'Microsoft 365',
            'categoria' => 'produtividade',
            'icon' => 'fa-envelope',
            'badge' => 'oficial',
            'descricao' => 'SSO, provisionamento de usuários e sincronização de calendário.',
            'hubs' => ['RH', 'Pessoas'],
            'eventos' => ['usuario.criado', 'usuario.desativado'],
        ],
        [
            'id' => 'google_workspace',
            'nome' => 'Google Workspace',
            'categoria' => 'produtividade',
            'icon' => 'fa-envelope-open-text',
            'badge' => 'oficial',
            'descricao' => 'Contas Google, grupos e drive corporativo.',
            'hubs' => ['RH', 'Pessoas'],
            'eventos' => ['usuario.criado', 'equipe.atualizada'],
        ],
        [
            'id' => 'totvs',
            'nome' => 'TOTVS ERP',
            'categoria' => 'financeiro',
            'icon' => 'fa-building-columns',
            'badge' => 'parceiro',
            'descricao' => 'Sincronização de folha, centros de custo e organograma com ERP.',
            'hubs' => ['RH', 'Financeiro'],
            'eventos' => ['folha.fechada', 'funcionario.atualizado'],
        ],
        [
            'id' => 'esocial',
            'nome' => 'eSocial',
            'categoria' => 'rh',
            'icon' => 'fa-landmark',
            'badge' => 'oficial',
            'descricao' => 'Envio de eventos trabalhistas e monitoramento de retornos gov.br.',
            'hubs' => ['RH'],
            'eventos' => ['admissao.concluida', 'demissao.concluida', 'afastamento.registrado'],
        ],
        [
            'id' => 'ponto_eletronico',
            'nome' => 'Ponto Eletrônico',
            'categoria' => 'rh',
            'icon' => 'fa-clock',
            'badge' => 'parceiro',
            'descricao' => 'Importação de batidas, espelho de ponto e banco de horas.',
            'hubs' => ['RH'],
            'eventos' => ['ponto.importado', 'jornada.excedida'],
        ],
        [
            'id' => 'active_directory',
            'nome' => 'Active Directory',
            'categoria' => 'identidade',
            'icon' => 'fa-shield-halved',
            'badge' => 'oficial',
            'descricao' => 'SSO SAML/OIDC e provisionamento automático de contas.',
            'hubs' => ['RH', 'TI'],
            'eventos' => ['usuario.criado', 'usuario.desativado', 'grupo.atualizado'],
        ],
        [
            'id' => 'webhook_generico',
            'nome' => 'Webhook genérico',
            'categoria' => 'dados',
            'icon' => 'fa-code',
            'badge' => 'custom',
            'descricao' => 'Endpoint REST configurável para qualquer sistema externo.',
            'hubs' => ['Todos'],
            'eventos' => ['*'],
        ],
        [
            'id' => 'zapier',
            'nome' => 'Zapier / Make',
            'categoria' => 'dados',
            'icon' => 'fa-puzzle-piece',
            'badge' => 'parceiro',
            'descricao' => 'Automações no-code conectando centenas de apps ao ecossistema UNio.',
            'hubs' => ['Todos'],
            'eventos' => ['*'],
        ],
        [
            'id' => 'ats',
            'nome' => 'ATS / Recrutamento',
            'categoria' => 'rh',
            'icon' => 'fa-user-tie',
            'badge' => 'parceiro',
            'descricao' => 'Pipeline de candidatos integrado à admissão no RH.',
            'hubs' => ['Talentos', 'RH'],
            'eventos' => ['candidato.aprovado', 'admissao.iniciada'],
        ],
        [
            'id' => 'bi_analytics',
            'nome' => 'BI / Analytics',
            'categoria' => 'dados',
            'icon' => 'fa-chart-line',
            'badge' => 'custom',
            'descricao' => 'Exportação de KPIs e datasets para Power BI, Looker ou Metabase.',
            'hubs' => ['Analytics', 'RH'],
            'eventos' => ['kpi.atualizado', 'relatorio.gerado'],
        ],
        [
            'id' => 'nfse',
            'nome' => 'NF-e / NFS-e',
            'categoria' => 'financeiro',
            'icon' => 'fa-file-invoice',
            'badge' => 'parceiro',
            'descricao' => 'Emissão e recebimento de notas fiscais de serviço.',
            'hubs' => ['Financeiro'],
            'eventos' => ['nota.emitida', 'nota.cancelada'],
        ],
    ];

    /** @var list<array{id: string, titulo: string, descricao: string, passos: list<string>, icon: string, categoria: string}> */
    public const PLAYBOOKS = [
        [
            'id' => 'admissao_ad',
            'titulo' => 'Admissão RH → Active Directory',
            'descricao' => 'Provisiona conta AD automaticamente quando admissão for concluída no RH.',
            'passos' => [
                'Ative o conector Active Directory no catálogo',
                'Configure webhook de saída: evento rh.admissao.concluida',
                'Mapeie campos: nome, e-mail, departamento, cargo',
                'Teste com uma admissão sandbox e valide no AD',
            ],
            'icon' => 'fa-user-plus',
            'categoria' => 'rh',
        ],
        [
            'id' => 'chamado_slack',
            'titulo' => 'Chamado TI → Slack #suporte',
            'descricao' => 'Notifica canal Slack quando chamado P1 for aberto no Núcleo TI.',
            'passos' => [
                'Ative conector Slack e informe webhook URL do canal',
                'Crie webhook de entrada no Núcleo Integrações',
                'Vincule evento ti.chamado.aberto com filtro prioridade=P1',
                'Configure template de mensagem e teste',
            ],
            'icon' => 'fa-headset',
            'categoria' => 'ti',
        ],
        [
            'id' => 'folha_erp',
            'titulo' => 'Folha UNio → ERP TOTVS',
            'descricao' => 'Exporta competência fechada para o ERP contábil.',
            'passos' => [
                'Ative conector TOTVS com credenciais de API',
                'Mapeie centros de custo e rubricas da folha',
                'Agende sync mensal pós-fechamento da folha',
                'Monitore logs e configure retry em falhas',
            ],
            'icon' => 'fa-file-invoice-dollar',
            'categoria' => 'financeiro',
        ],
        [
            'id' => 'esocial_eventos',
            'titulo' => 'Eventos RH → eSocial',
            'descricao' => 'Envia S-2200, S-2230 e demais eventos após workflows de RH.',
            'passos' => [
                'Ative conector eSocial com certificado A1',
                'Configure fila de eventos por tipo de movimentação',
                'Valide retornos gov.br no painel de logs',
                'Ative alertas para rejeições e pendências',
            ],
            'icon' => 'fa-landmark',
            'categoria' => 'rh',
        ],
    ];

    /** @var list<array{id: string, label: string}> */
    public const CATEGORIAS = [
        ['id' => 'produtividade', 'label' => 'Produtividade'],
        ['id' => 'rh', 'label' => 'RH & Pessoas'],
        ['id' => 'financeiro', 'label' => 'Financeiro'],
        ['id' => 'identidade', 'label' => 'Identidade & SSO'],
        ['id' => 'dados', 'label' => 'Dados & Automação'],
    ];

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return self::ITEMS;
    }

    public static function find(string $id): ?array
    {
        foreach (self::ITEMS as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    public static function playbooks(): array
    {
        return self::PLAYBOOKS;
    }
}
