<?php

namespace App\Wallet;

/**
 * Dados normalizados para emissão de passes Apple/Google.
 */
final readonly class WalletPassPayload
{
    public function __construct(
        public WalletPassType $type,
        public int $patientId,
        public string $serialNumber,
        public string $organizationName,
        public string $patientName,
        public string $patientCode,
        public string $procedureLabel,
        public string $doctorName,
        public string $surgeryDate,
        public string $validUntil,
        public string $issuedAt,
        public string $verificationCode,
        public string $verificationUrl,
        public string $planLabel,
    ) {}
}
