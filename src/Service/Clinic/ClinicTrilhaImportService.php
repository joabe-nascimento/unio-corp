<?php

namespace App\Service\Clinic;

use App\Entity\Empresa;
use App\Entity\PosOperatorioProtocolo;
use App\Entity\User;
use App\Repository\PosOperatorioProtocoloRepository;
use App\Repository\UserRepository;
use App\Service\Clinic\ClinicPlanLimitsService;
use App\Service\PosOperatorio\PosOperatorioPacienteService;
use App\Service\PosOperatorio\PosOperatorioProtocoloService;
use App\Support\BrPersonFormat;

/**
 * Importa planilha CSV de pacientes e inicia a Trilha Unio (protocolo + data cirurgia).
 */
final class ClinicTrilhaImportService
{
    public const TEMPLATE_HEADERS = [
        'nome',
        'procedimento',
        'data_cirurgia',
        'telefone',
        'cpf',
        'email',
        'medico_email',
    ];

    public function __construct(
        private PosOperatorioPacienteService $pacienteService,
        private PosOperatorioProtocoloService $protocoloService,
        private PosOperatorioProtocoloRepository $protocoloRepo,
        private UserRepository $userRepo,
        private ClinicPlanLimitsService $planLimits,
    ) {}

    /** @return array{ok: true, importados: int, erros: list<string>, avisos: list<string>}|array{ok: false, error: string} */
    public function importCsv(Empresa $empresa, string $csvContent, User $autor): array
    {
        if (!$this->planLimits->canAddBeneficiario($empresa)) {
            return ['ok' => false, 'error' => 'Limite de beneficiários do plano atingido.'];
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        if ($lines === []) {
            return ['ok' => false, 'error' => 'Arquivo vazio.'];
        }

        $header = str_getcsv($this->stripBom((string) array_shift($lines)));
        $map = $this->mapHeaders($header);
        if (!isset($map['nome'])) {
            return ['ok' => false, 'error' => 'Coluna "nome" é obrigatória.'];
        }
        $procIdx = $map['procedimento'] ?? $map['protocolo'] ?? null;
        if ($procIdx === null) {
            return ['ok' => false, 'error' => 'Coluna "procedimento" (nome do protocolo) é obrigatória.'];
        }
        if (!isset($map['data_cirurgia'])) {
            return ['ok' => false, 'error' => 'Coluna "data_cirurgia" é obrigatória.'];
        }

        $this->protocoloService->ensureLibraryProtocols($empresa);
        $protocolos = $this->protocoloRepo->findAtivosByEmpresa($empresa);

        $importados = 0;
        $erros = [];
        $avisos = [];

        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line);
            $lineNo = $i + 2;

            try {
                if (!$this->planLimits->canAddBeneficiario($empresa)) {
                    throw new \RuntimeException('Limite de beneficiários do plano atingido.');
                }

                $this->importRow($empresa, $row, $map, $protocolos, $autor);
                ++$importados;
            } catch (\Throwable $e) {
                $erros[] = sprintf('Linha %d: %s', $lineNo, $e->getMessage());
            }
        }

        if ($importados === 0 && $erros === []) {
            return ['ok' => false, 'error' => 'Nenhuma linha de dados encontrada no arquivo.'];
        }

