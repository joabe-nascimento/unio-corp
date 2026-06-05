<?php

namespace App\Service\Ti;

/** Dados de referência do Núcleo TI (catálogo, categorias, SLA, KB). */
final class TiReferenceData
{
    /** @return list<array<string, mixed>> */
    public static function catalog(): array
    {
        return [
            ['id' => 'reset-senha', 'title' => 'Reset de senha', 'icon' => 'fa-key', 'sla' => '15 min', 'desc' => 'Desbloqueio e redefinição de credenciais AD/MFA.', 'category' => 'acesso', 'priority' => 'P2'],
            ['id' => 'novo-equipamento', 'title' => 'Novo equipamento', 'icon' => 'fa-laptop', 'sla' => '48 h', 'desc' => 'Notebook, monitor ou periférico para colaborador.', 'category' => 'hardware', 'priority' => 'P3'],
            ['id' => 'acesso-sistema', 'title' => 'Acesso a sistema', 'icon' => 'fa-door-open', 'sla' => '4 h', 'desc' => 'Perfis ERP, BI ou ferramentas departamentais.', 'category' => 'acesso', 'priority' => 'P2'],
            ['id' => 'vpn-remoto', 'title' => 'VPN / Acesso remoto', 'icon' => 'fa-shield-halved', 'sla' => '2 h', 'desc' => 'Configuração VPN e MFA para home office.', 'category' => 'rede', 'priority' => 'P2'],
            ['id' => 'incidente-rede', 'title' => 'Incidente de rede', 'icon' => 'fa-wifi', 'sla' => '30 min', 'desc' => 'Conectividade, Wi-Fi ou link dedicado.', 'category' => 'rede', 'priority' => 'P1'],
            ['id' => 'software', 'title' => 'Instalação de software', 'icon' => 'fa-download', 'sla' => '8 h', 'desc' => 'Apps homologados e licenças corporativas.', 'category' => 'sistema', 'priority' => 'P3'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function slaRules(): array
    {
        return [
            ['priority' => 'P1', 'label' => 'Crítico', 'response' => '15 min', 'resolution' => '4 h', 'compliance' => 91, 'resolution_hours' => 4],
            ['priority' => 'P2', 'label' => 'Alto', 'response' => '1 h', 'resolution' => '8 h', 'compliance' => 96, 'resolution_hours' => 8],
            ['priority' => 'P3', 'label' => 'Médio', 'response' => '4 h', 'resolution' => '24 h', 'compliance' => 98, 'resolution_hours' => 24],
            ['priority' => 'P4', 'label' => 'Baixo', 'response' => '8 h', 'resolution' => '72 h', 'compliance' => 99, 'resolution_hours' => 72],
        ];
    }

    public static function resolutionHours(string $priority): float
    {
        foreach (self::slaRules() as $rule) {
            if ($rule['priority'] === $priority) {
                return (float) $rule['resolution_hours'];
            }
        }

        return 24.0;
    }

    /** @return list<array<string, mixed>> */
    public static function categories(): array
    {
        return [
            ['id' => 'rede', 'label' => 'Rede & VPN'],
            ['id' => 'hardware', 'label' => 'Hardware'],
            ['id' => 'acesso', 'label' => 'Acesso & Senha'],
            ['id' => 'sistema', 'label' => 'Sistemas'],
            ['id' => 'integracao', 'label' => 'Integrações'],
            ['id' => 'licenca', 'label' => 'Licenças'],
            ['id' => 'seguranca', 'label' => 'Segurança'],
            ['id' => 'infra', 'label' => 'Infraestrutura'],
            ['id' => 'email', 'label' => 'E-mail'],
        ];
    }

    /** @return list<string> */
    public static function priorities(): array
    {
        return ['P1', 'P2', 'P3', 'P4'];
    }

    /** @return list<array<string, string>> */
    public static function impactLevels(): array
    {
        return [
            ['id' => 'baixo', 'label' => 'Baixo', 'desc' => '1 usuário · workaround disponível'],
            ['id' => 'medio', 'label' => 'Médio', 'desc' => 'Equipe ou área afetada'],
            ['id' => 'alto', 'label' => 'Alto', 'desc' => 'Operação crítica parcialmente indisponível'],
            ['id' => 'critico', 'label' => 'Crítico', 'desc' => 'Parada total ou risco de segurança'],
        ];
    }

    /** @return list<array<string, string>> */
    public static function locations(): array
    {
        return [
            ['id' => 'matriz', 'label' => 'Matriz — São Paulo'],
            ['id' => 'filial-rj', 'label' => 'Filial Rio de Janeiro'],
            ['id' => 'obra-sp', 'label' => 'Obra São Paulo'],
            ['id' => 'remoto', 'label' => 'Home office / remoto'],
        ];
    }

    /** @return list<array<string, string>> */
    public static function contactChannels(): array
    {
        return [
            ['id' => 'portal', 'label' => 'Portal (notificações in-app)'],
            ['id' => 'email', 'label' => 'E-mail corporativo'],
            ['id' => 'teams', 'label' => 'Microsoft Teams'],
            ['id' => 'telefone', 'label' => 'Telefone / WhatsApp'],
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'novo' => 'Novo',
            'em_analise' => 'Em análise',
            'em_execucao' => 'Em execução',
            'aguardando' => 'Aguardando',
            'resolvido' => 'Resolvido',
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function knowledgeBase(): array
    {
        return [
            ['id' => 'KB-042', 'title' => 'VPN pós-patch Windows — MFA e certificado', 'match' => 92, 'keywords' => ['vpn', 'windows', 'mfa', 'conect']],
            ['id' => 'KB-018', 'title' => 'Reset de senha AD — fluxo self-service', 'match' => 88, 'keywords' => ['senha', 'ad', 'login', 'bloque']],
            ['id' => 'KB-091', 'title' => 'Webhook eSocial — retry e fila de erro', 'match' => 95, 'keywords' => ['esocial', '502', 'webhook', 'integra']],
            ['id' => 'KB-033', 'title' => 'Backup SQL — quota de disco e limpeza', 'match' => 90, 'keywords' => ['backup', 'sql', 'disco']],
            ['id' => 'KB-055', 'title' => 'Impressora offline — fila e driver', 'match' => 84, 'keywords' => ['impressora', 'print']],
            ['id' => 'KB-067', 'title' => 'Provisionamento ERP — perfil Compras', 'match' => 86, 'keywords' => ['erp', 'acesso', 'admissão']],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function novidadesSeed(): array
    {
        return [
            ['title' => 'Janela de patch — firewall datacenter', 'summary' => '31/05 02:00–04:00 · VPN pode oscilar durante a janela.', 'badge' => 'Manutenção', 'variant' => 'warning', 'icon' => 'fa-screwdriver-wrench', 'date' => '28/05/2026'],
            ['title' => 'Política MFA obrigatória', 'summary' => 'A partir de 15/06, todos os acessos externos exigem MFA corporativo.', 'badge' => 'Política', 'variant' => 'info', 'icon' => 'fa-shield-halved', 'date' => '27/05/2026'],
            ['title' => 'Núcleo TI — inventário e licenças', 'summary' => 'CRUD de ativos, licenças, integrações e manutenções disponível no portal.', 'badge' => 'Release', 'variant' => 'success', 'icon' => 'fa-rocket', 'date' => '29/05/2026'],
            ['title' => 'Backup S3 — quota excedida', 'summary' => 'Node BKP-02 atingiu 94% de capacidade. Ação do time Infra em andamento.', 'badge' => 'Alerta', 'variant' => 'danger', 'icon' => 'fa-triangle-exclamation', 'date' => '29/05/2026'],
        ];
    }
}
