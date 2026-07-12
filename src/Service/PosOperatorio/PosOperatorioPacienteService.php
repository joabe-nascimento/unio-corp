<?php



namespace App\Service\PosOperatorio;



use App\Entity\Empresa;

use App\Entity\PosOperatorioAlerta;

use App\Entity\PosOperatorioEvento;

use App\Entity\PosOperatorioPaciente;

use App\Entity\PosOperatorioQuestionarioResposta;

use App\Entity\User;

use App\PosOperatorio\PosOperatorioDisplay;
use App\PosOperatorio\PosOperatorioTimelineFormatter;
use App\Support\BrPersonFormat;

use App\Repository\PosOperatorioPacienteRepository;

use App\Repository\PosOperatorioProtocoloRepository;

use App\Repository\UserRepository;

use Doctrine\ORM\EntityManagerInterface;



final class PosOperatorioPacienteService

{

    public function __construct(

        private EntityManagerInterface $em,

        private PosOperatorioPacienteRepository $repository,

        private PosOperatorioProtocoloRepository $protocoloRepo,

        private UserRepository $userRepo,

        private PosOperatorioEventRecorder $events,

    ) {}



    /** @return list<PosOperatorioPaciente> */

    public function listByEmpresa(Empresa $empresa, int $limit = 100): array

    {

        return $this->repository->findRecentByEmpresa($empresa, $limit, 0);

    }



    /** @return array<int, true> */

    public function silentTodayIds(Empresa $empresa): array

    {

        $today = new \DateTimeImmutable('today');

        $ids = [];

        foreach ($this->repository->findActiveWithoutQuestionarioToday($empresa, $today) as $paciente) {

            $ids[(int) $paciente->getId()] = true;

        }



        return $ids;

    }



    public function recordEvolucao(PosOperatorioPaciente $paciente, User $autor, string $texto): void

    {

        $texto = trim($texto);

        if ($texto === '') {

            throw new \InvalidArgumentException('Nota de evolução vazia.');

        }



        $this->events->record(

            $paciente,

            PosOperatorioEvento::TIPO_EVOLUCAO,

            mb_substr($texto, 0, 2000),

            $autor,

        );

        $this->em->flush();

    }



    public function findForEmpresa(Empresa $empresa, int $id): ?PosOperatorioPaciente

    {

        $p = $this->repository->find($id);



        return ($p instanceof PosOperatorioPaciente && $p->getEmpresa()->getId() === $empresa->getId()) ? $p : null;

    }



    public function create(Empresa $empresa, array $data, User $autor): PosOperatorioPaciente

    {

        $paciente = (new PosOperatorioPaciente())

            ->setEmpresa($empresa)

            ->setCodigo($this->nextCodigo($empresa))

            ->setNome(trim((string) ($data['nome'] ?? '')))

            ->setTelefoneContato($this->normalizePhone((string) ($data['telefone'] ?? '')))

            ->setStatus(PosOperatorioPaciente::STATUS_ATIVO);



        $this->applyRelations($paciente, $empresa, $data);

        $this->applyProcedimentoFromProtocolo($paciente, $data);

        $this->applyContactExtras($paciente, $data);
        $this->applyCpf($paciente, $empresa, $data);

        $this->applyDataCirurgia($paciente, (string) ($data['data_cirurgia'] ?? ''));
        $this->applyDataNascimento($paciente, $data);



        $this->em->persist($paciente);

        $this->events->record($paciente, PosOperatorioEvento::TIPO_CADASTRO, 'Paciente cadastrado', $autor);

        $this->em->flush();



        return $paciente;

    }



    public function update(PosOperatorioPaciente $paciente, array $data, User $autor): void

    {

        if (($nome = trim((string) ($data['nome'] ?? ''))) !== '') {

            $paciente->setNome($nome);

        }

        $paciente->setProcedimento(trim((string) ($data['procedimento'] ?? '')) ?: null);

        $paciente->setTelefoneContato($this->normalizePhone((string) ($data['telefone'] ?? '')));



        if (isset($data['status']) && \in_array($data['status'], [

            PosOperatorioPaciente::STATUS_ATIVO,

            PosOperatorioPaciente::STATUS_PENDENTE,

            PosOperatorioPaciente::STATUS_ENCERRADO,

        ], true)) {

            $paciente->setStatus($data['status']);

        }



        $this->applyRelations($paciente, $paciente->getEmpresa(), $data);

        $this->applyProcedimentoFromProtocolo($paciente, $data);

        $this->applyContactExtras($paciente, $data);
        $this->applyCpf($paciente, $paciente->getEmpresa(), $data);

        $this->applyDataCirurgia($paciente, (string) ($data['data_cirurgia'] ?? ''));
        $this->applyDataNascimento($paciente, $data);



        $this->events->record($paciente, PosOperatorioEvento::TIPO_CADASTRO, 'Ficha atualizada', $autor);

        $this->em->flush();

    }



