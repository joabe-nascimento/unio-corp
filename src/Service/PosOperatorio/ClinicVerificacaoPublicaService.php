<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
use App\PosOperatorio\PosOperatorioDisplay;
use App\Repository\PosOperatorioPacienteRepository;

final class ClinicVerificacaoPublicaService
{
    public const TIPO_CARTEIRINHA = 'carteirinha';
    public const TIPO_COMPROVANTE = 'comprovante';

    public function __construct(
        private PosOperatorioPacienteRepository $pacientes,
    ) {}

    /** @return array<string, mixed> */
    public function verificar(string $codigo): array
    {
        $resolved = $this->pacientes->resolveVerificacaoGlobal(strtoupper(trim($codigo)));
        if ($resolved === null) {
            return $this->payloadInexistente($codigo);
        }

        return $this->payloadFromPaciente($resolved['paciente'], $resolved['tipo'], $codigo);
    }

    /** @return array<string, mixed> */
    private function payloadFromPaciente(PosOperatorioPaciente $paciente, string $tipo, string $codigo): array
    {
        $ativo = $tipo === self::TIPO_CARTEIRINHA
            ? $paciente->hasCarteirinhaAtiva()
            : $paciente->hasComprovanteAtivo();

        $validoAte = $tipo === self::TIPO_CARTEIRINHA
            ? $paciente->getCarteirinhaValidaAte()
            : $paciente->getComprovanteValidoAte();

        $status = 'valida';
        if (!$ativo) {
            $status = ($validoAte !== null && $validoAte < new \DateTimeImmutable('today'))
                ? 'expirada'
                : 'revogada';
        }

        $nome = PosOperatorioDisplay::pacienteNome($paciente);

        return [
            'status' => $status,
            'tipo' => $tipo,
            'tipo_label' => $tipo === self::TIPO_CARTEIRINHA ? 'Carteirinha digital' : 'Comprovante de procedimento',
            'codigo' => strtoupper(trim($codigo)),
            'clinica' => $paciente->getEmpresa()->getNome(),
            'selo_unio' => true,
            'emitido_em' => $tipo === self::TIPO_CARTEIRINHA
                ? ($paciente->getCarteirinhaEmitidaEm()?->format('d/m/Y') ?? '—')
                : ($paciente->getComprovanteEmitidaEm()?->format('d/m/Y') ?? '—'),
            'hash_documento' => $tipo === self::TIPO_COMPROVANTE ? $paciente->getComprovanteHash() : null,
            'hash_curto' => $tipo === self::TIPO_COMPROVANTE && $paciente->getComprovanteHash()
                ? strtoupper(substr($paciente->getComprovanteHash(), 0, 12))
                : null,
            'paciente_resumido' => $this->nomeResumido($nome),
            'procedimento' => $paciente->getProcedimento() ?? ($paciente->getProtocolo()?->getNome() ?? '—'),
            'cirurgia' => $paciente->getDataCirurgia()?->format('d/m/Y') ?? '—',
            'medico' => $paciente->getMedicoResponsavel()?->getNome() ?? '—',
            'valido_ate' => $validoAte?->format('d/m/Y') ?? '—',
            'verificado_em' => (new \DateTimeImmutable())->format('d/m/Y H:i'),
            'acompanhamento_ativo' => \in_array($paciente->getStatus(), [
                PosOperatorioPaciente::STATUS_ATIVO,
                PosOperatorioPaciente::STATUS_ALERTA,
                PosOperatorioPaciente::STATUS_PENDENTE,
            ], true),
        ];
    }

    /** @return array<string, mixed> */
    private function payloadInexistente(string $codigo): array
    {
        return [
            'status' => 'inexistente',
            'tipo' => null,
            'tipo_label' => null,
            'codigo' => strtoupper(trim($codigo)),
            'clinica' => null,
            'paciente_resumido' => null,
            'procedimento' => null,
            'cirurgia' => null,
            'medico' => null,
            'valido_ate' => null,
            'verificado_em' => (new \DateTimeImmutable())->format('d/m/Y H:i'),
            'acompanhamento_ativo' => false,
        ];
    }

    private function nomeResumido(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        if ($partes === []) {
            return 'Paciente';
        }

        $primeiro = $partes[0];
        if (\count($partes) === 1) {
            return $primeiro;
        }

        return $primeiro . ' ' . mb_strtoupper(mb_substr($partes[\count($partes) - 1], 0, 1)) . '.';
    }
}
