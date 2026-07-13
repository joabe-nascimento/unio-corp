<?php

namespace App\Service\Marketing;

use App\PosOperatorio\ClinicCommercialPlans;

/**
 * Conteúdo dos módulos da landing Unio Saúde (uniosaude.uniowork.com.br).
 */
final class ClinicLandingService
{
    /** @return list<array<string, mixed>> */
    public function hubs(): array
    {
        return [
            $this->hub(
                'pacientes',
                'Pacientes',
                'Ficha, evolução e histórico completo.',
                'fa-user-injured',
                'app_pos_operatorio_pacientes',
                'images/marketing/modules/mod-pacientes.jpg',
                'Centralize cadastro, evolução clínica e histórico do paciente em um só lugar. A equipe acompanha protocolos, anexos e linha do tempo sem planilhas paralelas.',
                ['Ficha clínica com evolução versionada', 'Linha do tempo por procedimento', 'Anexos e consentimentos LGPD', 'Busca e filtros por fase do protocolo'],
                [
                    ['value' => '42', 'label' => 'Pacientes ativos'],
                    ['value' => '6', 'label' => 'Altas esta semana'],
                    ['value' => '97%', 'label' => 'Fichas em dia'],
                ],
                [
                    ['ago' => 'há 7 min', 'type' => 'paciente', 'icon' => 'fa-user-injured', 'text' => 'Nova evolução registrada. João Pereira, D+3, dor 3/10'],
                    ['ago' => 'há 22 min', 'type' => 'protocolo', 'icon' => 'fa-clipboard-list', 'text' => 'Protocolo «Herniorrafia» aplicado a novo cadastro'],
                    ['ago' => 'há 48 min', 'type' => 'portal', 'icon' => 'fa-mobile-screen', 'text' => 'Portal do paciente ativado para Maria Silva'],
                    ['ago' => 'há 1 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => '6 altas registradas com checklist de encerramento completo'],
                    ['ago' => 'há 2 h', 'type' => 'anexo', 'icon' => 'fa-paperclip', 'text' => 'Exame de imagem anexado à ficha de Ana Costa'],
                ],
            ),
            $this->hub(
                'agenda',
                'Agenda',
                'Horários, status e retornos do protocolo.',
                'fa-calendar-alt',
                'app_pos_operatorio_agenda',
                'images/marketing/modules/mod-agenda.jpg',
                'Marque paciente e médico na mesma clínica. Marcos do protocolo (D+n) viram sugestão de horário. Menos retorno perdido, mais continuidade.',
                ['Lista semanal por médico', 'Status marcado → confirmado → atendido', 'Agendar a partir dos retornos', 'Mesmo organismo do pós-operatório'],
                [
                    ['value' => '18', 'label' => 'Horários na semana'],
                    ['value' => '5', 'label' => 'Do protocolo'],
                    ['value' => '3', 'label' => 'Médicos'],
                ],
                [
                    ['ago' => 'há 5 min', 'type' => 'agenda', 'icon' => 'fa-calendar-check', 'text' => 'Retorno D+14 agendado a partir do marco do protocolo'],
                    ['ago' => 'há 20 min', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Consulta confirmada. Dra. Helena, 09:00'],
                    ['ago' => 'há 1 h', 'type' => 'paciente', 'icon' => 'fa-user-injured', 'text' => 'Novo horário manual para João Pereira'],
                    ['ago' => 'há 2 h', 'type' => 'agenda', 'icon' => 'fa-calendar-day', 'text' => 'Semana filtrada por médico responsável'],
                    ['ago' => 'há 3 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Atendimento finalizado. conta particular aberta para pagamento'],
                ],
            ),
            $this->hub(
                'faturamento-tiss',
                'Faturamento TISS',
                'Contas, guias, lote e XML para o convênio.',
                'fa-file-invoice-dollar',
                'app_pos_operatorio_contas',
                'images/marketing/modules/mod-faturamento-tiss.jpg',
                ['Contas e convênios na mesma ficha', 'Guias TISS com catálogo TUSS', 'Lote/remessa e exportação XML', 'Status até glosa e pagamento'],
                [
                    ['value' => '24', 'label' => 'Guias abertas'],
                    ['value' => '3', 'label' => 'Lotes na semana'],
                    ['value' => 'XML', 'label' => 'Remessa pronta'],
                ],
                [
                    ['ago' => 'há 8 min', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Guia de consulta enviada ao lote da Unimed'],
                    ['ago' => 'há 25 min', 'type' => 'financeiro', 'icon' => 'fa-file-invoice-dollar', 'text' => 'Conta particular aberta após atendimento'],
                    ['ago' => 'há 1 h', 'type' => 'lote', 'icon' => 'fa-boxes-stacked', 'text' => 'Lote #118 fechado com 14 guias'],
                    ['ago' => 'há 2 h', 'type' => 'xml', 'icon' => 'fa-file-code', 'text' => 'XML de remessa gerado para a operadora'],
                    ['ago' => 'há 4 h', 'type' => 'glosa', 'icon' => 'fa-triangle-exclamation', 'text' => 'Glosa parcial registrada — guia em revisão'],
                ],
            ),
            $this->hub(
                'sala-critica',
                'Sala Crítica',
                'Monitoramento intensivo em tempo real.',
                'fa-bed-pulse',
                'app_pos_operatorio_sala_critica',
                'images/marketing/modules/mod-sala-critica.jpg',
                'Painel de monitoramento para casos P1 e P2. A equipe de enfermagem enxerga quem precisa de atenção imediata, com SLA e histórico de conduta.',
                ['Fila P1 com SLA em tempo real', 'Sinais vitais e questionários integrados', 'Atribuição rápida para enfermagem', 'Histórico de condutas por caso'],
                [
                    ['value' => '2', 'label' => 'Casos P1'],
                    ['value' => '12 min', 'label' => 'SLA médio'],
                    ['value' => '0', 'label' => 'Sem resposta > 1h'],
                ],
                [
                    ['ago' => 'há 3 min', 'type' => 'alerta', 'icon' => 'fa-heart-pulse', 'text' => 'Caso P1 aberto. dor 8/10 no questionário matinal'],
                    ['ago' => 'há 15 min', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Caso P2 encerrado após conduta de enfermagem'],
                    ['ago' => 'há 28 min', 'type' => 'sla', 'icon' => 'fa-stopwatch', 'text' => 'SLA médio P1 atualizado para 12 minutos'],
                    ['ago' => 'há 45 min', 'type' => 'triagem', 'icon' => 'fa-user-nurse', 'text' => 'Renata Oliveira assumiu triagem da fila crítica'],
                    ['ago' => 'há 1 h', 'type' => 'monitor', 'icon' => 'fa-chart-line', 'text' => 'Painel atualizado via Mercure. 2 casos ativos'],
                ],
            ),
            $this->hub(
                'carteirinha-digital',
                'Carteirinha digital',
                'Identidade com foto e validação.',
                'fa-id-card',
                'app_carteirinha_digital',
                'images/marketing/modules/mod-carteirinha.jpg',
                'Emita carteirinhas digitais com foto, QR e validação para beneficiários. Integração com portal do paciente e conformidade com LGPD.',
                ['Foto e QR de validação', 'Emissão em lote pela equipe', 'Download e compartilhamento seguro', 'Histórico de emissões auditável'],
                [
                    ['value' => '128', 'label' => 'Carteirinhas ativas'],
                    ['value' => '14', 'label' => 'Emitidas hoje'],
                    ['value' => '100%', 'label' => 'Validação OK'],
                ],
                [
                    ['ago' => 'há 9 min', 'type' => 'carteirinha', 'icon' => 'fa-id-card', 'text' => 'Carteirinha Premium emitida para beneficiário João Pereira'],
                    ['ago' => 'há 31 min', 'type' => 'validacao', 'icon' => 'fa-qrcode', 'text' => 'QR validado no portal do beneficiário. plano ativo'],
                    ['ago' => 'há 55 min', 'type' => 'portal', 'icon' => 'fa-mobile-screen', 'text' => 'Beneficiário baixou carteirinha pelo celular'],
                    ['ago' => 'há 1 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Lote de 8 carteirinhas processado sem pendências'],
                    ['ago' => 'há 3 h', 'type' => 'lgpd', 'icon' => 'fa-shield-halved', 'text' => 'Consentimento de imagem registrado para nova emissão'],
                ],
            ),
            $this->hub(
                'guia-medico',
                'Guia médico',
                'Orientações e sinais de alerta.',
                'fa-book-medical',
                'app_guia_medico_beneficiario',
                'images/marketing/modules/mod-guia.jpg',
                'Biblioteca de orientações pós-operatórias com sinais de alerta, medicamentos e retornos. Paciente e equipe consultam o mesmo conteúdo atualizado.',
                ['Orientações por procedimento', 'Sinais de alerta destacados', 'Versão para equipe e beneficiário', 'Atualização centralizada'],
                [
                    ['value' => '24', 'label' => 'Guias ativos'],
                    ['value' => '89%', 'label' => 'Leitura no portal'],
                    ['value' => '6', 'label' => 'Atualizações/mês'],
                ],
                [
                    ['ago' => 'há 11 min', 'type' => 'guia', 'icon' => 'fa-book-medical', 'text' => 'Guia «Pós-laparoscopia» consultado pelo portal do paciente'],
                    ['ago' => 'há 34 min', 'type' => 'alerta', 'icon' => 'fa-triangle-exclamation', 'text' => 'Sinais de alerta revisados pela equipe médica'],
                    ['ago' => 'há 1 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Nova versão do guia publicada para beneficiários'],
                    ['ago' => 'há 2 h', 'type' => 'medicamento', 'icon' => 'fa-pills', 'text' => 'Orientação de analgesia atualizada no protocolo D+3'],
                    ['ago' => 'há 4 h', 'type' => 'portal', 'icon' => 'fa-mobile-screen', 'text' => '18 acessos ao guia médico pelo portal hoje'],
                ],
            ),
            $this->hub(
                'alertas',
                'Alertas inteligentes',
                'Fila P1–P4, SLA e triagem clínica.',
                'fa-bell',
                'app_pos_operatorio_alertas',
                'images/marketing/modules/mod-alertas.jpg',
                'Fila clínica com priorização P1–P4, SLA configurável e triagem inteligente a partir dos questionários do paciente.',
                ['Priorização P1–P4 automática', 'SLA e escalonamento', 'Triagem por enfermagem e médico', 'Histórico auditável de condutas'],
                [
                    ['value' => '5', 'label' => 'Alertas abertos'],
                    ['value' => '2', 'label' => 'Em triagem'],
                    ['value' => '94%', 'label' => 'SLA cumprido'],
                ],
                [
                    ['ago' => 'há 4 min', 'type' => 'alerta', 'icon' => 'fa-bell', 'text' => 'Novo alerta P3. questionário com febre reportada'],
                    ['ago' => 'há 19 min', 'type' => 'triagem', 'icon' => 'fa-user-nurse', 'text' => 'Alerta P2 atribuído à enfermagem. contato telefônico'],
                    ['ago' => 'há 42 min', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Alerta P4 encerrado após orientação no portal'],
                    ['ago' => 'há 1 h', 'type' => 'sla', 'icon' => 'fa-stopwatch', 'text' => 'SLA P1 cumprido em 11 minutos na última ocorrência'],
                    ['ago' => 'há 2 h', 'type' => 'fila', 'icon' => 'fa-list', 'text' => 'Fila reorganizada. 2 casos elevados para P2'],
                ],
            ),
            $this->hub(
                'relatorios-lgpd',
                'Relatórios & LGPD',
                'Exportação CSV, auditoria e consentimento.',
                'fa-file-shield',
                'app_legal_lgpd',
                'images/marketing/modules/mod-lgpd.jpg',
                'Exportações clínicas, trilhas de auditoria e gestão de consentimentos em conformidade com a LGPD. Tudo rastreável para operadoras e clínicas.',
                ['Exportação CSV de indicadores', 'Trilha de auditoria por ação', 'Consentimentos e revogações', 'Relatórios para compliance'],
                [
                    ['value' => '100%', 'label' => 'Trilha auditável'],
                    ['value' => '3', 'label' => 'Exportações hoje'],
                    ['value' => '0', 'label' => 'Pendências LGPD'],
                ],
                [
                    ['ago' => 'há 20 min', 'type' => 'relatorio', 'icon' => 'fa-file-lines', 'text' => 'Relatório de indicadores clínicos exportado em CSV'],
                    ['ago' => 'há 38 min', 'type' => 'lgpd', 'icon' => 'fa-shield-halved', 'text' => 'Consentimento de dados registrado para novo paciente'],
                    ['ago' => 'há 1 h', 'type' => 'auditoria', 'icon' => 'fa-magnifying-glass', 'text' => 'Trilha de acesso consultada pela equipe de compliance'],
                    ['ago' => 'há 2 h', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Revisão mensal LGPD concluída sem pendências'],
                    ['ago' => 'há 4 h', 'type' => 'export', 'icon' => 'fa-download', 'text' => 'Exportação de alertas P1–P4 gerada para reunião de plantão'],
                ],
            ),
            $this->hub(
                'equipe-perfis',
                'Equipe clínica',
                'Recepção, Enfermagem, Médico e Coordenação.',
                'fa-user-shield',
                'app_pos_operatorio',
                'images/marketing/modules/mod-equipe.jpg',
                'Perfis clínicos reais: cada papel vê só o que precisa. Recepção cuida de cadastro e TISS; Enfermagem de triagem; Médico de alertas e protocolos; Coordenação de relatórios e config.',
                ['RECEPCAO · ENFERMAGEM · MEDICO · COORDENACAO', 'Home e menu filtrados por perfil', 'Rotas bloqueadas sem grant clínico', 'Sem misturar GESTOR legado no fluxo da clínica'],
                [
                    ['value' => '4', 'label' => 'Perfis clínicos'],
                    ['value' => '100%', 'label' => 'Menu filtrado'],
                    ['value' => '0', 'label' => 'Acesso cruzado'],
                ],
                [
                    ['ago' => 'há 6 min', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Camila (Recepção) abriu novo paciente e guia TISS'],
                    ['ago' => 'há 18 min', 'type' => 'triagem', 'icon' => 'fa-user-nurse', 'text' => 'Beatriz (Enfermagem) concluiu questionário D+3'],
                    ['ago' => 'há 40 min', 'type' => 'alerta', 'icon' => 'fa-user-doctor', 'text' => 'André (Médico) assumiu alerta P2 na fila'],
                    ['ago' => 'há 1 h', 'type' => 'relatorio', 'icon' => 'fa-chart-pie', 'text' => 'Helena (Coordenação) exportou indicadores do dia'],
                    ['ago' => 'há 3 h', 'type' => 'ok', 'icon' => 'fa-shield-halved', 'text' => 'Acesso a config bloqueado para perfil Médico'],
                ],
            ),
            $this->hub(
                'crm-comercial',
                'CRM Comercial',
                'Leads, pipeline, clientes e atividades.',
                'fa-handshake',
                'app_comercial',
                'images/marketing/modules/mod-crm.jpg',
                'Núcleo Comercial com pack CRM completo: captação de leads, kanban de oportunidades, contas, follow-ups e analytics de conversão no mesmo workspace da clínica.',
                ['Leads com origem e status', 'Pipeline kanban até ganho/perdido', 'Clientes e conversão de lead', 'Atividades e analytics de win rate'],
                [
                    ['value' => '18', 'label' => 'Leads no funil'],
                    ['value' => 'R$ 66k', 'label' => 'Pipeline aberto'],
                    ['value' => '42%', 'label' => 'Win rate'],
                ],
                [
                    ['ago' => 'há 5 min', 'type' => 'lead', 'icon' => 'fa-user-plus', 'text' => 'Novo lead qualificado — Marina Alves (Acme)'],
                    ['ago' => 'há 22 min', 'type' => 'pipeline', 'icon' => 'fa-diagram-project', 'text' => 'Oportunidade movida para Proposta'],
                    ['ago' => 'há 50 min', 'type' => 'ok', 'icon' => 'fa-check', 'text' => 'Lead convertido em cliente Grupo Horizonte'],
                    ['ago' => 'há 2 h', 'type' => 'atividade', 'icon' => 'fa-list-check', 'text' => 'Follow-up de demo agendado para amanhã'],
                    ['ago' => 'há 4 h', 'type' => 'analytics', 'icon' => 'fa-chart-pie', 'text' => 'Win rate da semana atualizado para 42%'],
                ],
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function commercialPlans(): array
    {
        return ClinicCommercialPlans::all();
    }

    /** @return list<array{id: string, label: string, icon: string}> */
    public function specialties(): array
    {
        return ClinicCommercialPlans::landingSpecialties();
    }

    /** @return array<string, mixed>|null */
    public function hubById(string $id): ?array
    {
        foreach ($this->hubs() as $hub) {
            if (($hub['id'] ?? '') === $id) {
                return $hub;
            }
        }

        return null;
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
}
