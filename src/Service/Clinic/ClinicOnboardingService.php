<?php

namespace App\Service\Clinic;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\ClinicCarteirinhaService;
use App\Service\PosOperatorio\ClinicConfigStore;
use App\Service\PosOperatorio\PosOperatorioPacienteService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Onboarding self-service — ativar produtos, importar CSV, primeira carteirinha.
 */
final class ClinicOnboardingService
{
    public function __construct(
        private ClinicConfigStore $configStore,
        private ClinicPlanLimitsService $planLimits,
        private PosOperatorioPacienteService $pacienteService,
        private ClinicCarteirinhaService $carteirinhaService,
        private PosOperatorioPacienteRepository $pacientes,
        private EntityManagerInterface $em,
    ) {}

    /** @return array<string, mixed> */
    public function status(Empresa $empresa): array
    {
        $stored = $this->configStore->read($empresa, 'onboarding');

        return array_merge([
            'produtos_ativados' => false,
            'csv_importado' => false,
            'primeira_carteirinha' => false,
            'branding_configurado' => false,
            'concluido' => false,
            'importados' => 0,
        ], $stored);
    }

    /** @param array<string, mixed> $patch */
    public function patch(Empresa $empresa, array $patch): void
    {
        $this->configStore->write($empresa, 'onboarding', array_merge($this->status($empresa), $patch));
    }

    public function markProdutosAtivados(Empresa $empresa): void
    {
        $this->patch($empresa, ['produtos_ativados' => true]);
        $this->refreshConcluido($empresa);
    }

    /**
     * @return array{ok: true, importados: int, erros: list<string>}|array{ok: false, error: string}
     */
    public function importCsv(Empresa $empresa, string $csvContent, User $autor): array
    {
        if (!$this->planLimits->canAddBeneficiario($empresa)) {
            return ['ok' => false, 'error' => 'Limite de beneficiários do plano atingido.'];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        if ($lines === []) {
            return ['ok' => false, 'error' => 'CSV vazio.'];
        }

        $header = str_getcsv(array_shift($lines));
        $map = $this->mapHeaders($header);
        if (!isset($map['nome'])) {
            return ['ok' => false, 'error' => 'Coluna "nome" obrigatória no CSV.'];
        }

        $importados = 0;
        $erros = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line);
            try {
                $this->importRow($empresa, $row, $map, $autor);
                ++$importados;
            } catch (\Throwable $e) {
                $erros[] = sprintf('Linha %d: %s', $i + 2, $e->getMessage());
            }
        }

        $status = $this->status($empresa);
        $this->patch($empresa, [
            'csv_importado' => $importados > 0,
            'importados' => (int) ($status['importados'] ?? 0) + $importados,
        ]);
        $this->refreshConcluido($empresa);

        return ['ok' => true, 'importados' => $importados, 'erros' => $erros];
    }

    public function emitirPrimeiraCarteirinha(Empresa $empresa, PosOperatorioPaciente $paciente, User $autor): void
    {
        if (!$paciente->hasCarteirinhaAtiva()) {
            $this->carteirinhaService->emitir($paciente, $autor, 'essencial', 14);
        }
        $this->patch($empresa, ['primeira_carteirinha' => true]);
        $this->refreshConcluido($empresa);
    }

    /** @param list<string> $header @return array<string, int> */
    private function mapHeaders(array $header): array
    {
        $map = [];
        foreach ($header as $i => $col) {
            $key = strtolower(trim($col));
            $map[$key] = $i;
        }

        return $map;
    }

    /** @param list<string> $row @param array<string, int> $map */
    private function importRow(Empresa $empresa, array $row, array $map, User $autor): void
    {
        if (!$this->planLimits->canAddBeneficiario($empresa)) {
            throw new \RuntimeException('Limite de beneficiários atingido.');
        }

        $nome = trim($row[$map['nome']] ?? '');
        if ($nome === '') {
            throw new \InvalidArgumentException('Nome vazio.');
        }

        $cpf = isset($map['cpf']) ? preg_replace('/\D+/', '', (string) ($row[$map['cpf']] ?? '')) : null;
        $codigo = isset($map['codigo']) ? strtoupper(trim((string) ($row[$map['codigo']] ?? ''))) : null;
        $procedimento = isset($map['procedimento']) ? trim((string) ($row[$map['procedimento']] ?? '')) : null;

        if ($codigo !== null && $codigo !== '' && $this->pacientes->findByCodigo($empresa, $codigo) !== null) {
            throw new \InvalidArgumentException(sprintf('Código %s já existe.', $codigo));
        }

        $data = [
            'nome' => $nome,
            'cpf' => $cpf !== '' ? $cpf : null,
            'procedimento' => $procedimento !== '' ? $procedimento : null,
        ];

        $paciente = $this->pacienteService->create($empresa, $data, $autor);

        if ($codigo !== null && $codigo !== '') {
            $paciente->setCodigo($codigo);
            $this->em->flush();
        }
    }

    private function refreshConcluido(Empresa $empresa): void
    {
        $s = $this->status($empresa);
        $concluido = ($s['produtos_ativados'] ?? false)
            && ($s['csv_importado'] ?? false)
            && ($s['primeira_carteirinha'] ?? false);

        $this->patch($empresa, ['concluido' => $concluido]);
    }
}
