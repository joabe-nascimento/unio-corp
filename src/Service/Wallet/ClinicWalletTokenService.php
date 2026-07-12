<?php

namespace App\Service\Wallet;

use App\Entity\PosOperatorioPaciente;
use App\Wallet\WalletPassType;

/**
 * Token assinado para download de wallet pass sem sessão do beneficiário.
 */
final class ClinicWalletTokenService
{
    private const DEFAULT_TTL_SECONDS = 2_592_000; // 30 dias

    public function __construct(
        private string $kernelSecret,
    ) {}

    public function issue(PosOperatorioPaciente $paciente, WalletPassType $type, ?int $ttlSeconds = null): string
    {
        $ttlSeconds ??= self::DEFAULT_TTL_SECONDS;
        $exp = time() + max(3600, $ttlSeconds);
        $body = sprintf('%d:%s:%d', (int) $paciente->getId(), $type->value, $exp);
        $signature = hash_hmac('sha256', $body, $this->kernelSecret);

        return $this->encode($body . ':' . $signature);
    }

    /** @return array{patient_id: int, type: WalletPassType, exp: int}|null */
    public function resolve(string $token): ?array
    {
        $decoded = $this->decode($token);
        if ($decoded === null) {
            return null;
        }

        $parts = explode(':', $decoded, 4);
        if (count($parts) !== 4) {
            return null;
        }

        [$patientId, $typeValue, $exp, $signature] = $parts;
        if (!ctype_digit($patientId) || !ctype_digit($exp)) {
            return null;
        }

        $body = sprintf('%s:%s:%s', $patientId, $typeValue, $exp);
        $expected = hash_hmac('sha256', $body, $this->kernelSecret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $type = WalletPassType::tryFrom($typeValue);
        if ($type === null || (int) $exp < time()) {
            return null;
        }

        return [
            'patient_id' => (int) $patientId,
            'type' => $type,
            'exp' => (int) $exp,
        ];
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $padded = strtr($token, '-_', '+/');
        $mod = strlen($padded) % 4;
        if ($mod > 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