    /** @return array<string, mixed> */

    public function buildFicha(PosOperatorioPaciente $paciente): array

    {

        $timeline = [];

        foreach ($paciente->getEventos()->slice(0, 20) as $ev) {

            $timeline[] = $this->mapEvento($ev);

        }

        $timelineGroups = $this->groupTimelineByDate($timeline);



        $questionarios = [];

        foreach ($paciente->getQuestionarios()->slice(0, 8) as $qr) {

            if (!$qr instanceof PosOperatorioQuestionarioResposta) {

                continue;

            }

            $questionarios[] = [

                'data' => $qr->getDataReferencia()->format('d/m/Y'),

                'score' => $qr->getScoreRisco(),

                'respondido_em' => $qr->getRespondidoEm()->format('d/m/Y H:i'),

                'dor' => $qr->getRespostas()['dor'] ?? null,

                'febre' => $qr->getRespostas()['febre'] ?? null,

            ];

        }



        $alertas = [];

        foreach ($paciente->getAlertas() as $alerta) {

            if ($alerta->getStatus() === PosOperatorioAlerta::STATUS_RESOLVIDO) {

                continue;

            }

            $alertas[] = [

                'id' => $alerta->getId(),

                'prioridade' => $alerta->getPrioridade(),

                'motivo' => $alerta->getMotivo(),

                'status' => $alerta->getStatus(),

                'criado_em' => $alerta->getCriadoEm()->format('d/m/Y H:i'),

            ];

        }



        $portalUser = $paciente->getPortalUser();

        $today = new \DateTimeImmutable('today');

        $questionarioHoje = null;

        foreach ($paciente->getQuestionarios() as $qr) {

            if (!$qr instanceof PosOperatorioQuestionarioResposta) {

                continue;

            }

            if ($qr->getDataReferencia()->format('Y-m-d') === $today->format('Y-m-d')) {

                $questionarioHoje = $qr;

                break;

            }

        }

        $protocolo = $paciente->getProtocolo();

        $checklist = $protocolo?->getChecklist() ?? PosOperatorioProtocoloDefaults::checklistBasico();

        $diaPos = $paciente->getDiaPosOperatorio();

        $checklistProgress = array_map(static fn (array $item) => [

            'dia' => (int) ($item['dia'] ?? 0),

            'item' => (string) ($item['item'] ?? ''),

            'done' => $diaPos !== null && (int) ($item['dia'] ?? 0) <= $diaPos,

        ], $checklist);

        $proximoChecklist = null;

        foreach ($checklistProgress as $step) {

            if (!$step['done']) {

                $proximoChecklist = $step;

                break;

            }

        }



        return [

            'paciente' => $paciente,

            'display_nome' => PosOperatorioDisplay::pacienteNome($paciente),

            'dia_pos' => $diaPos,

            'protocolo_dias' => $protocolo?->getDuracaoDias(),

            'proximo_checklist' => $proximoChecklist,

            'ultima_resposta' => $paciente->getUltimaResposta(),

            'alertas_abertos' => \count($alertas),

            'alertas' => $alertas,

            'questionarios' => $questionarios,

            'timeline' => $timeline,

            'timeline_groups' => $timelineGroups,

            'portal_user' => $portalUser ? [

                'nome' => $portalUser->getNome(),

                'email' => $portalUser->getEmail(),

            ] : null,

            'portal_questionario_hoje' => $questionarioHoje !== null,

            'portal_questionario_hoje_em' => $questionarioHoje?->getRespondidoEm()->format('H:i'),

            'portal_vinculado' => $portalUser !== null,

            'checklist_progress' => $checklistProgress,

            'consentimento_em' => $paciente->getConsentimentoLgpdEm()?->format('d/m/Y H:i'),

            'cadastrado_em' => $paciente->getCriadoEm()->format('d/m/Y H:i'),

        ];

    }



