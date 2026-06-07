<?php

namespace App\Twig;

use App\Entity\User;
use App\Security\TiGrantPolicy;
use App\Security\TiGrantService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TiPermissionsExtension extends AbstractExtension
{
    public function __construct(
        private TiGrantService $tiGrants,
        private Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ti_view', [$this, 'canView']),
            new TwigFunction('ti_can', [$this, 'can']),
            new TwigFunction('ti_at_least', [$this, 'atLeast']),
        ];
    }

    public function canView(string $product): bool
    {
        $user = $this->user();

        return $user !== null && $this->tiGrants->view($user, $product);
    }

    /** @param array<string, mixed> $context */
    public function can(string $action, array $context = []): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return match ($action) {
            'create_chamado' => $this->tiGrants->canCreateChamado($user),
            'operate_chamados' => $this->tiGrants->canOperateChamados($user),
            'manage_chamados' => $this->tiGrants->canManageChamados($user),
            'delete_chamados' => $this->tiGrants->canDeleteChamados($user),
            'manage_kb' => $this->tiGrants->canManageKb($user),
            'view_kb' => $this->tiGrants->canViewKb($user),
            'manage_problemas' => $this->tiGrants->canManageProblemas($user),
            'view_problemas' => $this->tiGrants->canViewProblemas($user),
            'link_problema' => $this->tiGrants->canLinkProblema($user),
            'manage_ativos' => $this->tiGrants->canManageInfra($user, 'ativos'),
            'manage_licencas' => $this->tiGrants->canManageInfra($user, 'licencas'),
            'manage_integracoes' => $this->tiGrants->canManageInfra($user, 'integracoes'),
            'manage_manutencoes' => $this->tiGrants->canManageManutencoes($user),
            'manutencao_approve' => $this->tiGrants->canApproveManutencao($user),
            'apply_helia' => $this->tiGrants->canApplyHelia($user),
            'pause_sla' => $this->tiGrants->canPauseSla($user),
            'export_analytics' => $this->tiGrants->canExportAnalytics($user),
            'manage_novidades' => $this->tiGrants->canManageNovidades($user),
            'manage_catalog' => $this->tiGrants->canManageCatalog($user),
            'view_chamado' => $this->tiGrants->canViewChamado($user, $context['ticket'] ?? []),
            'reply_chamado' => $this->tiGrants->canReplyAsSolicitante($user, $context['ticket'] ?? []),
            'reopen_chamado' => $this->tiGrants->canReopenChamado($user, $context['ticket'] ?? []),
            'rate_csat' => $this->tiGrants->canRateCsat($user, $context['ticket'] ?? []),
            default => false,
        };
    }

    public function atLeast(string $product, string $minProfile): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $this->tiGrants->atLeast($user, $product, $minProfile);
    }

    public function getPolicyLevels(): array
    {
        return [
            'MEMBRO' => TiGrantPolicy::PORTAL_CHAMADO,
            'SUPERVISOR_EQUIPE' => TiGrantPolicy::OPERATE_CHAMADOS,
            'SUPERVISOR' => TiGrantPolicy::MANAGE_CHAMADOS,
            'GESTOR_EQUIPE' => TiGrantPolicy::MANAGE_KB,
            'GESTOR' => TiGrantPolicy::CONFIG,
        ];
    }

    private function user(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
