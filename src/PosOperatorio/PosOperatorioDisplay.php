<?php

namespace App\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
use App\Rh\RhProcessDisplay;

/** Rótulos de exibição para pacientes e status clínicos. */
final class PosOperatorioDisplay
{
    /** @var array<string, string> */
    private const STATUS_LABELS = [
        PosOperatorioPaciente::STATUS_ATIVO => 'Ativo',
        PosOperatorioPaciente::STATUS_PENDENTE => 'Pendente',
        PosOperatorioPaciente::STATUS_ALERTA => 'Em alerta',
        PosOperatorioPaciente::STATUS_ENCERRADO => 'Encerrado',
    ];

    public static function pacienteNome(PosOperatorioPaciente $paciente): string
    {
        $nome = trim($paciente->getNome());
        if ($nome !== '' && !self::isInvalidPatientName($nome)) {
            return $nome;
        }

        $portal = $paciente->getPortalUser();
        $fromEmail = RhProcessDisplay::nomeFromEmail($portal?->getEmail());
        if ($fromEmail !== null) {
            return $fromEmail;
        }

        $codigo = trim($paciente->getCodigo());

        return $codigo !== '' ? 'Paciente ' . $codigo : 'Paciente';
    }

    public static function isInvalidPatientName(string $nome): bool
    {
        $trimmed = trim($nome);
        if ($trimmed === '') {
            return true;
        }

        return RhProcessDisplay::isGenericHubName($trimmed);
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? ucfirst($status);
    }
}
