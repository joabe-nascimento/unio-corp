<?php

namespace App\Service\Organismo;

use App\Entity\User;

/**
 * Rotas pós-login e home no modo Organismo.
 */
final class OrganismoRedirectService
{
    public function __construct(
        private OrganismoFeature $organismo,
    ) {
    }

    /** Rota após login (firewall → workspace). */
    public function afterLoginRoute(): string
    {
        return 'app_workspace_select';
    }

    /**
     * Rota após colônia definida (1 empresa auto-switch ou pós /workspace/switch).
     */
    public function afterWorkspaceRoute(User $user, int $empresaCount): string
    {
        if ($empresaCount === 0) {
            return 'app_dashboard';
        }

        if ($this->organismo->isPulsoHome()) {
            return 'app_pulso';
        }

        return 'app_welcome';
    }

    /** Home do breadcrumb / botões voltar. */
    public function homeRoute(): string
    {
        return $this->organismo->isPulsoHome() ? 'app_pulso' : 'app_dashboard';
    }
}
