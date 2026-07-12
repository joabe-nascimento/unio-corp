<?php

namespace App\Service\Clinic;

use App\Entity\Empresa;
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

        return array_merge($this->defaults(), $stored);
    }

    /** @param array<string, mixed> $data */
    public function save(Empresa $empresa, array $data): void
    {
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
            'plano_comercial' => (string) ($limits['plano_comercial'] ?? 'profissional'),
            'max_beneficiarios' => (int) ($limits['max_beneficiarios'] ?? 500),
            'beneficiarios_ativos' => $this->pacientes->countActiveByEmpresa($empresa),
            'wallet_incluso' => (bool) ($limits['wallet_incluso'] ?? false),
            'wallet_ativo' => $this->walletEnabledForEmpresa($empresa),
            'produtos' => $this->productConfig->enabledMap($empresa),
        ];
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        $products = [];
        foreach (ClinicProductCatalog::all() as $product) {
            $products[(string) $product['id']] = (bool) ($product['default_enabled'] ?? true);
        }

        return [
            'plano_comercial' => 'profissional',
            'max_beneficiarios' => 500,
            'wallet_incluso' => false,
            'produtos_override' => $products,
        ];
    }
}
