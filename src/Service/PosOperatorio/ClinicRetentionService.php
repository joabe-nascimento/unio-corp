<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use App\Repository\PosOperatorioPacienteRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Retenção LGPD — encerra e anonimiza fichas além da política da clínica.
 */
final class ClinicRetentionService
{
    public function __construct(
        private EmpresaRepository $empresas,
        private PosOperatorioPacienteRepository $pacientes,
        private ClinicPolicyConfigService $policy,
        private ClinicConfigStore $store,
        private PosOperatorioEventRecorder $events,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return array{empresas: int, elegiveis: int, anonimizados: int}
     */
    public function runAll(?int $empresaId = null, bool $dryRun = false): array
    {
        $list = $empresaId
            ? array_filter([$this->empresas->find($empresaId)])
            : $this->empresas->findBy(['ativo' => true]);

        $elegiveis = 0;
        $anonimizados = 0;
        $count = 0;

        foreach ($list as $empresa) {
            if (!$empresa instanceof Empresa) {
                continue;
            }
            ++$count;
            $result = $this->runForEmpresa($empresa, $dryRun);
            $elegiveis += $result['elegiveis'];
            $anonimizados += $result['anonimizados'];
        }

        return ['empresas' => $count, 'elegiveis' => $elegiveis, 'anonimizados' => $anonimizados];
    }

    /**
     * @return array{elegiveis: int, anonimizados: int, last_run: ?string}
     */
    public function status(Empresa $empresa): array
    {
        $meta = $this->store->read($empresa, 'integracoes');
        $retention = \is_array($meta['retention_meta'] ?? null) ? $meta['retention_meta'] : [];

        return [
            'elegiveis' => $this->countEligible($empresa),
            'anonimizados' => (int) ($retention['last_count'] ?? 0),
            'last_run' => isset($retention['last_run']) ? (string) $retention['last_run'] : null,
        ];
    }

    /**
     * @return array{elegiveis: int, anonimizados: int}
     */
    public function runForEmpresa(Empresa $empresa, bool $dryRun = false): array
    {
        $days = $this->policy->get($empresa)['retencao_dias'];
        $cutoff = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $days));
        $candidates = [];

        foreach ($this->pacientes->findRecentByEmpresa($empresa, 500, 0) as $paciente) {
            if ($paciente->getStatus() !== PosOperatorioPaciente::STATUS_ENCERRADO) {
                continue;
            }
            $ref = $paciente->getDataCirurgia() ?? $paciente->getCriadoEm();
            if ($ref === null || $ref > $cutoff) {
                continue;
            }
            if ($this->alreadyAnonymized($paciente)) {
                continue;
            }
            $candidates[] = $paciente;
        }

        $anonimizados = 0;
        if (!$dryRun) {
            foreach ($candidates as $paciente) {
                $this->anonymize($paciente);
                ++$anonimizados;
            }
            if ($anonimizados > 0) {
                $this->em->flush();
            }
            $meta = $this->store->read($empresa, 'integracoes');
            $meta['retention_meta'] = [
                'last_run' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'last_count' => $anonimizados,
            ];
            $this->store->write($empresa, 'integracoes', $meta);
        }

        return ['elegiveis' => \count($candidates), 'anonimizados' => $anonimizados];
    }

    private function countEligible(Empresa $empresa): int
    {
        return $this->runForEmpresa($empresa, true)['elegiveis'];
    }

    private function alreadyAnonymized(PosOperatorioPaciente $paciente): bool
    {
        return str_starts_with($paciente->getNome(), 'Paciente anonimizado');
    }

    private function anonymize(PosOperatorioPaciente $paciente): void
    {
        $paciente
            ->setNome(sprintf('Paciente anonimizado #%d', $paciente->getId()))
            ->setTelefoneContato(null)
            ->setEmailContato(null);

        if (method_exists($paciente, 'setCpf')) {
            $paciente->setCpf(null);
        }
        if (method_exists($paciente, 'setPortalUser')) {
            $paciente->setPortalUser(null);
        }

        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_CADASTRO,
            'Ficha anonimizada por política de retenção LGPD',
            null,
        );
    }
}
