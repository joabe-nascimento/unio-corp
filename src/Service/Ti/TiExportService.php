<?php

namespace App\Service\Ti;

use App\Entity\Empresa;

final class TiExportService
{
    public function __construct(private TiChamadoService $chamados) {}

    public function analyticsCsv(Empresa $empresa): string
    {
        $stats = $this->chamados->stats($empresa);
        $lines = ['metrica,valor'];
        foreach ($stats as $key => $value) {
            $lines[] = $key . ',' . $value;
        }
        $lines[] = 'sla_compliance,' . $this->chamados->slaCompliance($empresa);
        $lines[] = 'mttr_horas,' . $this->chamados->mttrHours($empresa);
        $lines[] = 'cortex_auto_rate,' . $this->chamados->cortexAutoRate($empresa);

        foreach ($this->chamados->analyticsVolume($empresa) as $row) {
            $lines[] = 'volume_' . ($row['month'] ?? '') . '_abertos,' . ($row['opened'] ?? 0);
            $lines[] = 'volume_' . ($row['month'] ?? '') . '_resolvidos,' . ($row['resolved'] ?? 0);
        }

        $lines[] = '';
        $lines[] = 'tecnico,chamados_abertos';
        foreach ($this->chamados->workloadByTechnician($empresa) as $row) {
            $lines[] = $this->escape((string) ($row['name'] ?? '')) . ',' . ($row['count'] ?? 0);
        }

        $lines[] = '';
        $lines[] = 'categoria,mttr_medio_horas,quantidade';
        foreach ($this->chamados->mttrByCategory($empresa) as $row) {
            $lines[] = $this->escape((string) ($row['category'] ?? '')) . ',' . ($row['avg_hours'] ?? 0) . ',' . ($row['count'] ?? 0);
        }

        $lines[] = '';
        $lines[] = 'hora,chamados,sla_breach';
        foreach ($this->chamados->slaHeatmapByHour($empresa) as $row) {
            $lines[] = ($row['hour'] ?? 0) . ',' . ($row['count'] ?? 0) . ',' . ($row['sla_breach'] ?? 0);
        }

        return implode("\n", $lines);
    }

    public function ticketsCsv(Empresa $empresa): string
    {
        $lines = ['id,titulo,status,prioridade,categoria,solicitante,responsavel,sla_pct,aberto_em'];
        foreach ($this->chamados->allSorted($empresa) as $t) {
            $lines[] = implode(',', [
                $t['id'] ?? '',
                $this->escape($t['title'] ?? ''),
                $t['status'] ?? '',
                $t['priority'] ?? '',
                $t['category'] ?? '',
                $this->escape($t['requester'] ?? ''),
                $this->escape($t['assignee'] ?? ''),
                (string) ($t['sla_pct'] ?? ''),
                $this->escape($t['opened_at'] ?? ''),
            ]);
        }

        return implode("\n", $lines);
    }

    private function escape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
