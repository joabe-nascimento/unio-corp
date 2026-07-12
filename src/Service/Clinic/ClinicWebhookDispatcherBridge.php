<?php

namespace App\Service\Clinic;

use App\Entity\ClinicCheckin;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Service\PosOperatorio\ClinicWebhookDispatcher;

/**
 * Ponte para webhooks clínicos sem acoplamento circular.
 */
final class ClinicWebhookDispatcherBridge
{
    public function __construct(
        private ClinicWebhookDispatcher $dispatcher,
    ) {}

    public function checkinRealizado(Empresa $empresa, PosOperatorioPaciente $paciente, ClinicCheckin $checkin): void
    {
        $this->dispatcher->dispatch($empresa, 'checkin.realizado', [
            'paciente_id' => $paciente->getId(),
            'codigo' => $paciente->getCodigo(),
            'metodo' => $checkin->getMetodo(),
            'checkin_id' => $checkin->getId(),
        ]);
    }

    /** @param array<string, mixed> $extra */
    public function documentoEmitido(Empresa $empresa, string $tipo, PosOperatorioPaciente $paciente, array $extra = []): void
    {
        $event = $tipo === 'carteirinha' ? 'carteirinha.emitida' : 'comprovante.emitido';
        $this->dispatcher->dispatch($empresa, $event, array_merge([
            'paciente_id' => $paciente->getId(),
            'codigo' => $paciente->getCodigo(),
        ], $extra));
    }

    public function verificacaoSucesso(Empresa $empresa, string $codigo, string $tipo): void
    {
        $this->dispatcher->dispatch($empresa, 'verificacao.sucesso', [
            'codigo' => $codigo,
            'tipo' => $tipo,
        ]);
    }
}