        return ['ok' => true, 'importados' => $importados, 'erros' => $erros, 'avisos' => $avisos];
    }

    public function templateCsv(): string
    {
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }

        fputcsv($out, self::TEMPLATE_HEADERS);
        fputcsv($out, [
            'Maria Silva',
            'Herniorrafia inguinal',
            '2026-07-10',
            '11999998888',
            '52998224725',
            'maria@email.com',
            '',
        ]);
        fputcsv($out, [
            'João Santos',
            'Colecistectomia videolaparoscópica',
            '15/07/2026',
            '21988887777',
            '',
            '',
            '',
        ]);

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv !== false ? $csv : '';
    }

    /** @param list<string> $header @return array<string, int> */
    private function mapHeaders(array $header): array
    {
        $map = [];
        foreach ($header as $i => $col) {
            $key = $this->normalizeHeader((string) $col);
            if ($key !== '') {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    private function normalizeHeader(string $col): string
    {
        $key = strtolower(trim($col));
        $key = str_replace([' ', '-'], '_', $key);

        return match ($key) {
            'nome_paciente', 'paciente' => 'nome',
            'protocolo', 'trilha', 'procedimento_protocolo' => 'procedimento',
            'data_alta', 'data_cirurgia', 'data' => 'data_cirurgia',
            'telefone', 'celular', 'whatsapp' => 'telefone',
            'e_mail' => 'email',
            'medico', 'medico_responsavel', 'email_medico' => 'medico_email',
            default => $key,
        };
    }

    /**
     * @param list<string> $row
     * @param array<string, int> $map
     * @param list<PosOperatorioProtocolo> $protocolos
     */
    private function importRow(
        Empresa $empresa,
        array $row,
        array $map,
        array $protocolos,
        User $autor,
    ): void {
        $nome = trim((string) ($row[$map['nome']] ?? ''));
        if ($nome === '') {
            throw new \InvalidArgumentException('Nome vazio.');
        }

        $procedimentoNome = trim((string) ($row[$map['procedimento'] ?? $map['protocolo']] ?? ''));
        if ($procedimentoNome === '') {
            throw new \InvalidArgumentException('Procedimento/protocolo vazio.');
        }

        $dataRaw = trim((string) ($row[$map['data_cirurgia']] ?? ''));
        $dataCirurgia = $this->parseDate($dataRaw);
        if ($dataCirurgia === null) {
            throw new \InvalidArgumentException(sprintf('Data inválida: "%s". Use AAAA-MM-DD ou DD/MM/AAAA.', $dataRaw));
        }

        $protocolo = $this->resolveProtocolo($protocolos, $procedimentoNome);
        if ($protocolo === null) {
            throw new \InvalidArgumentException(sprintf(
                'Protocolo "%s" não encontrado. Use o nome exato da biblioteca ou cadastre em Protocolos.',
                $procedimentoNome,
            ));
        }

        $data = [
            'nome' => $nome,
            'protocolo_id' => $protocolo->getId(),
            'data_cirurgia' => $dataCirurgia->format('Y-m-d'),
            'telefone' => trim((string) ($row[$map['telefone'] ?? -1] ?? '')),
            'email_contato' => trim((string) ($row[$map['email'] ?? -1] ?? '')),
            'cpf' => trim((string) ($row[$map['cpf'] ?? -1] ?? '')),
        ];

        $medicoEmail = strtolower(trim((string) ($row[$map['medico_email'] ?? -1] ?? '')));
        if ($medicoEmail !== '') {
            $medico = $this->userRepo->findOneBy(['email' => $medicoEmail, 'empresa' => $empresa, 'ativo' => true]);
            if ($medico instanceof User) {
                $data['medico_id'] = $medico->getId();
            }
        }

        if (($data['cpf'] ?? '') !== '' && !BrPersonFormat::isValidCpf((string) $data['cpf'])) {
            throw new \InvalidArgumentException('CPF inválido.');
        }

        $this->pacienteService->create($empresa, $data, $autor);
    }

    /** @param list<PosOperatorioProtocolo> $protocolos */
    private function resolveProtocolo(array $protocolos, string $needle): ?PosOperatorioProtocolo
    {
        $needleNorm = $this->normalizeProtocolName($needle);

        foreach ($protocolos as $protocolo) {
            if ($this->normalizeProtocolName($protocolo->getNome()) === $needleNorm) {
                return $protocolo;
            }
        }

        foreach ($protocolos as $protocolo) {
            $name = $this->normalizeProtocolName($protocolo->getNome());
            if (str_contains($name, $needleNorm) || str_contains($needleNorm, $name)) {
                return $protocolo;
            }
        }

        return null;
    }

    private function normalizeProtocolName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return $name;
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt;
            }
        }

        return null;
    }

    private function stripBom(string $line): string
    {
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            return substr($line, 3);
        }

        return $line;
    }
}
