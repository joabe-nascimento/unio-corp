<?php

namespace App\Service;

/**
 * Resolve a rota "voltar" para telas filhas de um hub (RH, Pessoas, Engenharia, etc.).
 */
final class PageBackResolver
{
    /** @var array<string, string> hub index route => parent hub route */
    private const HUB_PARENT = [
        'app_rh' => 'app_hub_operacoes',
        'app_pessoas' => 'app_hub_operacoes',
        'app_engenharia' => 'app_hub_operacoes',
        'app_talentos' => 'app_talentos',
        'app_publicidade' => 'app_maturidade',
        'app_admin' => 'app_dashboard',
    ];

    /** segmento da rota de detalhe => rota da listagem */
    private const RH_LIST_PARENT = [
        'funcionario' => 'app_rh_funcionarios',
        'admissoes' => 'app_rh_admissoes',
        'demissoes' => 'app_rh_demissoes',
        'ferias' => 'app_rh_ferias',
        'folha' => 'app_rh_folha',
    ];

    /**
     * @return array{route: string, params: array<string, mixed>}|null
     */
    public function resolve(?string $currentRoute): ?array
    {
        if ($currentRoute === null || $currentRoute === '') {
            return null;
        }

        if (isset(self::HUB_PARENT[$currentRoute])) {
            return ['route' => self::HUB_PARENT[$currentRoute], 'params' => []];
        }

        if (str_starts_with($currentRoute, 'app_rh_portal')) {
            if ($currentRoute === 'app_rh_portal') {
                return ['route' => 'app_rh', 'params' => []];
            }

            return ['route' => 'app_rh_portal', 'params' => []];
        }

        if (str_starts_with($currentRoute, 'app_rh_')) {
            return $this->resolveRh($currentRoute);
        }

        if (str_starts_with($currentRoute, 'app_admin')) {
            return null;
        }

        if (str_starts_with($currentRoute, 'app_pessoas_')) {
            return $this->resolvePrefixedHub('app_pessoas', $currentRoute, [
                'membro' => 'app_pessoas_membros',
                'equipe' => 'app_pessoas_equipes',
            ]);
        }

        if (str_starts_with($currentRoute, 'app_engenharia_')) {
            return $this->resolvePrefixedHub('app_engenharia', $currentRoute, []);
        }

        return null;
    }

    /**
     * @return array{route: string, params: array<string, mixed>}|null
     */
    private function resolveRh(string $route): ?array
    {
        if ($route === 'app_rh_esocial_retry') {
            return ['route' => 'app_rh_esocial', 'params' => []];
        }

        if (preg_match('/^app_rh_(.+?)_(nova|show|editar|gerar|export|candidato)$/', $route, $m)) {
            $parent = self::RH_LIST_PARENT[$m[1]] ?? null;
            if ($parent !== null) {
                return ['route' => $parent, 'params' => []];
            }
        }

        if ($route !== 'app_rh') {
            return ['route' => 'app_rh', 'params' => []];
        }

        return null;
    }

    /**
     * @param array<string, string> $detailParents
     *
     * @return array{route: string, params: array<string, mixed>}|null
     */
    private function resolvePrefixedHub(string $hubRoute, string $route, array $detailParents): ?array
    {
        $prefix = $hubRoute . '_';

        if (preg_match('/^' . preg_quote($prefix, '/') . '(.+?)_(show|nova|editar|form)$/', $route, $m)) {
            $parent = $detailParents[$m[1]] ?? null;
            if ($parent !== null) {
                return ['route' => $parent, 'params' => []];
            }
        }

        if ($route !== $hubRoute) {
            return ['route' => $hubRoute, 'params' => []];
        }

        return null;
    }
}
