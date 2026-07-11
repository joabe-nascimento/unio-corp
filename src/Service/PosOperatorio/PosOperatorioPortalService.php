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
        $perguntas = $protocolo?->getPerguntas() ?: PosOperatorioProtocoloDefaults::perguntas();
        if ($perguntas === []) {
            $perguntas = PosOperatorioProtocoloDefaults::perguntas();
        }

        $diaPos = $paciente->getDiaPosOperatorio();
        $duracaoDias = $protocolo?->getDuracaoDias() ?? 14;
        $checklist = $protocolo?->getChecklist() ?: PosOperatorioProtocoloDefaults::checklistBasico();
        $checklistParts = $this->splitChecklist($checklist, $diaPos);

        $today = new \DateTimeImmutable('today');
        $questionarioHoje = $this->questionarioRepo->findOneByPacienteAndDate($paciente, $today);
        $historico = array_map(
            fn (PosOperatorioQuestionarioResposta $qr) => $this->mapQuestionarioResumo($qr),
            $this->questionarioRepo->findRecentByPaciente($paciente, 7),
        );

        $medico = $paciente->getMedicoResponsavel();
        $procedimentoLabel = $this->procedimentoLabel($paciente);
        $retornoHoje = $this->findRetornoHoje($checklistParts['hoje']);
        $checklistProgress = $this->mapChecklistProgress($checklist, $diaPos);

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
            'progress_pct' => $diaPos !== null ? min(100, (int) round(($diaPos / max(1, $duracaoDias)) * 100)) : 0,
            'fase_label' => $this->faseLabel($diaPos, $duracaoDias, $checklistParts),
            'data_cirurgia_label' => $paciente->getDataCirurgia()?->format('d/m/Y'),
            'status_label' => $this->statusLabel($paciente->getStatus()),
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
            'guia_medico' => $this->medicalGuide->buildGuia($paciente, $diaPos, $checklistProgress),
        ];
    }

    /** @return array<string, mixed> */
    public function mapQuestionarioResumo(PosOperatorioQuestionarioResposta $qr): array
    {
        $respostas = $qr->getRespostas();
        $diaRef = $qr->getPaciente()->getDiaPosOperatorio($qr->getDataReferencia());

        return [
            'score' => $qr->getScoreRisco(),
            'respondido_em' => $qr->getRespondidoEm()->format('d/m/Y H:i'),
            'data_label' => $qr->getDataReferencia()->format('d/m/Y'),
            'dia_pos' => $diaRef,
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
    private function faseLabel(?int $diaPos, int $duracaoDias, array $parts): ?string
    {
        if ($diaPos === null) {
            return null;
        }

        if ($parts['hoje'] !== []) {
            return (string) ($parts['hoje'][0]['item'] ?? null);
        }

        if ($diaPos >= $duracaoDias) {
            return 'Fase final do protocolo';
        }

        if ($parts['proximos'] !== []) {
            return 'Próximo: ' . (string) ($parts['proximos'][0]['item'] ?? 'marco clínico');
        }

        return 'Recuperação em andamento';
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
