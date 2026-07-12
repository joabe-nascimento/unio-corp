<?php

namespace App\Service\Wallet;

use App\Wallet\WalletPassPayload;

/**
 * Gera link "Save to Google Wallet" (JWT).
 */
final class GoogleWalletPassBuilder
{
    private const SAVE_URL = 'https://pay.google.com/gp/v/save/';

    public function __construct(
        private ClinicWalletConfig $config,
        private JwtRs256Encoder $jwt,
    ) {}

    public function buildSaveUrl(WalletPassPayload $payload): string
    {
        if (!$this->config->isGoogleReady()) {
            throw new \RuntimeException('Google Wallet não configurado no servidor.');
        }

        $account = $this->config->googleServiceAccount();
        if ($account === null) {
            throw new \RuntimeException('Conta de serviço do Google Wallet inválida.');
        }

        $issuerId = $this->config->googleIssuerId();
        $classId = sprintf('%s.%s', $issuerId, $payload->type->googleClassSuffix());
        $objectId = sprintf('%s.%s', $issuerId, $payload->serialNumber);

        $claims = [
            'iss' => $account['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'payload' => [
                'genericClasses' => [$this->classDefinition($classId, $payload)],
                'genericObjects' => [$this->objectDefinition($classId, $objectId, $payload)],
            ],
        ];

        $origins = $this->config->googleOrigins();
        if ($origins !== []) {
            $claims['origins'] = $origins;
        }

        $token = $this->jwt->encode($claims, $account['private_key']);

        return self::SAVE_URL . $token;
    }

    /** @return array<string, mixed> */
    private function classDefinition(string $classId, WalletPassPayload $payload): array
    {
        return [
            'id' => $classId,
            'issuerName' => $payload->organizationName,
            'reviewStatus' => 'UNDER_REVIEW',
            'title' => $payload->type->label(),
            'hexBackgroundColor' => '#0f766e',
        ];
    }

    /** @return array<string, mixed> */
    private function objectDefinition(string $classId, string $objectId, WalletPassPayload $payload): array
    {
        return [
            'id' => $objectId,
            'classId' => $classId,
            'state' => 'ACTIVE',
            'cardTitle' => [
                'defaultValue' => [
                    'language' => 'pt-BR',
                    'value' => $payload->type->label(),
                ],
            ],
            'header' => [
                'defaultValue' => [
                    'language' => 'pt-BR',
                    'value' => $payload->patientName,
                ],
            ],
            'subheader' => [
                'defaultValue' => [
                    'language' => 'pt-BR',
                    'value' => $payload->procedureLabel,
                ],
            ],
            'textModulesData' => [
                [
                    'header' => 'Código de verificação',
                    'body' => $payload->verificationCode,
                    'id' => 'verification',
                ],
                [
                    'header' => 'Válido até',
                    'body' => $payload->validUntil,
                    'id' => 'valid_until',
                ],
                [
                    'header' => 'Médico',
                    'body' => $payload->doctorName,
                    'id' => 'doctor',
                ],
                [
                    'header' => 'Cirurgia',
                    'body' => $payload->surgeryDate,
                    'id' => 'surgery',
                ],
            ],
            'barcode' => [
                'type' => 'QR_CODE',
                'value' => $payload->verificationUrl,
                'alternateText' => $payload->verificationCode,
            ],
        ];
    }
}
