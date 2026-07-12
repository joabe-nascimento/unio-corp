<?php

namespace App\Twig;

use App\Entity\PosOperatorioPaciente;
use App\Service\Wallet\ClinicWalletPassService;
use App\Wallet\WalletPassType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ClinicWalletTwigExtension extends AbstractExtension
{
    public function __construct(
        private ClinicWalletPassService $wallet,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('clinic_wallet_share', [$this, 'walletShare']),
            new TwigFunction('clinic_wallet_beneficiary', [$this, 'walletBeneficiary']),
            new TwigFunction('clinic_wallet_ready', [$this, 'walletReady']),
        ];
    }

    /** @return array<string, mixed> */
    public function walletShare(PosOperatorioPaciente $paciente, string $tipo): array
    {
        $type = WalletPassType::tryFromRoute($tipo);
        if ($type === null) {
            return ['enabled' => false, 'active' => false, 'apple' => ['ready' => false], 'google' => ['ready' => false]];
        }

        return $this->wallet->buildShareContext($paciente, $type);
    }

    /** @return array<string, mixed> */
    public function walletBeneficiary(string $tipo): array
    {
        $type = WalletPassType::tryFromRoute($tipo);
        if ($type === null) {
            return ['enabled' => false, 'apple' => ['ready' => false], 'google' => ['ready' => false]];
        }

        return $this->wallet->buildBeneficiaryContext($type);
    }

    public function walletReady(): bool
    {
        return $this->wallet->isAnyReady();
    }
}
