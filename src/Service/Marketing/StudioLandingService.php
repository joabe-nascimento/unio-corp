<?php

namespace App\Service\Marketing;

/**
 * Conteúdo da landing do site central Unio (uniowork.com.br).
 */
final class StudioLandingService
{
    /** @return list<array<string, mixed>> */
    public function verticals(): array
    {
        return [
            [
                'id' => 'saude',
                'label' => 'Saúde',
                'title' => 'Unio Saúde',
                'desc' => 'Pós-operatório, carteirinha digital e guia médico para clínicas.',
                'icon' => 'fa-heart-pulse',
                'theme' => 'saude',
                'url' => 'https://uniosaude.uniowork.com.br',
                'badge' => 'Produto ativo',
                'external' => true,
            ],
            [
                'id' => 'educacao',
                'label' => 'Educação',
                'title' => 'Instituições de ensino',
                'desc' => 'Portais, comunicação e operações para faculdades e redes educacionais.',
                'icon' => 'fa-graduation-cap',
                'theme' => 'educacao',
                'url' => '#cases-reais',
                'badge' => 'Case UNEF',
                'external' => false,
            ],
            [
                'id' => 'corporativo',
                'label' => 'Corporativo',
                'title' => 'Operações & RH',
                'desc' => 'Módulos de pessoas, engenharia, finanças e governança em um só organismo.',
                'icon' => 'fa-building',
                'theme' => 'corp',
                'url' => '#modulos-studio',
                'badge' => 'Plataforma',
                'external' => false,
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function hubs(): array
    {
        return [
            $this->hub(
                'rh',
                'Pessoas & RH',
                'Recrutamento, cargos, folha e portal do colaborador.',
                'fa-users',
                'app_rh',
                'https://images.unsplash.com/photo-1521737711866-ece3fd7a9fca?auto=format&fit=crop&w=800&q=80',
                'Centralize admissões, movimentações e comunicação com colaboradores. O módulo conecta recrutamento, cargos, folha e portal do colaborador na mesma shell.',
                ['Admissão digital com checklist', 'Portal do colaborador sem app', 'Organograma e cargos versionados', 'Integração com folha e benefícios'],
                [
                    ['value' => '18', 'label' => 'Admissões no mês'],
                    ['value' => '94%', 'label' => 'Checklists OK'],
                    ['value' => '6', 'label' => 'Vagas abertas'],
                ],
                [
                    ['ago' => 'há 12 min', 'type' => 'admissao', 'icon' => 'fa-user-plus', 'text' => 'Nova admissão iniciada — documentação enviada ao colaborador'],
                    ['ago' => 'há 38 min', 'type' => 'portal', 'icon' => 'fa-mobile-screen', 'text' => 'Portal do colaborador: 7 acessos e 2 holerites consultados'],
                    ['ago' => 'há 1 h', 'type' => 'vaga', 'icon' => 'fa-briefcase', 'text' => 'Vaga «Analista de Operações» movida para entrevistas'],
                    ['ago' => 'há 2 h', 'type' => 'alerta', 'icon' => 'fa-bell', 'text' => '3 contratos com vencimento de experiência nesta semana'],
                    ['ago' => 'há 4 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Folha de março validada pela equipe de RH'],
                ],
            ),
            $this->hub(
                'engenharia',
                'Engenharia',
                'Projetos, entregas e playbooks de implementação.',
                'fa-compass-drafting',
                'app_engenharia',
                'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=800&q=80',
                'Acompanhe obras e projetos digitais com entregas rastreáveis, playbooks reutilizáveis e visão de prioridade para a equipe técnica.',
                ['Playbooks por tipo de entrega', 'Marcos e SLA por projeto', 'Biblioteca de implementação', 'Alertas de desvio de prazo'],
                [
                    ['value' => '9', 'label' => 'Projetos ativos'],
                    ['value' => '2', 'label' => 'Prioridade alta'],
                    ['value' => '97%', 'label' => 'Entregas no prazo'],
                ],
                [
                    ['ago' => 'há 8 min', 'type' => 'entrega', 'icon' => 'fa-flag-checkered', 'text' => 'Marco «Homologação» concluído no projeto Atlas Web'],
                    ['ago' => 'há 25 min', 'type' => 'playbook', 'icon' => 'fa-book', 'text' => 'Playbook «Deploy HostGator» atualizado com nova etapa de cache'],
                    ['ago' => 'há 52 min', 'type' => 'alerta', 'icon' => 'fa-triangle-exclamation', 'text' => 'Desvio de prazo detectado — revisão agendada para hoje'],
                    ['ago' => 'há 1 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Entrega de API documentada e publicada no portal do cliente'],
                    ['ago' => 'há 3 h', 'type' => 'projeto', 'icon' => 'fa-folder-open', 'text' => 'Novo projeto «Portal UNEF» vinculado ao playbook de educação'],
                ],
            ),
            $this->hub(
                'pos-operatorio',
                'Pós-operatório',
                'Acompanhamento clínico modular para clínicas e hospitais.',
                'fa-user-nurse',
                'app_pos_operatorio',
                'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?auto=format&fit=crop&w=800&q=80',
                'Produto vertical Unio Saúde: protocolos pós-cirúrgicos, alertas clínicos, questionários e portal do paciente em um módulo ativável.',
                ['Protocolos por procedimento', 'Fila P1–P4 com SLA', 'Questionários diários do paciente', 'Carteirinha e guia integrados'],
                [
                    ['value' => '42', 'label' => 'Pacientes ativos'],
                    ['value' => '3', 'label' => 'Alertas críticos'],
                    ['value' => '94%', 'label' => 'Questionários hoje'],
                ],
                [
                    ['ago' => 'há 5 min', 'type' => 'alerta', 'icon' => 'fa-heart-pulse', 'text' => 'Alerta P2 aberto — paciente D+3 com dor elevada no questionário'],
                    ['ago' => 'há 18 min', 'type' => 'portal', 'icon' => 'fa-mobile-screen', 'text' => '18 pacientes responderam questionário matinal'],
                    ['ago' => 'há 40 min', 'type' => 'protocolo', 'icon' => 'fa-clipboard-list', 'text' => 'Protocolo «Laparoscopia» aplicado a novo cadastro'],
                    ['ago' => 'há 1 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Sala Crítica sem casos P1 — equipe liberada'],
                    ['ago' => 'há 2 h', 'type' => 'carteirinha', 'icon' => 'fa-id-card', 'text' => 'Carteirinha digital emitida para beneficiário Premium'],
                ],
            ),
            $this->hub(
                'ti',
                'TI & Inovação',
                'Chamados, integrações e experimentação controlada.',
                'fa-microchip',
                'app_ti',
                'https://images.unsplash.com/photo-1518770660439-4636190af475?auto=format&fit=crop&w=800&q=80',
                'Gerencie chamados, integrações e experimentos com trilha de aprovação. Ideal para squads que precisam de ritmo sem perder governança.',
                ['Fila de chamados com prioridade', 'Integrações e webhooks', 'Ambiente de experimentação', 'Histórico e SLA por área'],
                [
                    ['value' => '11', 'label' => 'Chamados abertos'],
                    ['value' => '4', 'label' => 'Integrações ativas'],
                    ['value' => '2h', 'label' => 'SLA médio'],
                ],
                [
                    ['ago' => 'há 6 min', 'type' => 'chamado', 'icon' => 'fa-ticket', 'text' => 'Chamado #184 — integração SMTP marcado como resolvido'],
                    ['ago' => 'há 22 min', 'type' => 'deploy', 'icon' => 'fa-rocket', 'text' => 'Deploy de patch aplicado em produção com smoke OK'],
                    ['ago' => 'há 45 min', 'type' => 'alerta', 'icon' => 'fa-bell', 'text' => 'Novo chamado P1 — indisponibilidade reportada no portal'],
                    ['ago' => 'há 1 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Webhook de notificações validado em homologação'],
                    ['ago' => 'há 3 h', 'type' => 'lab', 'icon' => 'fa-flask', 'text' => 'Experimento «Pulso v2» aprovado para piloto interno'],
                ],
            ),
            $this->hub(
                'financeiro',
                'Financeiro',
                'Indicadores, compliance e trilhas de aprovação.',
                'fa-chart-pie',
                'app_financeiro',
                'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=800&q=80',
                'Indicadores, compliance e fluxos de aprovação em um painel único — da solicitação à trilha auditável.',
                ['Dashboard de indicadores', 'Trilhas de aprovação', 'Exportação para auditoria', 'Alertas de desvio orçamentário'],
                [
                    ['value' => 'R$ 1,2M', 'label' => 'Pipeline aprovado'],
                    ['value' => '5', 'label' => 'Pendências'],
                    ['value' => '100%', 'label' => 'Trilha auditável'],
                ],
                [
                    ['ago' => 'há 15 min', 'type' => 'aprovacao', 'icon' => 'fa-stamp', 'text' => 'Despesa operacional aprovada na trilha nível 2'],
                    ['ago' => 'há 33 min', 'type' => 'alerta', 'icon' => 'fa-chart-line', 'text' => 'Desvio de 8% detectado na rubrica «Infraestrutura»'],
                    ['ago' => 'há 1 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Fechamento parcial de março exportado para auditoria'],
                    ['ago' => 'há 2 h', 'type' => 'pendencia', 'icon' => 'fa-hourglass-half', 'text' => '3 solicitações aguardando aprovação do gestor'],
                    ['ago' => 'há 4 h', 'type' => 'relatorio', 'icon' => 'fa-file-lines', 'text' => 'Relatório de compliance gerado automaticamente'],
                ],
            ),
            $this->hub(
                'operacoes',
                'Operações',
                'Governança, indicadores e fluxo entre áreas.',
                'fa-sitemap',
                'app_hub_operacoes',
                'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=800&q=80',
                'Visão transversal da operação: prioridades entre áreas, indicadores cruzados e governança sem silos.',
                ['Painel multi-módulo', 'Prioridades entre áreas', 'Indicadores cruzados', 'Rituais e check-ins de gestão'],
                [
                    ['value' => '24', 'label' => 'Sinais ativos'],
                    ['value' => '7', 'label' => 'Áreas conectadas'],
                    ['value' => '3', 'label' => 'Prioridades hoje'],
                ],
                [
                    ['ago' => 'há 9 min', 'type' => 'prioridade', 'icon' => 'fa-flag', 'text' => 'Prioridade alta redistribuída entre Engenharia e TI'],
                    ['ago' => 'há 28 min', 'type' => 'reuniao', 'icon' => 'fa-users', 'text' => 'Check-in semanal registrado — 5 entregas revisadas'],
                    ['ago' => 'há 55 min', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Indicador «Satisfação cliente» atualizado para 4,8'],
                    ['ago' => 'há 1 h', 'type' => 'alerta', 'icon' => 'fa-bell', 'text' => 'Gargalo detectado na fila de aprovações financeiras'],
                    ['ago' => 'há 3 h', 'type' => 'governanca', 'icon' => 'fa-shield-halved', 'text' => 'Política de acesso revisada e publicada para gestores'],
                ],
            ),
        ];
    }

    /**
     * @param list<string> $highlights
     * @param list<array{value: string, label: string}> $kpis
     * @param list<array{ago: string, type: string, icon: string, text: string}> $activities
     *
     * @return array<string, mixed>
     */
    private function hub(
        string $id,
        string $label,
        string $desc,
        string $icon,
        string $route,
        string $image,
        string $summary,
        array $highlights,
        array $kpis,
        array $activities,
    ): array {
        return [
            'id' => $id,
            'label' => $label,
            'desc' => $desc,
            'icon' => $icon,
            'route' => $route,
            'image' => $image,
            'summary' => $summary,
            'highlights' => $highlights,
            'kpis' => $kpis,
            'activities' => $activities,
        ];
    }

    /** @return list<array<string, string>> */
    public function cases(): array
    {
        return [
            [
                'name' => 'União Médica',
                'sector' => 'Saúde',
                'logo' => 'uniao-medica.jpg',
                'text' => 'Sistema clínico e comunicação com pacientes, com identidade visual própria da operadora.',
            ],
            [
                'name' => 'UNEF',
                'sector' => 'Educação',
                'logo' => 'unef-horizontal-branca.png',
                'text' => 'Presença digital e sistemas para instituição de ensino superior, com marca própria.',
            ],
        ];
    }
}
