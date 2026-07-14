<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicAgendamento;
use App\Entity\Empresa;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Repository\ClinicAgendamentoRepository;
use App\Repository\EmpresaRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\Organismo\Contract\CareContractService;
use App\Service\Organismo\Contract\ContractAttestationService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Confirmação de agenda D-1 + lembretes de marcos pré-op (D−7 / D−3) e handoff D0.
 */
final class ClinicAgendaReminderService
{
    public function __construct(
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicPatientNotifier $patientNotifier,
        private EmpresaRepository $empresas,
        private PosOperatorioPacienteRepository $pacientes,
        private CareContractService $careContracts,
        private ContractAttestationService $attestations,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return array{
     *     enviados: int,
     *     ignorados: int,
     *     sem_telefone: int,
     *     items: list<array<string, mixed>>
     * }
     */
    public function prepareForTomorrow(Empresa $empresa, ?\DateTimeImmutable $reference = null): array
    {
        $ref = $reference ?? new \DateTimeImmutable('today');
        $dayStart = $ref->modify('+1 day')->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $pending = $this->agendamentos->findPendingConfirmacaoReminders($empresa, $dayStart, $dayEnd);
        $enviados = 0;
        $semTelefone = 0;
        $items = [];

        foreach ($pending as $agendamento) {
            $result = $this->patientNotifier->notifyAgendaConfirmacao($agendamento);
            $agendamento->setLembreteConfirmacaoEm(new \DateTimeImmutable());
            $agendamento->touch();
            ++$enviados;

            if ($result['whatsapp_url'] === null) {
                ++$semTelefone;
            }

            $this->attestTrilhaDMinus1($agendamento->getPaciente(), 'Lembrete WhatsApp D−1 de confirmação de agenda');

            $items[] = $this->mapItem($agendamento, $result['whatsapp_url']);
        }

        if ($enviados > 0) {
            $this->em->flush();
        }

        return [
            'enviados' => $enviados,
            'ignorados' => 0,
            'sem_telefone' => $semTelefone,
            'items' => $items,
        ];
    }

    /**
     * Lembretes da Trilha nos dias relativos −7 e −3 + atestação do handoff D0.
     *
     * @return array{enviados: int, sem_telefone: int, d0: int, items: list<array<string, mixed>>}
     */
    public function prepareProtocolMilestones(Empresa $empresa): array
    {
        $enviados = 0;
        $semTelefone = 0;
        $d0 = 0;
        $items = [];

        foreach ([-7, -3] as $dia) {
            foreach ($this->pacientes->findByRelativeSurgeryDay($empresa, $dia) as $paciente) {
                if ($this->hasLembreteMarcoToday($paciente, $dia)) {
                    continue;
                }
                $item = $this->checklistItemForDia($paciente, $dia)
                    ?? sprintf('Marco %s da preparação cirúrgica', PosOperatorioPaciente::formatDiaRelativoLabel($dia));
                $result = $this->patientNotifier->notifyTrilhaMarco($paciente, $dia, $item);
                ++$enviados;
                if (($result['whatsapp_url'] ?? null) === null) {
                    ++$semTelefone;
                }
                $this->logLembreteMarco($paciente, $dia, $item);
                $items[] = [
                    'paciente_id' => $paciente->getId(),
                    'paciente' => $paciente->getNome(),
                    'codigo' => $paciente->getCodigo(),
                    'dia' => $dia,
                    'dia_label' => PosOperatorioPaciente::formatDiaRelativoLabel($dia),
                    'item' => $item,
                    'whatsapp_url' => $result['whatsapp_url'] ?? null,
                ];
            }
        }

        foreach ($this->pacientes->findByRelativeSurgeryDay($empresa, 0) as $paciente) {
            $this->attestHandoffD0($paciente);
            ++$d0;
        }

        if ($enviados > 0 || $d0 > 0) {
            $this->em->flush();
        }

        return [
            'enviados' => $enviados,
            'sem_telefone' => $semTelefone,
            'd0' => $d0,
            'items' => $items,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function panelProtocolMilestones(Empresa $empresa): array
    {
        $out = [];
        foreach ([-7, -3] as $dia) {
            foreach ($this->pacientes->findByRelativeSurgeryDay($empresa, $dia) as $paciente) {
                $item = $this->checklistItemForDia($paciente, $dia)
                    ?? sprintf('Marco %s', PosOperatorioPaciente::formatDiaRelativoLabel($dia));
                $portalHint = sprintf(
                    "Olá, %s! Trilha Unio %s: %s",
                    explode(' ', trim($paciente->getNome()))[0] ?: 'paciente',
                    PosOperatorioPaciente::formatDiaRelativoLabel($dia),
                    $item,
                );
                $out[] = [
                    'paciente_id' => $paciente->getId(),
                    'paciente' => $paciente->getNome(),
                    'codigo' => $paciente->getCodigo(),
                    'dia' => $dia,
                    'dia_label' => PosOperatorioPaciente::formatDiaRelativoLabel($dia),
                    'item' => $item,
                    'enviado_hoje' => $this->hasLembreteMarcoToday($paciente, $dia),
                    'whatsapp_url' => $this->patientNotifier->buildWhatsappLink($paciente->getTelefoneContato(), $portalHint),
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{empresas: int, enviados: int, sem_telefone: int, marcos: int, d0: int}
     */
    public function runAll(?int $empresaId = null): array
    {
        $list = $empresaId
            ? array_filter([$this->empresas->find($empresaId)])
            : $this->empresas->findBy(['ativo' => true]);

        $enviados = 0;
        $semTelefone = 0;
        $marcos = 0;
        $d0 = 0;
        $count = 0;

        foreach ($list as $empresa) {
            if (!$empresa instanceof Empresa) {
                continue;
            }
            ++$count;
            $result = $this->prepareForTomorrow($empresa);
            $enviados += $result['enviados'];
            $semTelefone += $result['sem_telefone'];
            $proto = $this->prepareProtocolMilestones($empresa);
            $marcos += $proto['enviados'];
            $semTelefone += $proto['sem_telefone'];
            $d0 += $proto['d0'];
        }

        return [
            'empresas' => $count,
            'enviados' => $enviados,
            'sem_telefone' => $semTelefone,
            'marcos' => $marcos,
            'd0' => $d0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function panelForTomorrow(Empresa $empresa): array
    {
        $dayStart = (new \DateTimeImmutable('today'))->modify('+1 day')->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');
        $list = $this->agendamentos->findForConfirmacaoPanel($empresa, $dayStart, $dayEnd);
        $out = [];

        foreach ($list as $agendamento) {
            $url = $this->patientNotifier->confirmWhatsappUrlForAgendamento($agendamento);
            $out[] = $this->mapItem($agendamento, $url);
        }

        return $out;
    }

    public function attestTrilhaDMinus1(PosOperatorioPaciente $paciente, string $evidencia): void
    {
        try {
            $contract = $this->careContracts->ensureForPaciente($paciente);
            if ($contract === null) {
                return;
            }
            $this->attestations->attestChecklistDia(
                $contract,
                -1,
                $evidencia,
                null,
                ['origem' => 'agenda_d1'],
            );
        } catch (\Throwable) {
            // Trilha é auxiliar ao lembrete de agenda.
        }
    }

    public function attestHandoffD0(PosOperatorioPaciente $paciente): void
    {
        try {
            $contract = $this->careContracts->ensureForPaciente($paciente);
            if ($contract === null) {
                return;
            }
            $this->attestations->attestChecklistDia(
                $contract,
                0,
                'Handoff automático D0 — transição pré → pós',
                null,
                ['origem' => 'handoff_d0'],
            );
        } catch (\Throwable) {
            // Handoff auxiliar.
        }
    }

    private function checklistItemForDia(PosOperatorioPaciente $paciente, int $dia): ?string
    {
        $checklist = $paciente->getProtocolo()?->getChecklist() ?? [];
        foreach ($checklist as $row) {
            if (\is_array($row) && (int) ($row['dia'] ?? 9999) === $dia) {
                return (string) ($row['item'] ?? $row['titulo'] ?? '');
            }
        }
        foreach (PosOperatorioProtocoloDefaults::checklistPreOp() as $row) {
            if ((int) $row['dia'] === $dia) {
                return (string) $row['item'];
            }
        }

        return null;
    }

    private function hasLembreteMarcoToday(PosOperatorioPaciente $paciente, int $dia): bool
    {
        $needle = 'trilha_marco_'.$dia;
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        foreach ($paciente->getEventos() as $ev) {
            if ($ev->getTipo() !== PosOperatorioEvento::TIPO_LEMBRETE) {
                continue;
            }
            if ($ev->getCriadoEm()->format('Y-m-d') !== $today) {
                continue;
            }
            if (str_contains($ev->getDescricao(), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function logLembreteMarco(PosOperatorioPaciente $paciente, int $dia, string $item): void
    {
        $ev = new PosOperatorioEvento();
        $ev->setPaciente($paciente)
            ->setTipo(PosOperatorioEvento::TIPO_LEMBRETE)
            ->setDescricao(sprintf(
                'trilha_marco_%d · %s · %s',
                $dia,
                PosOperatorioPaciente::formatDiaRelativoLabel($dia),
                $item,
            ));
        $this->em->persist($ev);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapItem(ClinicAgendamento $agendamento, ?string $whatsappUrl): array
    {
        $paciente = $agendamento->getPaciente();

        return [
            'id' => $agendamento->getId(),
            'paciente_id' => $paciente->getId(),
            'paciente' => $paciente->getNome(),
            'codigo' => $paciente->getCodigo(),
            'quando' => $agendamento->getInicio()->format('d/m/Y H:i'),
            'titulo' => $agendamento->getTitulo() ?: 'Consulta',
            'status' => $agendamento->getStatus(),
            'lembrete_em' => $agendamento->getLembreteConfirmacaoEm()?->format('d/m H:i'),
            'whatsapp_url' => $whatsappUrl,
            'telefone' => $paciente->getTelefoneContato(),
        ];
    }
}
