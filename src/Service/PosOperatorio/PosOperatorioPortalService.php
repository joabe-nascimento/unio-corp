<?php

namespace App\Service\PosOperatorio;

use App\Entity\PosOperatorioAlerta;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\PosOperatorioQuestionarioResposta;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioEventoRepository;
use App\Repository\PosOperatorioQuestionarioRespostaRepository;

final class PosOperatorioPortalService
{
    public function __construct(
        private PosOperatorioQuestionarioRespostaRepository $questionarioRepo,
        private PosOperatorioEventoRepository $eventoRepo,
        private PosOperatorioAlertaRepository $alertaRepo,
        private PosOperatorioMedicalGuideService $medicalGuide,
    ) {}

    /** @return array<string, mixed> */
    public function buildView(?PosOperatorioPaciente $paciente): array
    {
        if ($paciente === null) {
            return $this->emptyView();
        }

        $protocolo = $paciente->getProtocolo();
        $isPreOp = $paciente->isPreOperatorio();
        $perguntas = $protocolo?->getPerguntas() ?: [];
        if ($perguntas === []) {
            $perguntas = $isPreOp
                ? PosOperatorioProtocoloDefaults::perguntasPreOp()
                : PosOperatorioProtocoloDefaults::perguntas();
        } elseif ($isPreOp && !$this->looksLikePreOpQuestions($perguntas)) {
            $perguntas = PosOperatorioProtocoloDefaults::perguntasPreOp();
        }

        $diaRelativo = $paciente->getDiaRelativoCirurgia();
        $diaPos = $paciente->getDiaPosOperatorio();
        $duracaoDias = $protocolo?->getDuracaoDias() ?? 14;
        $checklist = $protocolo?->getChecklist() ?: (
            $isPreOp
                ? array_merge(PosOperatorioProtocoloDefaults::checklistPreOp(), PosOperatorioProtocoloDefaults::checklistBasico())
                : PosOperatorioProtocoloDefaults::checklistBasico()
        );
        $checklistParts = $this->splitChecklist($checklist, $diaRelativo);

        $today = new \DateTimeImmutable('today');
        $questionarioHoje = $this->questionarioRepo->findOneByPacienteAndDate($paciente, $today);
        $historico = array_map(
            fn (PosOperatorioQuestionarioResposta $qr) => $this->mapQuestionarioResumo($qr),
            $this->questionarioRepo->findRecentByPaciente($paciente, 7),
        );

        $medico = $paciente->getMedicoResponsavel();
        $procedimentoLabel = $this->procedimentoLabel($paciente);
        $retornoHoje = $this->findRetornoHoje($checklistParts['hoje']);
        $checklistProgress = $this->mapChecklistProgress($checklist, $diaRelativo);

        return [
            'perguntas' => $perguntas,
            'checklist_hoje' => $checklistParts['hoje'],
            'checklist_proximos' => $checklistParts['proximos'],
            'checklist_concluidos' => $checklistParts['concluidos'],
            'checklist_progress' => $checklistProgress,
            'questionario_hoje' => $questionarioHoje,
            'historico_questionarios' => $historico,
            'protocolo_nome' => $protocolo?->getNome(),
            'procedimento_label' => $procedimentoLabel,
            'duracao_dias' => $duracaoDias,
            'progress_pct' => $this->progressPct($diaRelativo, $duracaoDias, $checklist),
            'fase_label' => $this->faseLabel($diaRelativo, $duracaoDias, $checklistParts, $isPreOp),
            'data_cirurgia_label' => $paciente->getDataCirurgia()?->format('d/m/Y'),
            'status_label' => $paciente->isDiaCirurgia()
                ? 'Dia da cirurgia · handoff pré → pós'
                : ($isPreOp ? 'Preparação para a cirurgia' : $this->statusLabel($paciente->getStatus())),
            'is_pre_op' => $isPreOp,
            'is_d0' => $paciente->isDiaCirurgia(),
            'handoff_label' => $paciente->isDiaCirurgia()
                ? 'É o dia da sua cirurgia. A Trilha Unio passa da preparação para o acompanhamento pós-op.'
                : null,
            'dia_relativo' => $diaRelativo,
            'dia_relativo_label' => $diaRelativo !== null ? PosOperatorioPaciente::formatDiaRelativoLabel($diaRelativo) : null,
            'medico_nome' => $medico?->getNome(),
            'medico_email' => $medico?->getEmail(),
            'clinica_nome' => $paciente->getEmpresa()->getNome(),
            'contato_emergencia' => $paciente->getContatoEmergencia(),
            'telefone_emergencia' => $paciente->getTelefoneEmergencia(),
            'alerta_equipe' => $this->mapAlertaEquipe($paciente),
            'dias_respondidos' => $this->countDiasRespondidos($paciente),
            'mensagens_equipe' => $this->mapMensagensEquipe($paciente),
            'retorno_hoje' => $retornoHoje,
            'retorno_confirmado' => $retornoHoje !== null && $this->eventoRepo->hasRetornoConfirmadoOnDate($paciente, $today),
            'ajuda_pendente' => $this->alertaRepo->hasOpenAlertWithMotivo($paciente, 'Paciente solicitou ajuda pelo portal'),
            'guia_medico' => $isPreOp ? null : $this->medicalGuide->buildGuia($paciente, $diaPos, $checklistProgress),
        ];
    }

