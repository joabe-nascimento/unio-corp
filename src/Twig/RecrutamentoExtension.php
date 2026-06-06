<?php

namespace App\Twig;

use App\Entity\RhCandidato;
use App\Entity\User;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhCandidatoOrigem;
use App\Rh\RhEntrevistaTipo;
use App\Rh\RhRecrutamentoDisplay;
use App\Rh\RhScorecardCriteria;
use App\Rh\RhVagaTipoContrato;
use App\Security\ProductGrantAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RecrutamentoExtension extends AbstractExtension
{
    public function __construct(
        private ProductGrantAccess $grants,
        private Security $security,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('recrutamento_nome', [$this, 'formatDisplayName']),
        ];
    }

    public function formatDisplayName(?string $name): string
    {
        if ($name === null || $name === '') {
            return '';
        }

        return RhRecrutamentoDisplay::formatNome($name);
    }

    public function entrevistaTitulo(RhCandidato $candidato): string
    {
        return RhRecrutamentoDisplay::entrevistaTitulo($candidato);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('recrutamento_etapa_label', [RhCandidatoEtapa::class, 'label']),
            new TwigFunction('recrutamento_etapa_badge_variant', [RhCandidatoEtapa::class, 'badgeVariant']),
            new TwigFunction('recrutamento_etapa_next', [RhCandidatoEtapa::class, 'next']),
            new TwigFunction('recrutamento_etapa_prev', [RhCandidatoEtapa::class, 'prev']),
            new TwigFunction('recrutamento_origem_label', [RhCandidatoOrigem::class, 'label']),
            new TwigFunction('recrutamento_origem_options', [RhCandidatoOrigem::class, 'options']),
            new TwigFunction('recrutamento_tipo_contrato_label', [RhVagaTipoContrato::class, 'label']),
            new TwigFunction('recrutamento_tipo_contrato_options', [RhVagaTipoContrato::class, 'options']),
            new TwigFunction('recrutamento_scorecard_criteria', [RhScorecardCriteria::class, 'forEtapa']),
            new TwigFunction('recrutamento_entrevista_tipo_options', [RhEntrevistaTipo::class, 'options']),
            new TwigFunction('recrutamento_entrevista_tipo_label', [RhEntrevistaTipo::class, 'label']),
            new TwigFunction('recrutamento_entrevista_titulo', [$this, 'entrevistaTitulo']),
            new TwigFunction('recrutamento_grant', [$this, 'canViewProduct']),
            new TwigFunction('recrutamento_grant_at_least', [$this, 'grantAtLeast']),
            new TwigFunction('recrutamento_has_products', [$this, 'hasAnyProduct']),
        ];
    }

    public function canViewProduct(string $product): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->canViewProductForUi($user, 'hub_recrutamento', $product)
            || $this->grants->canViewProductForUi($user, 'product_rh', 'recrutamento');
    }

    public function grantAtLeast(string $product, string $minProfileId): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->grantAtLeast($user, 'hub_recrutamento', $product, $minProfileId)
            || $this->grants->grantAtLeast($user, 'product_rh', 'recrutamento', $minProfileId);
    }

    public function hasAnyProduct(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->canViewAnyProductInScope($user, 'hub_recrutamento')
            || $this->grants->canViewProductForUi($user, 'product_rh', 'recrutamento');
    }
}
