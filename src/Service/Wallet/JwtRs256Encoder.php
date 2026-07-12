<?php

namespace App\Service\Wallet;

/**
 * JWT RS256 mínimo para Google Save to Wallet.
 */
final class JwtRs256Encoder
{
    /** @param array<string, mixed> $claims */
    public function encode(array $claims, string $privateKeyPem): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $input = $header . '.' . $payload;

        $signature = '';
        $ok = openssl_sign($input, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new \RuntimeException('Não foi possível assinar o JWT do Google Wallet.');
        }

        return $input . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
