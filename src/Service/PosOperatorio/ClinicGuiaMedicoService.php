<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;

/**
 * Editor e armazenamento de guias médicos por clínica (independente da biblioteca de protocolos).
 */
final class ClinicGuiaMedicoService
{
    public function __construct(
        private string $projectDir,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(Empresa $empresa): array
    {
        return $this->read($empresa)['guias'] ?? [];
    }

    public function find(Empresa $empresa, string $id): ?array
    {
        foreach ($this->list($empresa) as $guia) {
            if (($guia['id'] ?? '') === $id) {
                return $guia;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public function save(Empresa $empresa, array $data, ?string $id = null): array
    {
        $store = $this->read($empresa);
        $guias = $store['guias'] ?? [];
        if (!\is_array($guias)) {
            $guias = [];
        }

        $record = $this->normalizeGuia($data, $id);
        $found = false;
        foreach ($guias as $i => $guia) {
            if (($guia['id'] ?? '') === $record['id']) {
                $guias[$i] = $record;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $guias[] = $record;
        }

        $store['guias'] = array_values($guias);
        $this->write($empresa, $store);

        return $record;
    }

    public function delete(Empresa $empresa, string $id): void
    {
        $store = $this->read($empresa);
        $guias = array_values(array_filter(
            $store['guias'] ?? [],
            static fn (array $g): bool => ($g['id'] ?? '') !== $id,
        ));
        $store['guias'] = $guias;
        $this->write($empresa, $store);
    }

    public function resolveForPaciente(PosOperatorioPaciente $paciente): ?array
    {
        $empresa = $paciente->getEmpresa();
        $needle = mb_strtolower(trim(implode(' ', array_filter([
            $paciente->getProcedimento(),
            $paciente->getProtocolo()?->getTipoProcedimento(),
            $paciente->getProtocolo()?->getNome(),
        ]))));

        if ($needle === '') {
            return $this->defaultGuia($empresa);
        }

        foreach ($this->list($empresa) as $guia) {
            if (!($guia['ativo'] ?? true)) {
                continue;
            }
            $match = mb_strtolower(trim((string) ($guia['tipo_procedimento'] ?? '')));
            if ($match !== '' && str_contains($needle, $match)) {
                return $guia;
            }
        }

        return $this->defaultGuia($empresa);
    }

    /** @return array<string, mixed>|null */
    private function defaultGuia(Empresa $empresa): ?array
    {
        foreach ($this->list($empresa) as $guia) {
            if (($guia['ativo'] ?? true) && ($guia['padrao'] ?? false)) {
                return $guia;
            }
        }

        $guias = $this->list($empresa);
        foreach ($guias as $guia) {
            if ($guia['ativo'] ?? true) {
                return $guia;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    private function normalizeGuia(array $data, ?string $id = null): array
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw new \InvalidArgumentException('Informe o nome do guia.');
        }

        return [
            'id' => $id ?: $this->slugId($nome),
            'nome' => $nome,
            'tipo_procedimento' => trim((string) ($data['tipo_procedimento'] ?? '')),
            'subtitulo' => trim((string) ($data['subtitulo'] ?? 'Orientações por fase da recuperação')),
            'contato_rapido' => trim((string) ($data['contato_rapido'] ?? 'Use "Preciso de ajuda" no portal ou ligue para a clínica.')),
            'orientacoes_aguda' => $this->lines($data['orientacoes_aguda'] ?? ''),
            'orientacoes_intermediaria' => $this->lines($data['orientacoes_intermediaria'] ?? ''),
            'orientacoes_retorno' => $this->lines($data['orientacoes_retorno'] ?? ''),
            'orientacoes_alta' => $this->lines($data['orientacoes_alta'] ?? ''),
            'sinais_alerta' => $this->lines($data['sinais_alerta'] ?? "Dor intensa que não melhora com medicação prescrita\nFebre acima de 38 °C\nSangramento intenso ou secreção com odor\nFalta de ar ou confusão"),
            'ativo' => (bool) ($data['ativo'] ?? true),
            'padrao' => (bool) ($data['padrao'] ?? false),
            'atualizado_em' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @return list<string> */
    private function lines(mixed $raw): array
    {
        if (\is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw), static fn (string $l): bool => $l !== ''));
        }

        $text = str_replace("\r", '', (string) $raw);

        return array_values(array_filter(array_map('trim', explode("\n", $text)), static fn (string $l): bool => $l !== ''));
    }

    private function slugId(string $nome): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($nome)) ?? 'guia';

        return trim($slug, '-') ?: 'guia-' . bin2hex(random_bytes(3));
    }

    /** @return array<string, mixed> */
    private function read(Empresa $empresa): array
    {
        $path = $this->path($empresa);
        if (!is_file($path)) {
            return ['guias' => $this->seedGuias()];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return ['guias' => $this->seedGuias()];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : ['guias' => $this->seedGuias()];
    }

    /** @return list<array<string, mixed>> */
    private function seedGuias(): array
    {
        return [[
            'id' => 'geral',
            'nome' => 'Guia geral pós-operatório',
            'tipo_procedimento' => '',
            'subtitulo' => 'Orientações por fase da recuperação',
            'contato_rapido' => 'Use "Preciso de ajuda" no portal ou ligue para a clínica em horário comercial.',
            'orientacoes_aguda' => [
                'Priorize repouso relativo e ingestão líquida adequada.',
                'Tome os medicamentos nos horários indicados na receita.',
                'Não retire curativos sem orientação da equipe.',
            ],
            'orientacoes_intermediaria' => [
                'Caminhe pequenas distâncias se liberado pelo médico.',
                'Observe vermelhidão, calor ou secreção no sítio cirúrgico.',
                'Responda o questionário diário para a equipe acompanhar sua evolução.',
            ],
            'orientacoes_retorno' => [
                'Confirme retornos e exames agendados.',
                'Retome atividades leves de forma progressiva.',
            ],
            'orientacoes_alta' => [
                'Mantenha contato se notar piora dos sintomas.',
            ],
            'sinais_alerta' => [
                'Dor intensa que não melhora com medicação prescrita',
                'Febre acima de 38 °C ou calafrios',
                'Sangramento intenso ou secreção com odor forte',
                'Falta de ar, tontura persistente ou confusão',
            ],
            'ativo' => true,
            'padrao' => true,
            'atualizado_em' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]];
    }

    /** @param array<string, mixed> $data */
    private function write(Empresa $empresa, array $data): void
    {
        $dir = dirname($this->path($empresa));
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar diretório de guias médicos.');
        }
        file_put_contents(
            $this->path($empresa),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    private function path(Empresa $empresa): string
    {
        return sprintf('%s/var/clinic/guias-%d.json', rtrim($this->projectDir, '/\\'), $empresa->getId());
    }
}
