<?php

namespace App\Service;

use App\Entity\User;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Saudação, hubs da tela de boas-vindas e destaques de novidades.
 * Ao adicionar hub/módulo novo, inclua em NOVIDADES ou marque is_new no hub.
 */
class WelcomeService
{
    private const TZ = 'America/Sao_Paulo';

    /** Destaques exibidos na tela de boas-vindas (is_new = true aparece na seção Novidades). */
    private const NOVIDADES = [
        [
            'id' => 'membros_equipes',
            'title' => 'Membros e Equipes',
            'description' => 'Ficha técnica, fotos e gestão de equipes no Hub Operações.',
            'route' => 'app_pessoas_membros',
            'icon' => 'fa-user-group',
            'is_new' => true,
            'check' => 'hub_operacoes',
        ],
        [
            'id' => 'engenharia',
            'title' => 'Obras e Projetos',
            'description' => 'Engenharia civil integrada ao Hub Operações.',
            'route' => 'app_engenharia',
            'icon' => 'fa-hard-hat',
            'is_new' => true,
            'check' => 'modulo_engenharia',
        ],
        [
            'id' => 'publicidade',
            'title' => 'Marca e Comunicação',
            'description' => 'Campanhas e materiais no Hub de Maturidade.',
            'route' => 'app_publicidade',
            'icon' => 'fa-bullhorn',
            'is_new' => true,
            'check' => 'modulo_publicidade',
        ],
    ];

    public function __construct(private NavigationService $navigation) {}

    public function getGreeting(): string
    {
        $hour = (int) (new DateTimeImmutable('now', new DateTimeZone(self::TZ)))->format('G');

        return match (true) {
            $hour >= 5 && $hour < 12 => 'Bom dia',
            $hour >= 12 && $hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        };
    }

    /** @return array{datetime: DateTimeImmutable, date_label: string, time_label: string, weekday: string} */
    public function getDateTimeInfo(): array
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone(self::TZ));
        $weekdays = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

        return [
            'datetime' => $dt,
            'date_label' => $dt->format('d/m/Y'),
            'time_label' => $dt->format('H:i'),
            'weekday' => $weekdays[(int) $dt->format('w')],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getHubsForUser(User $user): array
    {
        $hubs = [];

        if ($this->navigation->showHubOperacoes($user)) {
            $hubs[] = [
                'id' => 'operacoes',
                'title' => 'Hub Operações',
                'subtitle' => 'RH, Gestão de Pessoas e operações do dia a dia',
                'icon' => 'fa-briefcase',
                'route' => 'app_hub_operacoes',
                'is_new' => false,
            ];
        }

        if ($this->navigation->showHubTalentos($user)) {
            $hubs[] = [
                'id' => 'talentos',
                'title' => 'Hub de Talentos',
                'subtitle' => 'Banco de talentos, vagas e trilhas de desenvolvimento',
                'icon' => 'fa-gem',
                'route' => 'app_talentos',
                'is_new' => false,
            ];
        }

        if ($this->navigation->showHubMaturidade($user)) {
            $hubs[] = [
                'id' => 'maturidade',
                'title' => 'Hub de Maturidade',
                'subtitle' => 'Radar organizacional, plano de ação e evolução',
                'icon' => 'fa-gauge-high',
                'route' => 'app_maturidade',
                'is_new' => false,
            ];
        }

        if ($this->navigation->showPlataforma($user)) {
            $hubs[] = [
                'id' => 'plataforma',
                'title' => 'Plataforma',
                'subtitle' => 'Usuários, empresas e configurações globais',
                'icon' => 'fa-shield-halved',
                'route' => 'app_admin',
                'is_new' => false,
            ];
        }

        return $hubs;
    }

    /** @return list<array<string, mixed>> */
    public function getNovidadesForUser(User $user): array
    {
        $items = [];

        foreach (self::NOVIDADES as $item) {
            if (!$item['is_new'] || !$this->canShowNovidade($user, $item['check'])) {
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }

    /** @return list<array{id: string, label: string, icon: string, route: string, primary?: bool}> */
    public function getQuickActionsForUser(User $user): array
    {
        $actions = [
            [
                'id' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'fa-gauge-high',
                'route' => 'app_dashboard',
                'primary' => true,
            ],
        ];

        if ($this->navigation->showHubOperacoes($user)) {
            $actions[] = [
                'id' => 'operacoes',
                'label' => 'Operações',
                'icon' => 'fa-briefcase',
                'route' => 'app_hub_operacoes',
            ];
        }

        if ($this->navigation->showHubTalentos($user)) {
            $actions[] = [
                'id' => 'talentos',
                'label' => 'Talentos',
                'icon' => 'fa-gem',
                'route' => 'app_talentos',
            ];
        }

        $actions[] = [
            'id' => 'profile',
            'label' => 'Meu perfil',
            'icon' => 'fa-user-circle',
            'route' => 'app_profile',
        ];

        return \array_slice($actions, 0, 5);
    }

    /** @return array{hub_count: int, novidade_count: int} */
    public function getWelcomeSnapshot(User $user): array
    {
        $hubs = $this->getHubsForUser($user);
        $novidades = $this->getNovidadesForUser($user);

        return [
            'hub_count' => \count($hubs),
            'novidade_count' => \count($novidades),
        ];
    }

    private function canShowNovidade(User $user, string $check): bool
    {
        return match ($check) {
            'hub_operacoes' => $this->navigation->showHubOperacoes($user),
            'modulo_engenharia' => $this->navigation->showModuloEngenharia($user),
            'modulo_publicidade' => $this->navigation->showModuloPublicidade($user),
            'hub_talentos' => $this->navigation->showHubTalentos($user),
            'hub_maturidade' => $this->navigation->showHubMaturidade($user),
            default => false,
        };
    }
}
