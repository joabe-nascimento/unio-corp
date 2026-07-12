<?php

namespace App\Service\PosOperatorio;

use App\Repository\PosOperatorioPacienteRepository;

final class ClinicVerificacaoCodigoGenerator
{
    public function __construct(
        private PosOperatorioPacienteRepository $pacientes,
    ) {}

    public function gerar(): string
    {
        for ($i = 0; $i < 12; ++$i) {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            if ($this->pacientes->findByAnyVerificacaoGlobal($code) === null) {
                return $code;
            }
        }

        throw new \RuntimeException('Não foi possível gerar código de verificação único.');
    }
}