    /** @return array<string, mixed> */
    public function mapQuestionarioResumo(PosOperatorioQuestionarioResposta $qr): array
    {
        $respostas = $qr->getRespostas();
        $diaRef = $qr->getPaciente()->getDiaRelativoCirurgia($qr->getDataReferencia());

        return [
            'score' => $qr->getScoreRisco(),
            'respondido_em' => $qr->getRespondidoEm()->format('d/m/Y H:i'),
            'data_label' => $qr->getDataReferencia()->format('d/m/Y'),
            'dia_pos' => $diaRef !== null && $diaRef >= 0 ? $diaRef : null,
            'dia_relativo' => $diaRef,
            'dia_relativo_label' => $diaRef !== null ? PosOperatorioPaciente::formatDiaRelativoLabel($diaRef) : null,
            'dor' => $respostas['dor'] ?? null,
            'febre' => $respostas['febre'] ?? null,
            'respostas' => $respostas,
        ];
    }

    /** @return array<string, mixed> */
    private function emptyView(): array
    {
        return [
            'perguntas' => [],
            'checklist_hoje' => [],
            'checklist_proximos' => [],
            'checklist_concluidos' => [],
            'checklist_progress' => [],
            'questionario_hoje' => null,
            'historico_questionarios' => [],
            'protocolo_nome' => null,
            'procedimento_label' => null,
            'duracao_dias' => null,
            'progress_pct' => 0,
            'fase_label' => null,
            'data_cirurgia_label' => null,
            'status_label' => null,
            'is_pre_op' => false,
            'is_d0' => false,
            'handoff_label' => null,
            'dia_relativo' => null,
            'dia_relativo_label' => null,
            'medico_nome' => null,
            'medico_email' => null,
            'clinica_nome' => null,
            'contato_emergencia' => null,
            'telefone_emergencia' => null,
            'alerta_equipe' => null,
            'dias_respondidos' => 0,
            'mensagens_equipe' => [],
            'retorno_hoje' => null,
            'retorno_confirmado' => false,
            'ajuda_pendente' => false,
            'guia_medico' => null,
        ];
    }

    private function procedimentoLabel(PosOperatorioPaciente $paciente): string
    {
        $fromField = trim((string) ($paciente->getProcedimento() ?? ''));
        if ($fromField !== '') {
            return $fromField;
        }

        $fromProtocol = trim((string) ($paciente->getProtocolo()?->getNome() ?? ''));

        return $fromProtocol !== '' ? $fromProtocol : 'Procedimento';
    }

    /**
     * @param list<array<string, mixed>> $checklist
     *
     * @return array{hoje: list<array<string, mixed>>, proximos: list<array<string, mixed>>, concluidos: list<array<string, mixed>>}
     */
    private function splitChecklist(array $checklist, ?int $diaPos): array
    {
        $hoje = [];
        $proximos = [];
        $concluidos = [];

        if ($diaPos === null) {
            return ['hoje' => [], 'proximos' => $checklist, 'concluidos' => []];
        }

        foreach ($checklist as $item) {
            $dia = (int) ($item['dia'] ?? 0);
            if ($dia < $diaPos) {
                $concluidos[] = $item;
            } elseif ($dia === $diaPos) {
                $hoje[] = $item;
            } else {
                $proximos[] = $item;
            }
        }

        if ($hoje === [] && $proximos !== []) {
            $hoje[] = array_shift($proximos);
        }

        return compact('hoje', 'proximos', 'concluidos');
    }

    /**
     * @param list<array<string, mixed>> $checklist
     *
     * @return list<array{dia: int, item: string, done: bool, current: bool}>
     */
    private function mapChecklistProgress(array $checklist, ?int $diaPos): array
    {
        $currentDia = null;
        if ($diaPos !== null) {
            foreach ($checklist as $item) {
                $dia = (int) ($item['dia'] ?? 0);
                if ($dia >= $diaPos) {
                    $currentDia = $dia;
                    break;
                }
            }
        }

        return array_map(static function (array $item) use ($diaPos, $currentDia): array {
            $dia = (int) ($item['dia'] ?? 0);

            return [
                'dia' => $dia,
                'item' => (string) ($item['item'] ?? ''),
                'done' => $diaPos !== null && $dia < $diaPos,
                'current' => $currentDia !== null && $dia === $currentDia,
            ];
        }, $checklist);
    }

