<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicAgendamento;

/**
 * Token HMAC assinado para confirmação pública de agenda (sem migration).
 */
final class ClinicAgendaConfirmToken
{
    private const TTL_SECONDS = 7 * 24 * 3600;

    public function __construct(
        private string $appSecret,
    ) {}

    public function issue(ClinicAgendamento $agendamento, ?int $ttlSeconds = null): string
    {
        $id = (int) $agendamento->getId();
        $empresaId = (int) $agendamento->getEmpresa()->getId();
        $exp = time() + ($ttlSeconds ?? self::TTL_SECONDS);
        $payload = $id.'|'.$empresaId.'|'.$exp;
        $sig = hash_hmac('sha256', $payload, $this->secret());

        return rtrim(strtr(base64_encode($payload.'.'.$sig), '+/', '-_'), '=');
    }

    /**
     * @return array{agendamento_id: int, empresa_id: int}|null
     */
    public function parse(string $token): ?array
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || !str_contains($raw, '.')) {
            return null;
        }

        [$payload, $sig] = explode('.', $raw, 2);
        $expected = hash_hmac('sha256', $payload, $this->secret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $parts = explode('|', $payload);
        if (\count($parts) !== 3) {
            return null;
        }

        [$id, $empresaId, $exp] = $parts;
        if (!ctype_digit($id) || !ctype_digit($empresaId) || !ctype_digit($exp)) {
            return null;
        }
        if ((int) $exp < time()) {
            return null;
        }

        return [
            'agendamento_id' => (int) $id,
            'empresa_id' => (int) $empresaId,
        ];
    }

    private function secret(): string
    {
        $secret = trim($this->appSecret);

        return $secret !== '' ? $secret : 'unio-agenda-confirm-dev';
    }
}
