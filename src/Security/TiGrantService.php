<?php

namespace App\Security;

use App\Entity\User;

/** Permissões finas do Núcleo TI por produto e nível de grant. */
final class TiGrantService
{
    public function __construct(private ProductGrantAccess $grants) {}

    public function view(User $user, string $product): bool
    {
        return $this->grants->canViewProductForUi($user, TiGrantPolicy::SCOPE, $product);
    }

    public function atLeast(User $user, string $product, string $minProfile): bool
    {
        if (!$this->view($user, $product)) {
            return false;
        }

        return $this->grants->grantAtLeast($user, TiGrantPolicy::SCOPE, $product, $minProfile);
    }

    public function canCreateChamado(User $user): bool
    {
        if ($user->isTenant()) {
            return true;
        }

        return $this->atLeast($user, 'meus_chamados', TiGrantPolicy::PORTAL_CHAMADO)
            || $this->atLeast($user, 'catalogo', TiGrantPolicy::PORTAL_CHAMADO)
            || $this->atLeast($user, 'chamados', TiGrantPolicy::OPERATE_CHAMADOS);
    }

    public function canOperateChamados(User $user): bool
    {
        return $this->atLeast($user, 'chamados', TiGrantPolicy::OPERATE_CHAMADOS);
    }

    public function canManageChamados(User $user): bool
    {
        return $this->atLeast($user, 'chamados', TiGrantPolicy::MANAGE_CHAMADOS);
    }

    public function canDeleteChamados(User $user): bool
    {
        return $this->atLeast($user, 'chamados', TiGrantPolicy::DELETE_CHAMADOS);
    }

    public function canViewChamado(User $user, array $ticket): bool
    {
        if ($this->canOperateChamados($user)) {
            return true;
        }

        if (!$this->atLeast($user, 'meus_chamados', TiGrantPolicy::PORTAL_CHAMADO)) {
            return false;
        }

        $requesterId = $ticket['requester_id'] ?? $ticket['solicitante_id'] ?? null;

        return $requesterId !== null && (int) $requesterId === $user->getId();
    }

    public function canReplyAsSolicitante(User $user, array $ticket): bool
    {
        if (!$this->isTicketRequester($user, $ticket)) {
            return false;
        }

        return ($ticket['status'] ?? '') !== \App\Entity\TiChamado::STATUS_RESOLVIDO;
    }

    public function canReopenChamado(User $user, array $ticket): bool
    {
        if (($ticket['status'] ?? '') !== \App\Entity\TiChamado::STATUS_RESOLVIDO) {
            return false;
        }

        if ($this->isTicketRequester($user, $ticket)) {
            return true;
        }

        return $this->canOperateChamados($user);
    }

    public function canRateCsat(User $user, array $ticket): bool
    {
        if (!$this->isTicketRequester($user, $ticket)) {
            return false;
        }

        if (($ticket['status'] ?? '') !== \App\Entity\TiChamado::STATUS_RESOLVIDO) {
            return false;
        }

        return ($ticket['csat_score'] ?? null) === null && ($ticket['csat_em'] ?? null) === null;
    }

    public function canManageKb(User $user): bool
    {
        return $this->atLeast($user, 'kb', TiGrantPolicy::MANAGE_KB);
    }

    public function canViewKb(User $user): bool
    {
        return $this->atLeast($user, 'kb', TiGrantPolicy::VIEW_KB);
    }

    public function canManageProblemas(User $user): bool
    {
        return $this->atLeast($user, 'problemas', TiGrantPolicy::MANAGE_PROBLEMAS);
    }

    public function canViewProblemas(User $user): bool
    {
        return $this->atLeast($user, 'problemas', TiGrantPolicy::VIEW_PROBLEMAS);
    }

    public function canLinkProblema(User $user): bool
    {
        return $this->canManageProblemas($user) || $this->canManageChamados($user);
    }

    public function canManageInfra(User $user, string $product): bool
    {
        return $this->atLeast($user, $product, TiGrantPolicy::MANAGE_INFRA);
    }

    public function canViewInfra(User $user, string $product): bool
    {
        return $this->atLeast($user, $product, TiGrantPolicy::VIEW_INFRA);
    }

    public function canManageManutencoes(User $user): bool
    {
        return $this->atLeast($user, 'manutencoes', TiGrantPolicy::MANAGE_MANUTENCOES);
    }

    public function canApproveManutencao(User $user): bool
    {
        return $this->atLeast($user, 'manutencoes', TiGrantPolicy::CONFIG);
    }

    public function canViewManutencoes(User $user): bool
    {
        return $this->atLeast($user, 'manutencoes', TiGrantPolicy::VIEW_OPS);
    }

    public function canManageCatalog(User $user): bool
    {
        return $this->atLeast($user, 'catalogo', TiGrantPolicy::MANAGE_INFRA);
    }

    public function canApplyHelia(User $user): bool
    {
        return $this->canOperateChamados($user)
            || $this->atLeast($user, 'cortex', TiGrantPolicy::VIEW_INTEL);
    }

    public function canPauseSla(User $user): bool
    {
        return $this->canManageChamados($user);
    }

    public function canExportAnalytics(User $user): bool
    {
        return $this->atLeast($user, 'analytics', TiGrantPolicy::MANAGE_INTEL);
    }

    public function canViewAnalytics(User $user): bool
    {
        return $this->atLeast($user, 'analytics', TiGrantPolicy::VIEW_INTEL);
    }

    public function canManageNovidades(User $user): bool
    {
        return $this->atLeast($user, 'novidades', TiGrantPolicy::MANAGE_NOVIDADES);
    }

    public function canViewNovidades(User $user): bool
    {
        return $this->view($user, 'novidades');
    }

    public function assert(User $user, bool $allowed, string $message = 'Sem permissão para esta ação.'): void
    {
        if (!$allowed && !$user->isTenant()) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException($message);
        }
    }

    private function isTicketRequester(User $user, array $ticket): bool
    {
        $requesterId = $ticket['requester_id'] ?? $ticket['solicitante_id'] ?? null;

        return $requesterId !== null && (int) $requesterId === $user->getId();
    }
}
