<?php

namespace App\Service\Clinic;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\Marketing\ClinicPatientProductService;
use App\Service\PosOperatorio\ClinicCarteirinhaService;
use App\Service\PosOperatorio\ClinicComprovanteService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Demonstração sandbox — Ana Costa, João Pereira e Maria Silva sempre disponíveis.
 */
final class ClinicSandboxService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PosOperatorioPacienteRepository $pacientes,
        private ClinicPatientProductService $demoProduct,
        private ClinicCarteirinhaService $carteirinha,
        private ClinicComprovanteService $comprovante,
    ) {}

    /** @return list<PosOperatorioPaciente> */
    public function ensureSandbox(Empresa $empresa, User $autor): array
    {
        $created = [];
        foreach ($this->demoProduct->plans() as $plan) {
            $codigo = (string) ($plan['codigo'] ?? '');
            if ($codigo === '') {
                continue;
            }

            $existing = $this->pacientes->findByCodigo($empresa, $codigo);
            if ($existing instanceof PosOperatorioPaciente) {
                $this->syncSandboxPatient($existing, $plan);
                $created[] = $existing;
                continue;
            }

            $cpf = preg_replace('/\D+/', '', (string) ($plan['cpf_masked'] ?? '')) ?: null;
            $paciente = (new PosOperatorioPaciente())
                ->setEmpresa($empresa)
                ->setCodigo($codigo)
                ->setNome((string) ($plan['nome'] ?? 'Beneficiário'))
                ->setCpf($cpf)
                ->setProcedimento((string) ($plan['procedimento'] ?? 'Procedimento'))
                ->setDataCirurgia(new \DateTimeImmutable('-' . (int) ($plan['dia_pos'] ?? 3) . ' days'))
                ->setStatus(PosOperatorioPaciente::STATUS_ATIVO)
                ->setIsSandbox(true)
                ->setTitularCpf($cpf);

            $this->em->persist($paciente);
            $this->em->flush();

            $planoId = (string) ($plan['id'] ?? 'essencial');
            $verificacao = (string) ($plan['verificacao'] ?? '');

            if ($verificacao !== '') {
                $paciente
                    ->setCarteirinhaPlano($planoId)
                    ->setCarteirinhaVerificacao(strtoupper($verificacao))
                    ->setCarteirinhaEmitidaEm(new \DateTimeImmutable('-2 days'))
                    ->setCarteirinhaValidaAte(new \DateTimeImmutable('+12 days'))
                    ->setConsentimentoCarteirinhaEm(new \DateTimeImmutable());
            }

            $this->em->flush();

            if (!$paciente->hasComprovanteAtivo() && $codigo === 'PO-0042') {
                $paciente->setComprovanteVerificacao('CP' . substr(strtoupper($verificacao), 0, 6));
                $paciente->setComprovanteEmitidaEm(new \DateTimeImmutable('-2 days'));
                $paciente->setComprovanteValidoAte(new \DateTimeImmutable('+28 days'));
                $paciente->setComprovanteHash(ClinicDocumentoAuditService::hashComprovante($paciente));
                $this->em->flush();
            }

            $created[] = $paciente;
        }

        return $created;
    }

    /** @param array<string, mixed> $plan */
    private function syncSandboxPatient(PosOperatorioPaciente $paciente, array $plan): void
    {
        $paciente
            ->setIsSandbox(true)
            ->setNome((string) ($plan['nome'] ?? $paciente->getNome()))
            ->setProcedimento((string) ($plan['procedimento'] ?? $paciente->getProcedimento()));

        $verificacao = strtoupper((string) ($plan['verificacao'] ?? ''));
        if ($verificacao !== '' && !$paciente->hasCarteirinhaAtiva()) {
            $paciente
                ->setCarteirinhaPlano((string) ($plan['id'] ?? 'essencial'))
                ->setCarteirinhaVerificacao($verificacao)
                ->setCarteirinhaEmitidaEm(new \DateTimeImmutable('-2 days'))
                ->setCarteirinhaValidaAte(new \DateTimeImmutable('+12 days'));
        }

        $this->em->flush();
    }
}
