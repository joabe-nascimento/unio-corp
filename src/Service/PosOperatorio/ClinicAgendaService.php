<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicProcedimento;
use App\Entity\ClinicProfissional;
use App\Entity\ClinicSala;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\ClinicAgendamentoRepository;
use App\Repository\ClinicAtendimentoRepository;
use App\Repository\ClinicProcedimentoRepository;
use App\Repository\ClinicProfissionalRepository;
use App\Repository\ClinicSalaRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicAgendaService
{
    public function __construct(
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicAtendimentoRepository $atendimentos,
        private PosOperatorioPacienteRepository $pacientes,
        private UserRepository $users,
        private ClinicOperationsService $operations,
        private ClinicPatientNotifier $patientNotifier,
        private ClinicAgendaReminderService $agendaReminders,
        private EntityManagerInterface $em,
        private ?ClinicSalaRepository $salas = null,
        private ?ClinicProfissionalRepository $profissionais = null,
        private ?ClinicProcedimentoRepository $procedimentos = null,
    ) {}

    /**
     * @return array{
     *     items: list<ClinicAgendamento>,
     *     week_start: \DateTimeImmutable,
     *     week_end: \DateTimeImmutable,
     *     medico: ?User
     * }
     */
    public function listWeek(Empresa $empresa, ?\DateTimeImmutable $weekStart = null, ?User $medico = null): array
    {
        $start = $weekStart ?? new \DateTimeImmutable('monday this week');
        $start = $start->setTime(0, 0);
        $end = $start->modify('+7 days');

        return [
            'items' => $this->agendamentos->findByEmpresaAndInterval($empresa, $start, $end, $medico),
            'week_start' => $start,
            'week_end' => $end,
            'medico' => $medico,
        ];
    }

    /**
     * @return array{
     *     items: list<ClinicAgendamento>,
     *     day: \DateTimeImmutable,
     *     day_end: \DateTimeImmutable,
     *     medico: ?User
     * }
     */
    public function listDay(Empresa $empresa, ?\DateTimeImmutable $day = null, ?User $medico = null): array
    {
        $start = ($day ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $end = $start->modify('+1 day');

        return [
            'items' => $this->agendamentos->findByEmpresaAndInterval($empresa, $start, $end, $medico),
            'day' => $start,
            'day_end' => $end,
            'medico' => $medico,
        ];
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            ClinicAgendamento::STATUS_MARCADO => 'Marcado',
            ClinicAgendamento::STATUS_CONFIRMADO => 'Confirmado',
            ClinicAgendamento::STATUS_CHEGOU => 'Chegou',
            ClinicAgendamento::STATUS_EM_ATENDIMENTO => 'Em atendimento',
            ClinicAgendamento::STATUS_FALTOU => 'Faltou',
            ClinicAgendamento::STATUS_CANCELADO => 'Cancelado',
            ClinicAgendamento::STATUS_ATENDIDO => 'Atendido',
        ];
    }

    /**
     * Link wa.me para o paciente confirmar o horário (manual / Meta depois).
     */
    public function confirmWhatsappUrl(ClinicAgendamento $agendamento): ?string
    {
        $paciente = $agendamento->getPaciente();
        $primeiro = explode(' ', trim($paciente->getNome()))[0] ?: 'paciente';
        $medico = $agendamento->getMedico()?->getNome();
        $titulo = $agendamento->getTitulo() ?: 'consulta';
        $quando = $agendamento->getInicio()->format('d/m/Y \à\s H:i');

        $text = sprintf(
            "Olá, %s! Confirmando seu horário na clínica:\n\n%s\n%s%s\n\nResponda *CONFIRMO* ou *REMARCAR* por favor.",
            $primeiro,
            $titulo,
            $quando,
            $medico ? "\nCom: " . $medico : '',
        );

        return $this->patientNotifier->buildWhatsappLink($paciente->getTelefoneContato(), $text);
    }

    /**
     * @param list<ClinicAgendamento> $items
     *
     * @return array<int, string|null> id => wa url
     */
    public function confirmWhatsappMap(array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $id = $item->getId();
            if ($id === null) {
                continue;
            }
            $map[$id] = $this->confirmWhatsappUrl($item);
        }

        return $map;
    }

    /**
     * @param list<ClinicAgendamento> $items
     *
     * @return array{
     *     total: int,
     *     marcado: int,
     *     confirmado: int,
     *     chegou: int,
     *     em_atendimento: int,
     *     atendido: int,
     *     faltou: int,
     *     cancelado: int
     * }
     */
    public function countByStatus(array $items): array
    {
        $stats = [
            'total' => \count($items),
            ClinicAgendamento::STATUS_MARCADO => 0,
            ClinicAgendamento::STATUS_CONFIRMADO => 0,
            ClinicAgendamento::STATUS_CHEGOU => 0,
            ClinicAgendamento::STATUS_EM_ATENDIMENTO => 0,
            ClinicAgendamento::STATUS_ATENDIDO => 0,
            ClinicAgendamento::STATUS_FALTOU => 0,
            ClinicAgendamento::STATUS_CANCELADO => 0,
        ];

        foreach ($items as $item) {
            $status = $item->getStatus();
            if (isset($stats[$status])) {
                ++$stats[$status];
            }
        }

        return $stats;
    }

    /**
     * @return list<PosOperatorioPaciente>
     */
    public function listPacientesAtivos(Empresa $empresa): array
    {
        return $this->pacientes->findRecentByEmpresa($empresa, 300, 0);
    }

    /**
     * @return list<User>
     */
    public function listMedicos(Empresa $empresa): array
    {
        return $this->users->findActiveByEmpresa($empresa);
    }

    /** @return list<ClinicSala> */
    public function listSalas(Empresa $empresa): array
    {
        return $this->salas?->findByEmpresa($empresa, true) ?? [];
    }

    /** @return list<ClinicProfissional> */
    public function listProfissionais(Empresa $empresa): array
    {
        return $this->profissionais?->findByEmpresa($empresa, true) ?? [];
    }

    /** @return list<ClinicProcedimento> */
    public function listProcedimentos(Empresa $empresa): array
    {
        return $this->procedimentos?->findByEmpresa($empresa, true) ?? [];
    }

    public function find(Empresa $empresa, int $id): ?ClinicAgendamento
    {
        return $this->agendamentos->findOneByEmpresa($empresa, $id);
    }

    public function findPaciente(Empresa $empresa, int $id): ?PosOperatorioPaciente
    {
        $paciente = $this->pacientes->find($id);
        if ($paciente === null || $paciente->getEmpresa()->getId() !== $empresa->getId()) {
            return null;
        }

        return $paciente;
    }

    public function findMedico(Empresa $empresa, ?int $id): ?User
    {
        if ($id === null || $id <= 0) {
            return null;
        }
        $medico = $this->users->find($id);
        if ($medico === null || !$medico->isAtivo()) {
            return null;
        }
        foreach ($this->users->findActiveByEmpresa($empresa) as $u) {
            if ($u->getId() === $medico->getId()) {
                return $medico;
            }
        }

        return null;
    }

    /**
     * @param array{
     *     paciente_id: int,
     *     medico_id?: ?int,
     *     inicio: \DateTimeImmutable,
     *     fim: \DateTimeImmutable,
     *     titulo?: ?string,
     *     observacao?: ?string,
     *     origem?: string,
     *     protocolo_dia?: ?int,
     *     status?: string
     * } $data
     */
    public function create(Empresa $empresa, array $data): ClinicAgendamento
    {
        $paciente = $this->findPaciente($empresa, (int) $data['paciente_id']);
        if ($paciente === null) {
            throw new \InvalidArgumentException('Paciente inválido.');
        }

        $medicoId = isset($data['medico_id']) ? (int) $data['medico_id'] : 0;
        $medico = $medicoId > 0
            ? $this->findMedico($empresa, $medicoId)
            : $paciente->getMedicoResponsavel();

        $agendamento = new ClinicAgendamento();
        $agendamento->setEmpresa($empresa);
        $agendamento->setPaciente($paciente);
        $agendamento->setMedico($medico);
        $this->applyFields($agendamento, $data);
        if (isset($data['status']) && \is_string($data['status']) && $data['status'] !== '') {
            $this->assertStatusChange($agendamento, $empresa, $data['status']);
            $agendamento->setStatus($data['status']);
        }
        $agendamento->touch();

        $this->em->persist($agendamento);
        $this->em->flush();

        return $agendamento;
    }

    /**
     * @param array{
     *     paciente_id?: int,
     *     medico_id?: ?int,
     *     inicio?: \DateTimeImmutable,
     *     fim?: \DateTimeImmutable,
     *     titulo?: ?string,
     *     observacao?: ?string,
     *     origem?: string,
     *     protocolo_dia?: ?int,
     *     status?: string
     * } $data
     */
    public function update(ClinicAgendamento $agendamento, Empresa $empresa, array $data): ClinicAgendamento
    {
        if ($agendamento->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Agendamento fora do escopo.');
        }

        if (isset($data['paciente_id'])) {
            $paciente = $this->findPaciente($empresa, (int) $data['paciente_id']);
            if ($paciente === null) {
                throw new \InvalidArgumentException('Paciente inválido.');
            }
            $agendamento->setPaciente($paciente);
        }

        if (array_key_exists('medico_id', $data)) {
            $medicoId = $data['medico_id'] !== null && $data['medico_id'] !== ''
                ? (int) $data['medico_id']
                : 0;
            $agendamento->setMedico($medicoId > 0 ? $this->findMedico($empresa, $medicoId) : null);
        }

        $this->applyFields($agendamento, $data);
        $newStatus = null;
        if (isset($data['status']) && \is_string($data['status']) && $data['status'] !== '') {
            $this->assertStatusChange($agendamento, $empresa, $data['status']);
            $agendamento->setStatus($data['status']);
            $newStatus = $data['status'];
        }
        $agendamento->touch();
        $this->em->flush();

        if ($newStatus === ClinicAgendamento::STATUS_CONFIRMADO) {
            $this->agendaReminders->attestTrilhaDMinus1(
                $agendamento->getPaciente(),
                'Confirmação de agenda/cirurgia na agenda clínica',
            );
        }

        return $agendamento;
    }

    public function changeStatus(ClinicAgendamento $agendamento, Empresa $empresa, string $status): ClinicAgendamento
    {
        if ($agendamento->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Agendamento fora do escopo.');
        }

        $this->assertStatusChange($agendamento, $empresa, $status);
        $agendamento->setStatus($status);
        $agendamento->touch();
        $this->em->flush();

        if ($status === ClinicAgendamento::STATUS_CONFIRMADO) {
            $this->agendaReminders->attestTrilhaDMinus1(
                $agendamento->getPaciente(),
                'Confirmação de agenda/cirurgia na agenda clínica',
            );
        }

        return $agendamento;
    }

    /**
     * Enriquece marcos de retorno com sugestão de data (sem duplicar lógica de marcos).
     *
     * @return array<string, mixed>
     */
    public function buildReturnSuggestions(Empresa $empresa): array
    {
        $retornos = $this->operations->buildReturns($empresa);
        $today = new \DateTimeImmutable('today');
        $items = [];

        foreach ($retornos['items'] as $item) {
            $diff = (int) $item['dia_marco'] - (int) $item['dia_pos'];
            $sugestao = $today->modify(($diff >= 0 ? '+' : '') . $diff . ' days');
            $items[] = $item + [
                'sugestao_data' => $sugestao->format('Y-m-d'),
                'titulo_sugerido' => (string) ($item['marco'] ?? 'Retorno'),
            ];
        }

        $retornos['items'] = $items;

        return $retornos;
    }

    /**
     * Prefill a partir de query (retornos / protocolo).
     *
     * @return array{
     *     paciente_id: ?int,
     *     medico_id: ?int,
     *     inicio: string,
     *     fim: string,
     *     titulo: string,
     *     observacao: string,
     *     origem: string,
     *     protocolo_dia: ?int
     * }
     */
    public function prefillFromRequest(Empresa $empresa, array $query): array
    {
        $pacienteId = isset($query['paciente_id']) ? (int) $query['paciente_id'] : null;
        $paciente = $pacienteId ? $this->findPaciente($empresa, $pacienteId) : null;

        $sugestaoData = trim((string) ($query['sugestao_data'] ?? ($query['dia'] ?? '')));
        if ($sugestaoData !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sugestaoData)) {
            $inicio = new \DateTimeImmutable($sugestaoData . ' 09:00:00');
        } else {
            $inicio = (new \DateTimeImmutable('tomorrow'))->setTime(9, 0);
        }
        $fim = $inicio->modify('+30 minutes');

        $protocoloDia = isset($query['protocolo_dia']) && $query['protocolo_dia'] !== ''
            ? (int) $query['protocolo_dia']
            : null;
        $titulo = trim((string) ($query['titulo'] ?? ''));
        $origem = $protocoloDia !== null || $titulo !== ''
            ? ClinicAgendamento::ORIGEM_PROTOCOLO
            : ClinicAgendamento::ORIGEM_MANUAL;

        return [
            'paciente_id' => $paciente?->getId(),
            'medico_id' => $paciente?->getMedicoResponsavel()?->getId(),
            'inicio' => $inicio->format('Y-m-d\TH:i'),
            'fim' => $fim->format('Y-m-d\TH:i'),
            'titulo' => $titulo !== '' ? $titulo : ($protocoloDia !== null ? 'Retorno D+' . $protocoloDia : ''),
            'observacao' => trim((string) ($query['observacao'] ?? '')),
            'origem' => $origem,
            'protocolo_dia' => $protocoloDia,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyFields(ClinicAgendamento $agendamento, array $data): void
    {
        if (isset($data['inicio']) && $data['inicio'] instanceof \DateTimeImmutable) {
            $agendamento->setInicio($data['inicio']);
        }
        if (isset($data['fim']) && $data['fim'] instanceof \DateTimeImmutable) {
            $agendamento->setFim($data['fim']);
        }

        if ($agendamento->getFim() <= $agendamento->getInicio()) {
            throw new \InvalidArgumentException('Horário de fim deve ser após o início.');
        }

        if (array_key_exists('titulo', $data)) {
            $titulo = trim((string) ($data['titulo'] ?? ''));
            $agendamento->setTitulo($titulo !== '' ? mb_substr($titulo, 0, 180) : null);
        }
        if (array_key_exists('observacao', $data)) {
            $obs = trim((string) ($data['observacao'] ?? ''));
            $agendamento->setObservacao($obs !== '' ? mb_substr($obs, 0, 500) : null);
        }
        if (isset($data['origem']) && \in_array($data['origem'], ClinicAgendamento::ORIGENS, true)) {
            $agendamento->setOrigem($data['origem']);
        }
        if (array_key_exists('protocolo_dia', $data)) {
            $dia = $data['protocolo_dia'];
            $agendamento->setProtocoloDia($dia !== null && $dia !== '' ? (int) $dia : null);
        }

        if (array_key_exists('sala_id', $data)) {
            $salaId = (int) ($data['sala_id'] ?? 0);
            $sala = null;
            if ($salaId > 0 && $this->salas !== null) {
                $sala = $this->salas->findOneByEmpresa($agendamento->getEmpresa(), $salaId);
            }
            $agendamento->setSala($sala);
        }
        if (array_key_exists('profissional_id', $data)) {
            $profissionalId = (int) ($data['profissional_id'] ?? 0);
            $profissional = null;
            if ($profissionalId > 0 && $this->profissionais !== null) {
                $profissional = $this->profissionais->findOneByEmpresa($agendamento->getEmpresa(), $profissionalId);
            }
            $agendamento->setProfissional($profissional);
        }
        if (array_key_exists('procedimento_id', $data)) {
            $procedimentoId = (int) ($data['procedimento_id'] ?? 0);
            $procedimento = null;
            if ($procedimentoId > 0 && $this->procedimentos !== null) {
                $procedimento = $this->procedimentos->findOneByEmpresa($agendamento->getEmpresa(), $procedimentoId);
            }
            $agendamento->setProcedimento($procedimento);
        }

        $sala = $agendamento->getSala();
        if ($sala !== null && $this->agendamentos->hasSalaOverlap(
            $agendamento->getEmpresa(),
            $sala,
            $agendamento->getInicio(),
            $agendamento->getFim(),
            $agendamento->getId(),
        )) {
            throw new \InvalidArgumentException('Sala ocupada neste horário. Escolha outro horário ou outra sala.');
        }
        // Status: sempre via assertStatusChange em create/update/changeStatus
    }

    private function assertStatusChange(ClinicAgendamento $agendamento, Empresa $empresa, string $status): void
    {
        if (!\in_array($status, ClinicAgendamento::STATUSES, true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        // Regravar o mesmo status (edição de outros campos) não revalida regras de transição
        if ($agendamento->getId() !== null && $agendamento->getStatus() === $status) {
            return;
        }

        $openAtendimento = $this->atendimentos->findOneByAgendamento($empresa, $agendamento);

        if ($status === ClinicAgendamento::STATUS_ATENDIDO) {
            if ($openAtendimento === null || !$openAtendimento->isFinalizado()) {
                throw new \InvalidArgumentException('Finalize o atendimento para marcar como atendido.');
            }
        }

        if ($status === ClinicAgendamento::STATUS_EM_ATENDIMENTO) {
            if ($openAtendimento === null || !$openAtendimento->isEmAndamento()) {
                throw new \InvalidArgumentException('Abra o atendimento pela agenda (Chegou → Atender) para marcar em atendimento.');
            }
        }

        if (\in_array($status, [ClinicAgendamento::STATUS_FALTOU, ClinicAgendamento::STATUS_CANCELADO], true)
            && $openAtendimento !== null
            && $openAtendimento->isEmAndamento()
        ) {
            throw new \InvalidArgumentException('Há atendimento em andamento. Finalize ou continue o SOAP antes de marcar falta/cancelar.');
        }
    }
}
