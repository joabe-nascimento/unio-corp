<?php

namespace App\Service\Clinic;

use App\Entity\ClinicVerificacaoLog;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Repository\PosOperatorioPacienteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ClinicVerificacaoAuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PosOperatorioPacienteRepository $pacientes,
    ) {}

    /** @param array<string, mixed> $resultado */
    public function log(Request $request, string $codigo, array $resultado, string $origem = 'publico'): void
    {
        $resolved = $this->pacientes->resolveVerificacaoGlobal(strtoupper(trim($codigo)));

        $log = (new ClinicVerificacaoLog())
            ->setCodigo(strtoupper(trim($codigo)))
            ->setTipo($resultado['tipo'] ?? null)
            ->setStatus((string) ($resultado['status'] ?? 'inexistente'))
            ->setOrigem($origem)
            ->setIp($request->getClientIp())
            ->setUserAgent(substr((string) $request->headers->get('User-Agent', ''), 0, 255));

        if ($resolved !== null) {
            $paciente = $resolved['paciente'];
            $log->setPaciente($paciente)->setEmpresa($paciente->getEmpresa());
        }

        $this->em->persist($log);
        $this->em->flush();
    }
}
