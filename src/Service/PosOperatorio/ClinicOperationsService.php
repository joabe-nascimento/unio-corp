<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioAlerta;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioAlertaRepository;
use App\Repository\PosOperatorioEventoRepository;
use App\Repository\PosOperatorioPacienteRepository;

/**
 * Centro de operações clínico — fila unificada, qualidade, retornos e compliance.
 */
final class ClinicOperationsService
{
    public function __construct(
        private PosOperatorioAlertQueueService $alertQueue,
        private PosOperatorioQuestionarioListService $questionarios,
        private PosOperatorioPacienteRepository $pacientes,
        private PosOperatorioAlertaRepository $alertas,
        private PosOperatorioEventoRepository $eventos,
    ) {}

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     stats: array{total: int, p1: int, questionarios: int, retornos: int, sla: int}
     * }
     */
    public function buildWorkQueue(Empresa $empresa): array
    {
        $items = [];
        $today = new \DateTimeImmutable('today');

        $queue = $this->alertQueue->buildQueue($empresa);
        foreach ($queue['tickets'] as $ticket) {
            if (!\in_array($ticket['status'], [PosOperatorioAlerta::STATUS_ABERTO, PosOperatorioAlerta::STATUS_EM_ATENDIMENTO], true)) {
                continue;
            }
            $priority = 0;
            if ($ticket['prioridade'] === 'P1') {
                $priority = 100;
            } elseif ($ticket['prioridade'] === 'P2') {
                $priority = 80;
            } elseif ($ticket['sla_breach_imminent'] ?? false) {
                $priority = 70;
            } else {
                $priority = 50;
            }

            $items[] = [
                'priority' => $priority,
                'type' => 'alerta',
                'type_label' => 'Alerta ' . $ticket['prioridade'],
                'title' => $ticket['paciente_nome'],
                'subtitle' => $ticket['motivo'],
                'meta' => $ticket['paciente_codigo'] . ($ticket['dia_pos'] !== null ? ' · D+' . $ticket['dia_pos'] : ''),
                'tone' => $ticket['prioridade'] === 'P1' ? 'danger' : ($ticket['prioridade'] === 'P2' ? 'warning' : 'sky'),
                'route' => $ticket['prioridade'] === 'P1' ? 'app_pos_operatorio_sala_critica' : 'app_pos_operatorio_alertas',
                'route_params' => [],
                'action_label' => $ticket['status'] === PosOperatorioAlerta::STATUS_EM_ATENDIMENTO ? 'Continuar' : 'Assumir',
                'paciente_id' => $ticket['paciente_id'],
            ];
        }

        foreach ($this->pacientes->findActiveWithoutQuestionarioToday($empresa, $today) as $paciente) {
            $items[] = [
                'priority' => 60,
                'type' => 'questionario',
                'type_label' => 'Questionário pendente',
                'title' => $paciente->getNome(),
                'subtitle' => 'Sem resposta hoje no portal',
                'meta' => $paciente->getCodigo() . ' · D+' . ($paciente->getDiaPosOperatorio() ?? '—'),
                'tone' => 'amber',
                'route' => 'app_pos_operatorio_pacientes',
                'route_params' => ['open_ficha' => $paciente->getId()],
                'action_label' => 'Abrir ficha',
                'paciente_id' => $paciente->getId(),
            ];
        }

        foreach ($this->buildReturns($empresa)['items'] as $retorno) {
            if (!($retorno['hoje'] ?? false)) {
                continue;
            }
            $items[] = [
                'priority' => 55,
                'type' => 'retorno',
                'type_label' => 'Retorno previsto',
                'title' => $retorno['paciente_nome'],
                'subtitle' => $retorno['marco'],
                'meta' => $retorno['paciente_codigo'] . ' · D+' . $retorno['dia_pos'],
                'tone' => 'teal',
                'route' => 'app_pos_operatorio_pacientes',
                'route_params' => ['open_ficha' => $retorno['paciente_id']],
                'action_label' => 'Ver paciente',
                'paciente_id' => $retorno['paciente_id'],
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        $p1 = $queue['stats']['p1'];
        $pendentes = $this->questionarios->buildList($empresa, 1)['stats']['pendentes'];

        return [
            'items' => $items,
            'stats' => [
                'total' => \count($items),
                'p1' => $p1,
                'questionarios' => $pendentes,
                'retornos' => \count(array_filter($items, static fn (array $i) => $i['type'] === 'retorno')),
                'sla' => \count(array_filter($queue['tickets'], static fn (array $t) => $t['sla_breach_imminent'] ?? false)),
            ],
        ];
    }

    /** @return array{items: list<array<string, mixed>>, proximos_7_dias: int} */
    public function buildReturns(Empresa $empresa): array
    {
        $items = [];
        $today = new \DateTimeImmutable('today');

        foreach ($this->pacientes->findRecentByEmpresa($empresa, 200, 0) as $paciente) {
            $diaPos = $paciente->getDiaPosOperatorio($today);
            if ($diaPos === null) {
                continue;
            }
            $protocolo = $paciente->getProtocolo();
            $marcos = $protocolo?->getChecklist() ?? [];
            foreach ($marcos as $marco) {
                $diaMarco = (int) ($marco['dia'] ?? 0);
                if ($diaMarco <= 0) {
                    continue;
                }
                $diff = $diaMarco - $diaPos;
                if ($diff < -2 || $diff > 7) {
                    continue;
                }
                $items[] = [
                    'paciente_id' => $paciente->getId(),
                    'paciente_nome' => $paciente->getNome(),
                    'paciente_codigo' => $paciente->getCodigo(),
                    'procedimento' => $paciente->getProcedimento() ?? '—',
                    'dia_pos' => $diaPos,
                    'dia_marco' => $diaMarco,
                    'marco' => (string) ($marco['item'] ?? 'Marco do protocolo'),
                    'quando' => $diff === 0 ? 'Hoje' : ($diff > 0 ? 'Em ' . $diff . ' dia(s)' : 'Há ' . abs($diff) . ' dia(s)'),
                    'hoje' => $diff === 0,
                    'urgente' => $diff <= 0,
                ];
            }
        }

        usort($items, static fn (array $a, array $b): int => ($a['dia_marco'] - $a['dia_pos']) <=> ($b['dia_marco'] - $b['dia_pos']));

        return [
            'items' => $items,
            'proximos_7_dias' => \count($items),
        ];
    }

    /** @return array<string, mixed> */
    public function buildQuality(Empresa $empresa): array
    {
        $today = new \DateTimeImmutable('today');
        $qStats = $this->questionarios->buildList($empresa, 50)['stats'];
        $ativos = $this->pacientes->countAtivosByEmpresa($empresa);
        $pendentesHoje = $this->pacientes->findActiveWithoutQuestionarioToday($empresa, $today);
        $respondidos = $qStats['hoje'];
        $totalEsperado = $respondidos + \count($pendentesHoje);
        $taxaResposta = $totalEsperado > 0 ? (int) round(($respondidos / $totalEsperado) * 100) : 100;

        $abertos = $this->alertas->findAbertosByEmpresa($empresa, 200);
        $slaOk = 0;
        foreach ($abertos as $alerta) {
            if ($alerta->getSlaLimiteEm() === null || $alerta->getSlaLimiteEm() > new \DateTimeImmutable()) {
                ++$slaOk;
            }
        }
        $slaPct = \count($abertos) > 0 ? (int) round(($slaOk / \count($abertos)) * 100) : 100;

        $heatmap = [];
        foreach ($this->pacientes->findRecentByEmpresa($empresa, 100, 0) as $p) {
            $dia = $p->getDiaPosOperatorio();
            if ($dia === null) {
                continue;
            }
            $bucket = 'D+' . min($dia, 14);
            $heatmap[$bucket] = ($heatmap[$bucket] ?? 0) + 1;
        }
        ksort($heatmap);

        return [
            'taxa_resposta' => $taxaResposta,
            'respondidos_hoje' => $respondidos,
            'pendentes_hoje' => \count($pendentesHoje),
            'pacientes_ativos' => $ativos,
            'alertas_abertos' => \count($abertos),
            'sla_pct' => $slaPct,
            'heatmap' => $heatmap,
        ];
    }

    /** @return array<string, mixed> */
    public function buildReminders(Empresa $empresa): array
    {
        $today = new \DateTimeImmutable('today');
        $pendentes = $this->pacientes->findActiveWithoutQuestionarioToday($empresa, $today);
        $canais = [
            ['id' => 'in_app', 'nome' => 'Notificação in-app (médico)', 'status' => 'active', 'desc' => 'Ativo via cron diário'],
            ['id' => 'email', 'nome' => 'E-mail ao paciente', 'status' => 'planned', 'desc' => 'Lembrete automático às 18h'],
            ['id' => 'whatsapp', 'nome' => 'WhatsApp', 'status' => 'planned', 'desc' => 'Requer integração WhatsApp Business'],
            ['id' => 'sms', 'nome' => 'SMS', 'status' => 'planned', 'desc' => 'Canal alternativo para pacientes sem app'],
        ];

        return [
            'pendentes_hoje' => \count($pendentes),
            'pacientes' => array_map(static fn (PosOperatorioPaciente $p) => [
                'id' => $p->getId(),
                'codigo' => $p->getCodigo(),
                'nome' => $p->getNome(),
                'dia_pos' => $p->getDiaPosOperatorio(),
                'telefone' => $p->getTelefoneContato(),
                'email' => $p->getEmailContato(),
                'medico' => $p->getMedicoResponsavel()?->getNome(),
            ], array_slice($pendentes, 0, 20)),
            'canais' => $canais,
            'escalacao' => [
                ['horas' => 4, 'acao' => 'Notificar médico responsável', 'status' => 'active'],
                ['horas' => 8, 'acao' => 'Notificar coordenação', 'status' => 'planned'],
                ['horas' => 24, 'acao' => 'Ligar para paciente / familiar', 'status' => 'planned'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function buildPlantao(Empresa $empresa): array
    {
        $usuarios = [];
        foreach ($this->pacientes->findRecentByEmpresa($empresa, 50, 0) as $p) {
            $m = $p->getMedicoResponsavel();
            if ($m instanceof User && !isset($usuarios[$m->getId()])) {
                $usuarios[$m->getId()] = [
                    'id' => $m->getId(),
                    'nome' => $m->getNome(),
                    'email' => $m->getEmail(),
                    'pacientes' => 0,
                ];
            }
            if ($m instanceof User) {
                ++$usuarios[$m->getId()]['pacientes'];
            }
        }

        return [
            'plantonistas' => array_values($usuarios),
            'sla_p1_min' => 15,
            'sla_p2_min' => 60,
            'sla_p3_min' => 240,
            'sla_p4_min' => 1440,
            'roteamento' => 'P1 → plantonista do dia · demais → médico responsável',
        ];
    }

    /** @return array<string, mixed> */
    public function buildReports(Empresa $empresa): array
    {
        $quality = $this->buildQuality($empresa);
        $queue = $this->alertQueue->buildQueue($empresa);

        return [
            'resumo' => [
                ['value' => $quality['pacientes_ativos'], 'label' => 'Pacientes ativos', 'icon' => 'fa-user-injured', 'tone' => 'sky'],
                ['value' => $quality['taxa_resposta'] . '%', 'label' => 'Taxa resposta hoje', 'icon' => 'fa-percent', 'tone' => 'sage'],
                ['value' => $quality['sla_pct'] . '%', 'label' => 'SLA em dia', 'icon' => 'fa-stopwatch', 'tone' => 'lavender'],
                ['value' => $queue['stats']['p1'], 'label' => 'P1 abertos', 'icon' => 'fa-fire', 'tone' => 'rose', 'variant' => $queue['stats']['p1'] > 0 ? 'danger' : ''],
            ],
            'exports' => [
                ['id' => 'fichas', 'nome' => 'Fichas de pacientes', 'formato' => 'PDF', 'status' => 'active'],
                ['id' => 'questionarios', 'nome' => 'Questionários do período', 'formato' => 'CSV', 'status' => 'active'],
                ['id' => 'alertas', 'nome' => 'Histórico de alertas', 'formato' => 'CSV', 'status' => 'active'],
                ['id' => 'auditoria', 'nome' => 'Trilha LGPD', 'formato' => 'CSV', 'status' => 'active'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function buildCompliance(Empresa $empresa): array
    {
        $eventos = $this->eventos->findRecentByEmpresa($empresa, 30);
        $auditoria = [];
        foreach ($eventos as $ev) {
            $tipo = $ev->getTipo();
            if (!\in_array($tipo, [
                PosOperatorioEvento::TIPO_ACESSO_FICHA,
                PosOperatorioEvento::TIPO_CONSENTIMENTO,
                PosOperatorioEvento::TIPO_CADASTRO,
            ], true)) {
                continue;
            }
            $autor = $ev->getAutor()?->getNome() ?? 'Sistema';
            $auditoria[] = [
                'tipo' => $tipo,
                'label' => match ($tipo) {
                    PosOperatorioEvento::TIPO_ACESSO_FICHA => 'Acesso à ficha',
                    PosOperatorioEvento::TIPO_CONSENTIMENTO => 'Consentimento LGPD',
                    default => 'Cadastro',
                },
                'detalhe' => match ($tipo) {
                    PosOperatorioEvento::TIPO_ACESSO_FICHA => sprintf('Ficha visualizada por %s', $autor),
                    default => $ev->getDescricao(),
                },
                'autor' => $autor,
                'em' => $ev->getCriadoEm()->format('d/m/Y H:i'),
                'paciente' => $ev->getPaciente()->getCodigo(),
            ];
        }

        $semConsentimento = 0;
        foreach ($this->pacientes->findRecentByEmpresa($empresa, 200, 0) as $p) {
            if ($p->getConsentimentoLgpdEm() === null && $p->getPortalUser() !== null) {
                ++$semConsentimento;
            }
        }

        return [
            'auditoria' => $auditoria,
            'sem_consentimento' => $semConsentimento,
            'politicas' => [
                ['titulo' => 'Consentimento versionado', 'status' => 'active', 'desc' => 'Registro no portal do paciente'],
                ['titulo' => 'Trilha de acesso à ficha', 'status' => 'active', 'desc' => 'Cada visualização gera evento'],
                ['titulo' => 'Retenção e anonimização', 'status' => 'active', 'desc' => 'Encerramento arquiva ficha; exportação sob demanda'],
                ['titulo' => 'Exportação para auditoria', 'status' => 'active', 'desc' => 'Relatório PDF sob demanda'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function buildConfig(): array
    {
        return [
            'sla' => [
                ['prioridade' => 'P1', 'minutos' => 15, 'desc' => 'Risco imediato'],
                ['prioridade' => 'P2', 'minutos' => 60, 'desc' => 'Atenção em 1 hora'],
                ['prioridade' => 'P3', 'minutos' => 240, 'desc' => 'Triagem em 4 horas'],
                ['prioridade' => 'P4', 'minutos' => 1440, 'desc' => 'Acompanhamento em 24h'],
            ],
            'regras' => [
                ['titulo' => 'Dor ≥ limiar → P1', 'status' => 'active'],
                ['titulo' => 'Febre ≥ limiar → P2', 'status' => 'active'],
                ['titulo' => 'Score composto (dor + febre + náusea)', 'status' => 'active'],
                ['titulo' => 'Perfil de risco (observações clínicas)', 'status' => 'active'],
            ],
            'perfis' => [
                ['perfil' => 'Recepção', 'acesso' => 'Cadastro de pacientes'],
                ['perfil' => 'Enfermagem', 'acesso' => 'Triagem e questionários'],
                ['perfil' => 'Médico', 'acesso' => 'Alertas, ficha e protocolos'],
                ['perfil' => 'Coordenação', 'acesso' => 'Relatórios e configurações'],
            ],
        ];
    }
}
