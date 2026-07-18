<?php

namespace App\Service\Security;

/**
 * Serviço simplificado para geração e validação de JWT
 * Usa apenas recursos nativos do PHP (não requer biblioteca externa)
 */
final class JwtService
{
    private const ALGORITHM = 'HS256';
    
    public function __construct(
        private string $secretKey,
        private int $expirationSeconds = 86400, // 24 horas
    ) {}

    /**
     * Gera um token JWT para um usuário
     */
    public function generateToken(int $userId, string $email, array $roles = []): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->expirationSeconds;

        $header = [
            'typ' => 'JWT',
            'alg' => self::ALGORITHM,
        ];

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'user_id' => $userId,
            'email' => $email,
            'roles' => $roles,
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $signature = $this->generateSignature($headerEncoded . '.' . $payloadEncoded);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    /**
     * Valida e decodifica um token JWT
     * 
     * @return array|null Retorna o payload se válido, null caso contrário
     */
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureProvided] = $parts;

        // Verificar assinatura
        $signatureExpected = $this->generateSignature($headerEncoded . '.' . $payloadEncoded);
        
        if (!hash_equals($signatureExpected, $signatureProvided)) {
            return null;
        }

        // Decodificar payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (!$payload) {
            return null;
        }

        // Verificar expiração
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Extrai o token do header Authorization
     */
    public function extractTokenFromAuthHeader(?string $authHeader): ?string
    {
        if (!$authHeader) {
            return null;
        }

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * Gera assinatura HMAC
     */
    private function generateSignature(string $data): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $data, $this->secretKey, true)
        );
    }

    /**
     * Codifica em Base64 URL-safe
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Decodifica de Base64 URL-safe
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Decodifica um token sem validar (útil para debug)
     */
    public function decodeWithoutValidation(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        
        return $payload ?: null;
    }

    /**
     * Verifica se o token está expirado
     */
    public function isExpired(string $token): bool
    {
        $payload = $this->decodeWithoutValidation($token);
        
        if (!$payload || !isset($payload['exp'])) {
            return true;
        }

        return $payload['exp'] < time();
    }

    /**
     * Retorna o tempo restante até expiração (em segundos)
     */
    public function getTimeToExpiration(string $token): ?int
    {
        $payload = $this->decodeWithoutValidation($token);
        
        if (!$payload || !isset($payload['exp'])) {
            return null;
        }

        $remaining = $payload['exp'] - time();
        
        return max(0, $remaining);
    }
}