    /**
     * @param list<array<string, mixed>> $timeline
     *
     * @return list<array{date_key: string, date_label: string, events: list<array<string, mixed>>}>
     */
    private function groupTimelineByDate(array $timeline): array
    {
        $groups = [];

        foreach ($timeline as $event) {
            $key = (string) ($event['date_key'] ?? '');

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'date_key' => $key,
                    'date_label' => (string) ($event['date_label'] ?? ''),
                    'events' => [],
                ];
            }

            $groups[$key]['events'][] = $event;
        }

        return array_values($groups);
    }

    /** @return array{time: string, date: string, date_key: string, date_label: string, datetime: string, type: string, label: string, detail: string, icon: string, tone: string, author: string|null} */
    private function mapEvento(PosOperatorioEvento $ev): array
    {
        $tipo = $ev->getTipo();
        $criadoEm = $ev->getCriadoEm();

        return [
            'time' => $criadoEm->format('H:i'),
            'date' => $criadoEm->format('d/m'),
            'date_key' => $criadoEm->format('Y-m-d'),
            'date_label' => $this->timelineDateLabel($criadoEm),
            'datetime' => $criadoEm->format('d/m/Y H:i'),
            'type' => $tipo,
            'label' => match ($tipo) {
                PosOperatorioEvento::TIPO_CADASTRO => 'Cadastro',
                PosOperatorioEvento::TIPO_ALERTA => 'Alerta clínico',
                PosOperatorioEvento::TIPO_QUESTIONARIO => 'Questionário',
                PosOperatorioEvento::TIPO_CONSENTIMENTO => 'Consentimento LGPD',
                PosOperatorioEvento::TIPO_LEMBRETE => 'Lembrete',
                PosOperatorioEvento::TIPO_EVOLUCAO => 'Evolução clínica',
                PosOperatorioEvento::TIPO_RETORNO => 'Retorno confirmado',
                PosOperatorioEvento::TIPO_VITORIA => 'Vitória AI',
                PosOperatorioEvento::TIPO_ACESSO_FICHA => 'Acesso à ficha',
                PosOperatorioEvento::TIPO_CHAT => 'Pedir ajuda',
                default => 'Registro',
            },
            'detail' => $this->eventDetail($ev),
            'author' => $ev->getAutor()?->getNome(),
            'icon' => match ($tipo) {
                PosOperatorioEvento::TIPO_CADASTRO => 'fa-user-plus',
                PosOperatorioEvento::TIPO_ALERTA => 'fa-triangle-exclamation',
                PosOperatorioEvento::TIPO_QUESTIONARIO => 'fa-file-medical',
                PosOperatorioEvento::TIPO_CONSENTIMENTO => 'fa-shield-halved',
                PosOperatorioEvento::TIPO_LEMBRETE => 'fa-bell',
                PosOperatorioEvento::TIPO_EVOLUCAO => 'fa-notes-medical',
                PosOperatorioEvento::TIPO_RETORNO => 'fa-calendar-check',
                PosOperatorioEvento::TIPO_VITORIA => 'fa-sparkles',
                PosOperatorioEvento::TIPO_ACESSO_FICHA => 'fa-eye',
                PosOperatorioEvento::TIPO_CHAT => 'fa-hand-holding-medical',
                default => 'fa-circle-dot',
            },
            'tone' => match ($tipo) {
                PosOperatorioEvento::TIPO_ALERTA => 'warn',
                PosOperatorioEvento::TIPO_QUESTIONARIO => 'ok',
                PosOperatorioEvento::TIPO_CADASTRO => 'accent',
                PosOperatorioEvento::TIPO_VITORIA => 'ai',
                PosOperatorioEvento::TIPO_EVOLUCAO => 'accent',
                PosOperatorioEvento::TIPO_RETORNO => 'ok',
                default => 'info',
            },
        ];
    }

    private function eventDetail(PosOperatorioEvento $ev): string
    {
        return PosOperatorioTimelineFormatter::format($ev)['detail'];
    }

    private function timelineDateLabel(\DateTimeImmutable $date): string
    {
        $today = new \DateTimeImmutable('today');
        $eventDay = $date->setTime(0, 0);

        if ($eventDay == $today) {
            return 'Hoje';
        }

        if ($eventDay == $today->modify('-1 day')) {
            return 'Ontem';
        }

        $months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        return sprintf(
            '%s %s %s',
            $date->format('d'),
            $months[(int) $date->format('n') - 1],
            $date->format('Y'),
        );
    }



    private function nextCodigo(Empresa $empresa): string

    {

        $next = $this->repository->findMaxCodigoSequence($empresa) + 1;



        return 'PO-' . str_pad((string) $next, 4, '0', \STR_PAD_LEFT);

    }



    private function applyRelations(PosOperatorioPaciente $paciente, Empresa $empresa, array $data): void

    {

        $protocoloId = (int) ($data['protocolo_id'] ?? 0);

        if ($protocoloId > 0) {

            $protocolo = $this->protocoloRepo->find($protocoloId);

            if ($protocolo instanceof \App\Entity\PosOperatorioProtocolo && $protocolo->getEmpresa()->getId() === $empresa->getId()) {

                $paciente->setProtocolo($protocolo);

            }

        }



        $medicoId = (int) ($data['medico_id'] ?? 0);

        if ($medicoId > 0) {

            $medico = $this->userRepo->find($medicoId);

            if ($medico instanceof User) {

                $paciente->setMedicoResponsavel($medico);

            }

        } elseif (array_key_exists('medico_id', $data)) {

            $paciente->setMedicoResponsavel(null);

        }



        if (array_key_exists('portal_user_id', $data)) {

            $portalId = (int) ($data['portal_user_id'] ?? 0);

            if ($portalId <= 0) {

                $paciente->setPortalUser(null);

            } else {

                $portalUser = $this->userRepo->find($portalId);

                if ($portalUser instanceof User && $portalUser->getEmpresa()?->getId() === $empresa->getId()) {

                    $paciente->setPortalUser($portalUser);

                }

            }

        }

    }



    private function applyDataCirurgia(PosOperatorioPaciente $paciente, string $raw): void

    {

        if ($raw === '') {

            $paciente->setDataCirurgia(null);

            return;

        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);

        if ($dt) {

            $paciente->setDataCirurgia($dt);

        }

    }

    private function applyDataNascimento(PosOperatorioPaciente $paciente, array $data): void
    {
        if (!array_key_exists('data_nascimento', $data)) {
            return;
        }

        $raw = trim((string) ($data['data_nascimento'] ?? ''));
        if ($raw === '') {
            $paciente->setDataNascimento(null);

            return;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        if ($dt) {
            $paciente->setDataNascimento($dt);
        }
    }



    private function applyContactExtras(PosOperatorioPaciente $paciente, array $data): void

    {

        $paciente->setEmailContato(trim((string) ($data['email_contato'] ?? '')) ?: null);

        $paciente->setContatoEmergencia(trim((string) ($data['contato_emergencia'] ?? '')) ?: null);

        $paciente->setTelefoneEmergencia($this->normalizePhone((string) ($data['telefone_emergencia'] ?? '')));

        $paciente->setObservacoes(trim((string) ($data['observacoes'] ?? '')) ?: null);

    }

    private function applyCpf(PosOperatorioPaciente $paciente, Empresa $empresa, array $data): void
    {
        if (!array_key_exists('cpf', $data)) {
            return;
        }

        $raw = trim((string) ($data['cpf'] ?? ''));
        if ($raw === '') {
            $paciente->setCpf(null);

            return;
        }

        if (!BrPersonFormat::isValidCpf($raw)) {
            throw new \InvalidArgumentException('CPF inválido.');
        }

        $digits = BrPersonFormat::digitsOnly($raw);
        if ($digits === null) {
            throw new \InvalidArgumentException('CPF inválido.');
        }

        if ($this->repository->existsByCpf($empresa, $digits, $paciente->getId())) {
            throw new \InvalidArgumentException('Já existe paciente com este CPF na clínica.');
        }

        $paciente->setCpf($digits);
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = BrPersonFormat::digitsOnly(trim($raw));

        return $digits !== null && $digits !== '' ? $digits : null;
    }



    private function applyProcedimentoFromProtocolo(PosOperatorioPaciente $paciente, array $data): void

    {

        $protocolo = $paciente->getProtocolo();

        if ($protocolo === null) {

            $paciente->setProcedimento(trim((string) ($data['procedimento'] ?? '')) ?: null);

            return;

        }



        $label = trim($protocolo->getNome());

        $paciente->setProcedimento($label !== '' ? $label : null);

    }

}

