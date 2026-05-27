<?php

namespace App\Service\Rh;

use App\Entity\RhEsocialLote;

/**
 * Envio ao eSocial — implementação sandbox até integração com webservice oficial.
 */
final class RhEsocialGateway
{
  /**
   * @param array<string, mixed> $payload
   * @return array{ok: bool, protocolo?: string, erro?: string, resposta?: array<string, mixed>}
   */
    public function transmit(RhEsocialLote $lote, array $payload): array
    {
        $trabalhadores = (int) ($payload['trabalhadores'] ?? 0);
        if ($trabalhadores < 1) {
            return [
                'ok' => false,
                'erro' => 'Nenhum trabalhador ativo na competência para gerar o evento.',
            ];
        }

        $ref = $lote->getReferencia();
        if (!preg_match('/^\d{4}-\d{2}$/', $ref)) {
            return [
                'ok' => false,
                'erro' => 'Referência inválida (use AAAA-MM).',
            ];
        }

        // Simula rejeição esporádica para exercitar fila de retry.
        $id = $lote->getId();
        if ($lote->getTentativas() > 1 && $id !== null && $id % 7 === 0) {
            return [
                'ok' => false,
                'erro' => 'Rejeição simulada do ambiente de homologação (tente reprocessar).',
            ];
        }

        $protocolo = sprintf(
            'ESO-%s-%s-%s',
            str_replace('-', '', $ref),
            $lote->getTipoEvento(),
            strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
        );

        return [
            'ok' => true,
            'protocolo' => $protocolo,
            'resposta' => [
                'ambiente' => 'homologacao',
                'evento' => $lote->getTipoEvento(),
                'trabalhadores' => $trabalhadores,
                'processado_em' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
        ];
    }
}
