<?php

namespace App\Service\Organismo;

use App\Clinic\ClinicStaffRole;
use App\Entity\User;

/**
 * Rotas pós-login e home no modo Organismo.
 */
final class OrganismoRedirectService
{
    public function __construct(
        private OrganismoFeature $organismo,
        private OrganismoCopyService $copy,
    ) {
    }

    /** Rota após login (firewall). */
    public function afterLoginRoute(?User $user = null): string
    {
        if ($this->organismo->isEnabled()) {
            if ($user !== null) {
                $roleHome = $this->clinicHomeRoute($user);
                if ($roleHome !== null) {
                    return $roleHome;
                }
            }

            return $this->organismo->isPulsoHome() ? 'app_pulso' : 'app_welcome';
        }

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

        $roleHome = $this->clinicHomeRoute($user);
        if ($roleHome !== null) {
            return $roleHome;
        }

        if ($this->organismo->isPulsoHome()) {
            return 'app_pulso';
        }

        return 'app_welcome';
    }

    /** Home do breadcrumb / botões voltar. */
    public function homeRoute(?User $user = null): string
    {
        if ($user !== null) {
            $roleHome = $this->clinicHomeRoute($user);
            if ($roleHome !== null) {
                return $roleHome;
            }
        }

        return $this->organismo->isPulsoHome() ? 'app_pulso' : 'app_dashboard';
    }

    private function clinicHomeRoute(User $user): ?string
    {
        if (!$this->copy->isClinicProfile() || $user->hasPlatformAccess()) {
            return null;
        }

        return ClinicStaffRole::homeRoute($user->getPerfil());
    }
}
