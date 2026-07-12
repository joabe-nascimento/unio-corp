<?php

namespace App\Service\Clinic;

use App\Entity\ClinicDocumentoEmissao;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Trilha de auditoria para emissão, reemissão e revogação de documentos.
 */
final class ClinicDocumentoAuditService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    public function registrar(
        PosOperatorioPaciente $paciente,
        string $tipo,
        string $acao,
        string $codigoVerificacao,
        ?User $autor = null,
        ?string $plano = null,
        ?string $hashDocumento = null,
        array $meta = [],
    ): ClinicDocumentoEmissao {
        $entry = (new ClinicDocumentoEmissao())
            ->setEmpresa($paciente->getEmpresa())
            ->setPaciente($paciente)
            ->setEmitidoPor($autor)
            ->setTipo($tipo)
            ->setAcao($acao)
            ->setCodigoVerificacao($codigoVerificacao)
            ->setPlano($plano)
            ->setHashDocumento($hashDocumento)
            ->setMeta($meta);

        $this->em->persist($entry);
        $this->em->flush();

        return $entry;
    }

    public static function hashComprovante(PosOperatorioPaciente $paciente): string
    {
        $payload = implode('|', [
            $paciente->getId(),
            $paciente->getCodigo(),
            $paciente->getComprovanteVerificacao() ?? '',
            $paciente->getComprovanteEmitidaEm()?->format(\DateTimeInterface::ATOM) ?? '',
            $paciente->getProcedimento() ?? '',
            $paciente->getDataCirurgia()?->format('Y-m-d') ?? '',
        ]);

        return hash('sha256', $payload);
    }
}
