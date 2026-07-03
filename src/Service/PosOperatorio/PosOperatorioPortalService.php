<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
use App\Entity\PosOperatorioQuestionarioResposta;
use App\Repository\PosOperatorioQuestionarioRespostaRepository;

final class PosOperatorioPortalService
{
    public function __construct(
        private PosOperatorioQuestionarioRespostaRepository $questionarioRepo,
    ) {}

    /** @return array<string, mixed> */
    public function buildView(?PosOperatorioPaciente $paciente): array
    {
        if ($paciente === null) {
            return [
                'perguntas' => [],
                'checklist_hoje' => [],
                'questionario_hoje' => null,
                'protocolo_nome' => null,
                'medico_nome' => null,
            ];
        }

        $protocolo = $paciente->getProtocolo();
        $perguntas = $protocolo?->getPerguntas() ?: PosOperatorioProtocoloDefaults::perguntas();
        if ($perguntas === []) {
            $perguntas = PosOperatorioProtocoloDefaults::perguntas();
        }

        $diaPos = $paciente->getDiaPosOperatorio();
        $checklist = $protocolo?->getChecklist() ?: PosOperatorioProtocoloDefaults::checklistBasico();
        $checklistHoje = array_values(array_filter(
            $checklist,
            static fn (array $item) => $diaPos === null || (int) ($item['dia'] ?? 0) <= $diaPos,
        ));

        $today = new \DateTimeImmutable('today');
        $questionarioHoje = $this->questionarioRepo->findOneByPacienteAndDate($paciente, $today);

        $medico = $paciente->getMedicoResponsavel();

        return [
            'perguntas' => $perguntas,
            'checklist_hoje' => $checklistHoje,
            'checklist_proximos' => array_values(array_filter(
                $checklist,
                static fn (array $item) => $diaPos !== null && (int) ($item['dia'] ?? 0) > $diaPos,
            )),
            'questionario_hoje' => $questionarioHoje,
            'protocolo_nome' => $protocolo?->getNome(),
            'medico_nome' => $medico?->getNome(),
            'medico_email' => $medico?->getEmail(),
        ];
    }

    /** @return array<string, mixed> */
    public function mapQuestionarioResumo(PosOperatorioQuestionarioResposta $qr): array
    {
        $respostas = $qr->getRespostas();

        return [
            'score' => $qr->getScoreRisco(),
            'respondido_em' => $qr->getRespondidoEm()->format('d/m/Y H:i'),
            'dor' => $respostas['dor'] ?? null,
            'febre' => $respostas['febre'] ?? null,
            'respostas' => $respostas,
        ];
    }
}
