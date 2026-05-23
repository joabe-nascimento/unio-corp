<?php

namespace App\Service;

/**
 * Notificações mock — substituir por persistência/API quando o backend estiver pronto.
 */
class NotificationMockService
{
    /**
     * @return list<array{id: string, title: string, body: string, type: string, icon: string, read: bool, time_label: string, route: ?string}>
     */
    public function getAll(): array
    {
        return [
            [
                'id' => 'n1',
                'title' => 'Admissão pendente de aprovação',
                'body' => 'João Silva aguarda revisão no módulo de Recursos Humanos.',
                'type' => 'warning',
                'icon' => 'fa-user-plus',
                'read' => false,
                'time_label' => 'Há 12 min',
                'route' => 'app_rh_admissoes',
            ],
            [
                'id' => 'n2',
                'title' => 'Férias aprovadas',
                'body' => 'Sua solicitação de férias para julho foi aprovada pelo gestor.',
                'type' => 'success',
                'icon' => 'fa-umbrella-beach',
                'read' => false,
                'time_label' => 'Há 1 h',
                'route' => 'app_rh_ferias',
            ],
            [
                'id' => 'n3',
                'title' => 'Novo membro na equipe',
                'body' => 'Maria Costa foi adicionada à equipe Comercial.',
                'type' => 'info',
                'icon' => 'fa-id-badge',
                'read' => false,
                'time_label' => 'Há 3 h',
                'route' => 'app_pessoas_membros',
            ],
            [
                'id' => 'n4',
                'title' => 'Relatório mensal disponível',
                'body' => 'O consolidado de RH de maio já pode ser consultado no dashboard.',
                'type' => 'info',
                'icon' => 'fa-chart-line',
                'read' => true,
                'time_label' => 'Ontem',
                'route' => 'app_dashboard',
            ],
            [
                'id' => 'n5',
                'title' => 'Prazo de avaliação se aproxima',
                'body' => 'Restam 5 dias para concluir as avaliações de desempenho do trimestre.',
                'type' => 'warning',
                'icon' => 'fa-star-half-stroke',
                'read' => true,
                'time_label' => 'Ontem',
                'route' => 'app_pessoas_avaliacao',
            ],
            [
                'id' => 'n6',
                'title' => 'Helix em breve',
                'body' => 'O assistente virtual Helix ganhará respostas automáticas em breve.',
                'type' => 'info',
                'icon' => 'fa-robot',
                'read' => true,
                'time_label' => 'Há 2 dias',
                'route' => null,
            ],
        ];
    }

    public function getUnreadCount(): int
    {
        return count(array_filter($this->getAll(), static fn (array $n): bool => !$n['read']));
    }
}