    /** @param array{hoje: list<array<string, mixed>>, proximos: list<array<string, mixed>>} $parts */
    private function faseLabel(?int $diaRelativo, int $duracaoDias, array $parts, bool $isPreOp): ?string
    {
        if ($diaRelativo === null) {
            return null;
        }

        if ($diaRelativo === 0) {
            return 'Handoff: dia da cirurgia — inicia o acompanhamento pós-operatório';
        }

        if ($isPreOp) {
            if ($parts['hoje'] !== []) {
                return 'Preparação: '.(string) ($parts['hoje'][0]['item'] ?? 'checklist pré-op');
            }

            return 'Preparação para a cirurgia';
        }

        if ($parts['hoje'] !== []) {
            return (string) ($parts['hoje'][0]['item'] ?? null);
        }

        if ($diaRelativo >= $duracaoDias) {
            return 'Fase final do protocolo';
        }

        if ($parts['proximos'] !== []) {
            return 'Próximo: ' . (string) ($parts['proximos'][0]['item'] ?? 'marco clínico');
        }

        return 'Recuperação em andamento';
    }

    /** @param list<array<string, mixed>> $perguntas */
    private function looksLikePreOpQuestions(array $perguntas): bool
    {
        foreach ($perguntas as $p) {
            $id = (string) ($p['id'] ?? '');
            if (\in_array($id, ['preparado', 'jejum', 'medicamentos'], true)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $checklist */
    private function progressPct(?int $diaRelativo, int $duracaoDias, array $checklist): int
    {
        if ($diaRelativo === null || $checklist === []) {
            return 0;
        }

        $done = 0;
        foreach ($checklist as $item) {
            if ((int) ($item['dia'] ?? 0) < $diaRelativo) {
                ++$done;
            }
        }

        return min(100, (int) round(($done / max(1, \count($checklist))) * 100));
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            PosOperatorioPaciente::STATUS_ALERTA => 'Monitoramento intensivo',
            PosOperatorioPaciente::STATUS_PENDENTE => 'Aguardando dados',
            PosOperatorioPaciente::STATUS_ENCERRADO => 'Acompanhamento encerrado',
            default => 'Recuperação ativa',
        };
    }

    /** @return array{ativo: bool, em_atendimento: bool, mensagem: string}|null */
    private function mapAlertaEquipe(PosOperatorioPaciente $paciente): ?array
    {
        foreach ($paciente->getAlertas() as $alerta) {
            if (!\in_array($alerta->getStatus(), [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO], true)) {
                continue;
            }

            $emAtendimento = $alerta->getStatus() === PosOperatorioAlerta::STATUS_EM_ATENDIMENTO;

            return [
                'ativo' => true,
                'em_atendimento' => $emAtendimento,
                'mensagem' => $emAtendimento
                    ? 'Nossa equipe está analisando seus sinais agora. Fique atento ao telefone.'
                    : (str_contains(mb_strtolower($alerta->getMotivo()), 'ajuda pelo portal')
                        ? 'Recebemos seu pedido de ajuda. A equipe entrará em contato em breve.'
                        : 'Identificamos sinais que precisam de atenção. Responda o questionário ou fale com a clínica.'),
            ];
        }

        return null;
    }

    /** @return list<array{data_label: string, hora: string, texto: string, origem: string, tipo: string}> */
    private function mapMensagensEquipe(PosOperatorioPaciente $paciente): array
    {
        $portalUserId = $paciente->getPortalUser()?->getId();

        return array_map(function (PosOperatorioEvento $ev) use ($portalUserId): array {
            $autor = $ev->getAutor();
            $isPatient = $portalUserId !== null && $autor !== null && $autor->getId() === $portalUserId;

            return [
                'data_label' => $ev->getCriadoEm()->format('d/m/Y'),
                'hora' => $ev->getCriadoEm()->format('H:i'),
                'texto' => $ev->getDescricao(),
                'origem' => $isPatient ? 'Você' : ($autor?->getNome() ?? 'Equipe clínica'),
                'tipo' => $ev->getTipo(),
            ];
        }, $this->eventoRepo->findVisibleToPatient($paciente, 12));
    }

    /**
     * @param list<array<string, mixed>> $hoje
     *
     * @return array{dia: int, item: string}|null
     */
    private function findRetornoHoje(array $hoje): ?array
    {
        foreach ($hoje as $item) {
            $text = mb_strtolower((string) ($item['item'] ?? ''));
            if (str_contains($text, 'retorno')) {
                return [
                    'dia' => (int) ($item['dia'] ?? 0),
                    'item' => (string) ($item['item'] ?? ''),
                ];
            }
        }

        return null;
    }

    private function countDiasRespondidos(PosOperatorioPaciente $paciente): int
    {
        return $paciente->getQuestionarios()->count();
    }
}
