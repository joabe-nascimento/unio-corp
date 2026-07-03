<?php

namespace App\Message\PosOperatorio;

use App\Domain\Event\AbstractDomainEvent;

final class QuestionarioSubmetido extends AbstractDomainEvent
{
    public function __construct(
        public readonly int $questionarioRespostaId,
        public readonly int $pacienteId,
        public readonly int $empresaId,
        public readonly string $pacienteCodigo = '',
    ) {}

    public function eventName(): string
    {
        return 'pos_operatorio.questionario_submetido';
    }

    public function module(): string
    {
        return 'pos_operatorio';
    }

    public function payload(): array
    {
        return [
            'questionario_resposta_id' => $this->questionarioRespostaId,
            'paciente_id' => $this->pacienteId,
            'empresa_id' => $this->empresaId,
            'codigo' => $this->pacienteCodigo,
        ];
    }
}
