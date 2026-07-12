<?php

namespace App\Service\Clinic;

use App\Entity\Empresa;
use App\PosOperatorio\ClinicCommercialPlans;
use App\PosOperatorio\ClinicProductCatalog;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\ClinicConfigStore;
use App\Service\PosOperatorio\ClinicProductConfigService;
use App\Service\Wallet\ClinicWalletConfig;

/**
 * Planos comerciais, limites de beneficiários e add-ons (wallet).
 */
final class ClinicPlanLimitsService
{
    /** IDs legados → planos públicos atuais. */
    private const LEGACY_PLAN_MAP = [
        'profissional' => ClinicCommercialPlans::CLINICA,
        'premium' => ClinicCommercialPlans::REDE,
    ];

    public function __construct(
        private ClinicConfigStore $configStore,
        private ClinicProductConfigService $productConfig,
        private PosOperatorioPacienteRepository $pacientes,
        private ClinicWalletConfig $walletConfig,
    ) {}

    /** @return array<string, mixed> */
    public function get(Empresa $empresa): array
    {
        $stored = $this->configStore->read($empresa, 'planos_limites');
        $merged = array_merge($this->defaults(), $stored);
        $merged['plano_comercial'] = $this->normalizePlanId((string) ($merged['plano_comercial'] ?? ClinicCommercialPlans::defaultPlanId()));

        return $merged;
    }

    /** @param array<string, mixed> $data */
    public function save(Empresa $empresa, array $data): void
    {
        if (isset($data['plano_comercial'])) {
            $data['plano_comercial'] = $this->normalizePlanId((string) $data['plano_comercial']);
            $plan = ClinicCommercialPlans::find($data['plano_comercial']);
            if ($plan !== null && !isset($data['max_beneficiarios'])) {
                $data['max_beneficiarios'] = (int) $plan['max_beneficiarios'];
            }
        }

        $merged = array_merge($this->get($empresa), $data);
        $this->configStore->write($empresa, 'planos_limites', $merged);
    }

    public function canAddBeneficiario(Empresa $empresa): bool
    {
        $limits = $this->get($empresa);
        $max = (int) ($limits['max_beneficiarios'] ?? 500);
        $count = $this->pacientes->countActiveByEmpresa($empresa);

        return $count < $max;
    }

    public function walletEnabledForEmpresa(Empresa $empresa): bool
    {
        $limits = $this->get($empresa);
        if (!($limits['wallet_incluso'] ?? false)) {
            return false;
        }

        return $this->walletConfig->isAnyReady();
    }

    public function isProductAllowed(Empresa $empresa, string $productId): bool
    {
        $enabled = $this->productConfig->enabledMap($empresa);

        return $enabled[$productId] ?? true;
    }

    /** @return array<string, mixed> */
    public function usageSummary(Empresa $empresa): array
    {
        $limits = $this->get($empresa);

        return [
            'plano_comercial' => (string) ($limits['plano_comercial'] ?? ClinicCommercialPlans::defaultPlanId()),
            'max_beneficiarios' => (int) ($limits['max_beneficiarios'] ?? 500),
            'beneficiarios_ativos' => $this->pacientes->countActiveByEmpresa($empresa),
            'wallet_incluso' => (bool) ($limits['wallet_incluso'] ?? false),
            'wallet_ativo' => $this->walletEnabledForEmpresa($empresa),
            'produtos' => $this->productConfig->enabledMap($empresa),
        ];
    }

    public function normalizePlanId(string $id): string
    {
        $id = strtolower(trim($id));
        if (isset(self::LEGACY_PLAN_MAP[$id])) {
            return self::LEGACY_PLAN_MAP[$id];
        }

        return ClinicCommercialPlans::find($id) !== null
            ? $id
            : ClinicCommercialPlans::defaultPlanId();
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        $products = [];
        foreach (ClinicProductCatalog::all() as $product) {
            $products[(string) $product['id']] = (bool) ($product['default_enabled'] ?? true);
        }

        $defaultPlan = ClinicCommercialPlans::find(ClinicCommercialPlans::defaultPlanId());

        return [
            'plano_comercial' => ClinicCommercialPlans::defaultPlanId(),
            'max_beneficiarios' => (int) ($defaultPlan['max_beneficiarios'] ?? 500),
            'wallet_incluso' => false,
            'produtos_override' => $products,
        ];
    }
}
