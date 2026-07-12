<?php

namespace App\Service\Wallet;

use App\Entity\PosOperatorioPaciente;
use App\PosOperatorio\PosOperatorioDisplay;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\Clinic\ClinicPlatformScope;
use App\Service\PosOperatorio\ClinicCarteirinhaService;
use App\Wallet\WalletPassPayload;
use App\Wallet\WalletPassType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Orquestra emissão de passes e URLs de compartilhamento.
 */
final class ClinicWalletPassService
{
    public function __construct(
        private ClinicWalletConfig $config,
        private ClinicWalletTokenService $tokens,
        private AppleWalletPassBuilder $apple,
        private GoogleWalletPassBuilder $google,
        private PosOperatorioPacienteRepository $pacientes,
        private UrlGeneratorInterface $urlGenerator,
        private ClinicPlatformScope $clinicScope,
    ) {}

    public function isClinicScope(): bool
    {
        return $this->clinicScope->isActive();
    }

    public function isAnyReady(): bool
    {
        return $this->isClinicScope() && $this->config->isAnyReady();
    }

    public function isAppleReady(): bool
    {
        return $this->isClinicScope() && $this->config->isAppleReady();
    }

    public function isGoogleReady(): bool
    {
        return $this->isClinicScope() && $this->config->isGoogleReady();
    }

    public function patientHasActiveDocument(PosOperatorioPaciente $paciente, WalletPassType $type): bool
    {
        return match ($type) {
            WalletPassType::Carteirinha => $paciente->hasCarteirinhaAtiva(),
            WalletPassType::Comprovante => $paciente->hasComprovanteAtivo(),
        };
    }

    public function buildPayload(PosOperatorioPaciente $paciente, WalletPassType $type): WalletPassPayload
    {
        if (!$this->patientHasActiveDocument($paciente, $type)) {
            throw new \RuntimeException('Documento inativo ou revogado.');
        }

        $empresa = $paciente->getEmpresa();
        $nome = PosOperatorioDisplay::pacienteNome($paciente);
        $codigo = match ($type) {
            WalletPassType::Carteirinha => (string) $paciente->getCarteirinhaVerificacao(),
            WalletPassType::Comprovante => (string) $paciente->getComprovanteVerificacao(),
        };
        $validoAte = match ($type) {
            WalletPassType::Carteirinha => $paciente->getCarteirinhaValidaAte(),
            WalletPassType::Comprovante => $paciente->getComprovanteValidoAte(),
        };
        $emitidoEm = match ($type) {
            WalletPassType::Carteirinha => $paciente->getCarteirinhaEmitidaEm(),
            WalletPassType::Comprovante => $paciente->getComprovanteEmitidaEm(),
        };

        $verificationUrl = $this->urlGenerator->generate(
            'app_verificar_documento',
            ['codigo' => $codigo],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $plano = $paciente->getCarteirinhaPlano() ?? 'essencial';

        return new WalletPassPayload(
            type: $type,
            patientId: (int) $paciente->getId(),
            serialNumber: sprintf('unio-%s-%d-%s', $type->value, (int) $paciente->getId(), strtolower($codigo)),
            organizationName: mb_strtoupper($empresa->getNome()),
            patientName: $nome,
            patientCode: (string) $paciente->getCodigo(),
            procedureLabel: $paciente->getProcedimento() ?? ($paciente->getProtocolo()?->getNome() ?? 'Procedimento'),
            doctorName: $paciente->getMedicoResponsavel()?->getNome() ?? '—',
            surgeryDate: $paciente->getDataCirurgia()?->format('d/m/Y') ?? '—',
            validUntil: $validoAte?->format('d/m/Y') ?? '—',
            issuedAt: $emitidoEm?->format('d/m/Y') ?? '—',
            verificationCode: $codigo,
            verificationUrl: $verificationUrl,
            planLabel: ClinicCarteirinhaService::planoLabelFor($plano),
        );
    }

    public function buildApplePkpass(WalletPassPayload $payload): string
    {
        return $this->apple->build($payload);
    }

    public function buildGoogleSaveUrl(WalletPassPayload $payload): string
    {
        return $this->google->buildSaveUrl($payload);
    }

    public function findPatientForToken(string $token): ?array
    {
        $resolved = $this->tokens->resolve($token);
        if ($resolved === null) {
            return null;
        }

        $paciente = $this->pacientes->find($resolved['patient_id']);
        if ($paciente === null || !$this->patientHasActiveDocument($paciente, $resolved['type'])) {
            return null;
        }

        return [
            'paciente' => $paciente,
            'type' => $resolved['type'],
        ];
    }

    /** @return array<string, mixed> */
    public function buildShareContext(PosOperatorioPaciente $paciente, WalletPassType $type): array
    {
        if (!$this->isClinicScope()) {
            return $this->disabledShareContext();
        }

        $active = $this->patientHasActiveDocument($paciente, $type);
        $token = $active ? $this->tokens->issue($paciente, $type) : null;

        return [
            'enabled' => $active && $this->isAnyReady(),
            'active' => $active,
            'apple' => [
                'ready' => $active && $this->isAppleReady(),
                'url' => $token !== null && $this->isAppleReady()
                    ? $this->urlGenerator->generate('app_wallet_apple', [
                        'tipo' => $type->value,
                        'token' => $token,
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                    : null,
            ],
            'google' => [
                'ready' => $active && $this->isGoogleReady(),
                'url' => $token !== null && $this->isGoogleReady()
                    ? $this->urlGenerator->generate('app_wallet_google', [
                        'tipo' => $type->value,
                        'token' => $token,
                    ], UrlGeneratorInterface::ABSOLUTE_URL)
                    : null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function buildBeneficiaryContext(WalletPassType $type): array
    {
        if (!$this->isClinicScope()) {
            return ['enabled' => false, 'apple' => ['ready' => false], 'google' => ['ready' => false]];
        }

        return [
            'enabled' => $this->isAnyReady(),
            'apple' => [
                'ready' => $this->isAppleReady(),
                'route' => match ($type) {
                    WalletPassType::Carteirinha => 'app_carteirinha_wallet_apple',
                    WalletPassType::Comprovante => 'app_comprovante_wallet_apple',
                },
            ],
            'google' => [
                'ready' => $this->isGoogleReady(),
                'route' => match ($type) {
                    WalletPassType::Carteirinha => 'app_carteirinha_wallet_google',
                    WalletPassType::Comprovante => 'app_comprovante_wallet_google',
                },
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function disabledShareContext(): array
    {
        return [
            'enabled' => false,
            'active' => false,
            'apple' => ['ready' => false, 'url' => null],
            'google' => ['ready' => false, 'url' => null],
        ];
    }
}
