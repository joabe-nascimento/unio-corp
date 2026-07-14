<?php

namespace App\Service\Clinic;

use App\Entity\Empresa;
use App\Repository\ClinicCheckinRepository;
use App\Repository\PosOperatorioPacienteRepository;

final class ClinicDayPanelService
{
    public function __construct(
        private PosOperatorioPacienteRepository $pacientes,
        private ClinicCheckinRepository $checkins,
    ) {}

    /** @return array<string, mixed> */
    public function build(Empresa $empresa): array
    {
        $preOp = $this->pacientes->findPreOpThisWeek($empresa, 7);

        return [
            'checkins_hoje' => $this->formatCheckins($this->checkins->findTodayByEmpresa($empresa)),
            'sem_questionario' => $this->formatPacientes($this->pacientes->findActiveWithoutQuestionarioToday($empresa, new \DateTimeImmutable('today'))),
            'carteirinhas_vencendo' => $this->formatPacientes($this->pacientes->findCarteirinhasExpirando($empresa, 7)),
            'retornos_hoje' => $this->formatPacientes($this->pacientes->findRetornosNoDia($empresa, new \DateTimeImmutable('today'))),
            'pre_op_semana' => $this->formatPacientes($preOp),
            'totais' => [
                'pacientes_ativos' => $this->pacientes->countActiveByEmpresa($empresa),
                'checkins' => \count($this->checkins->findTodayByEmpresa($empresa)),
                'pre_op_semana' => \count($preOp),
            ],
        ];
    }

    /** @param list<\App\Entity\ClinicCheckin> $items @return list<array<string, mixed>> */
    private function formatCheckins(array $items): array
    {
        return array_map(static fn ($c): array => [
            'hora' => $c->getCriadoEm()->format('H:i'),
            'paciente' => $c->getPaciente()->getNome(),
            'codigo' => $c->getPaciente()->getCodigo(),
            'metodo' => $c->getMetodo(),
        ], $items);
    }

    /** @param list<\App\Entity\PosOperatorioPaciente> $items @return list<array<string, mixed>> */
    private function formatPacientes(array $items): array
    {
        return array_map(static function ($p): array {
            $rel = $p->getDiaRelativoCirurgia();

            return [
                'id' => $p->getId(),
                'nome' => $p->getNome(),
                'codigo' => $p->getCodigo(),
                'dia_pos' => $p->getDiaPosOperatorio(),
                'dia_relativo' => $rel,
                'dia_label' => $rel !== null ? $p::formatDiaRelativoLabel($rel) : null,
                'is_pre_op' => $p->isPreOperatorio(),
                'data_cirurgia' => $p->getDataCirurgia()?->format('d/m/Y'),
                'status' => $p->getStatus(),
            ];
        }, $items);
    }
}
