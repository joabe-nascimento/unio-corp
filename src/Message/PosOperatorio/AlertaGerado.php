<?php

namespace App\Message\PosOperatorio;

use App\Domain\Event\AbstractDomainEvent;

final class AlertaGerado extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $alertaId,
        public readonly int $empresaId,
        public readonly string $prioridade = 'P3',
        public readonly string $pacienteCodigo = '',
        public readonly string $motivo = '',
    ) {}

    public function eventName(): string
    {
        return 'pos_operatorio.alerta_gerado';
    }

    public function module(): string
    {
        return 'pos_operatorio';
    }

    public function payload(): array
    {
        return [
            'alerta_id' => $this->alertaId,
            'empresa_id' => $this->empresaId,
            'prioridade' => $this->prioridade,
            'codigo' => $this->pacienteCodigo,
            'motivo' => $this->motivo,
        ];
    }
}
