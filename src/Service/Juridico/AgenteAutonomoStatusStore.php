<?php

declare(strict_types=1);

namespace App\Service\Juridico;

/**
 * Persiste o estado do Agente Autônomo Jurídico (última execução, deduplicação de
 * alertas já enviados e estatísticas) em um arquivo JSON simples — sem precisar de
 * tabela própria no banco. O mesmo padrão usado para o histórico de tokens da Azure.
 */
final class AgenteAutonomoStatusStore
{
    /** Não reenviar o mesmo alerta antes desse intervalo, mesmo que o agente rode de novo antes disso. */
    private const JANELA_DEDUP_HORAS = 20;

    /** Chaves de deduplicação mais antigas que isso são descartadas para o arquivo não crescer para sempre. */
    private const RETENCAO_HORAS = 72;

    /** Considera o agente "ativo" se rodou dentro desse intervalo. */
    private const JANELA_ATIVO_MINUTOS = 90;

    private string $filePath;

    public function __construct(string $projectDir)
    {
        $this->filePath = $projectDir . '/var/data/agente_autonomo_status.json';
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        if (!is_file($this->filePath)) {
            return $this->estadoVazio();
        }

        $raw = file_get_contents($this->filePath);
        $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;

        return \is_array($data) ? array_merge($this->estadoVazio(), $data) : $this->estadoVazio();
    }

    /** @param array<string, mixed> $estado */
    public function jaNotificado(array $estado, string $chave): bool
    {
        $ts = $estado['notificados'][$chave] ?? null;
        if (!\is_string($ts)) {
            return false;
        }

        $quando = $this->paraData($ts);
        if ($quando === null) {
            return false;
        }

        return $quando >= (new \DateTimeImmutable('now'))->modify('-' . self::JANELA_DEDUP_HORAS . ' hours');
    }

    /**
     * @param array<string, mixed> $estado
     * @param list<string>         $novasChaves
     *
     * @return array<string, mixed>
     */
    public function registrarExecucao(array $estado, int $empresaId, string $empresaNome, array $novasChaves): array
    {
        $agora = new \DateTimeImmutable('now');
        $isoAgora = $agora->format(\DateTimeInterface::ATOM);

        $notificados = \is_array($estado['notificados'] ?? null) ? $estado['notificados'] : [];
        foreach ($novasChaves as $chave) {
            $notificados[$chave] = $isoAgora;
        }

        $limitePoda = $agora->modify('-' . self::RETENCAO_HORAS . ' hours');
        foreach ($notificados as $chave => $ts) {
            $quando = \is_string($ts) ? $this->paraData($ts) : null;
            if ($quando === null || $quando < $limitePoda) {
                unset($notificados[$chave]);
            }
        }
        $estado['notificados'] = $notificados;

        $estado['last_run_at'] = $isoAgora;
        $estado['total_runs'] = (int) ($estado['total_runs'] ?? 0) + 1;
        $estado['total_alertas'] = (int) ($estado['total_alertas'] ?? 0) + \count($novasChaves);

        $empresas = \is_array($estado['empresas'] ?? null) ? $estado['empresas'] : [];
        $empresas[(string) $empresaId] = [
            'nome' => $empresaNome,
            'last_run_at' => $isoAgora,
            'alertas_nesta_execucao' => \count($novasChaves),
        ];
        $estado['empresas'] = $empresas;

        return $estado;
    }

    /** @param array<string, mixed> $estado */
    public function persist(array $estado): void
    {
        $dir = \dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->filePath,
            json_encode($estado, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE),
            \LOCK_EX,
        );
    }

    /**
     * @return array{
     *     ativo: bool,
     *     last_run_at: ?string,
     *     minutos_desde_execucao: ?int,
     *     alertas_hoje: int,
     *     total_runs: int,
     *     total_alertas: int,
     *     empresas_monitoradas: int
     * }
     */
    public function resumo(): array
    {
        $estado = $this->load();
        $lastRun = \is_string($estado['last_run_at'] ?? null) ? $estado['last_run_at'] : null;
        $minutos = null;
        $ativo = false;

        if ($lastRun !== null) {
            $quando = $this->paraData($lastRun);
            if ($quando !== null) {
                $minutos = (int) round((time() - $quando->getTimestamp()) / 60);
                $ativo = $minutos <= self::JANELA_ATIVO_MINUTOS;
            } else {
                $lastRun = null;
            }
        }

        $hoje = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $alertasHoje = 0;
        foreach ((array) ($estado['notificados'] ?? []) as $ts) {
            if (\is_string($ts) && str_starts_with($ts, $hoje)) {
                ++$alertasHoje;
            }
        }

        return [
            'ativo' => $ativo,
            'last_run_at' => $lastRun,
            'minutos_desde_execucao' => $minutos,
            'alertas_hoje' => $alertasHoje,
            'total_runs' => (int) ($estado['total_runs'] ?? 0),
            'total_alertas' => (int) ($estado['total_alertas'] ?? 0),
            'empresas_monitoradas' => \count((array) ($estado['empresas'] ?? [])),
        ];
    }

    private function paraData(string $ts): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($ts);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function estadoVazio(): array
    {
        return [
            'last_run_at' => null,
            'total_runs' => 0,
            'total_alertas' => 0,
            'notificados' => [],
            'empresas' => [],
        ];
    }
}
