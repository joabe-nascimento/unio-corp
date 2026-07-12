<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicAtendimento;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ClinicAgendamentoRepository;
use App\Repository\ClinicAtendimentoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicAtendimentoService
{
    public function __construct(
        private ClinicAtendimentoRepository $atendimentos,
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicAgendaService $agenda,
        private ClinicContaService $contas,
        private PosOperatorioPacienteService $pacientes,
        private EntityManagerInterface $em,
    ) {}

    public function startFromAgendamento(Empresa $empresa, ClinicAgendamento $agendamento, ?User $ator = null): ClinicAtendimento
    {
        if ($agendamento->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Agendamento fora do escopo.');
        }

        $existing = $this->atendimentos->findOneByAgendamento($empresa, $agendamento);
        if ($existing !== null) {
            if ($agendamento->getStatus() !== ClinicAgendamento::STATUS_EM_ATENDIMENTO
                && $agendamento->getStatus() !== ClinicAgendamento::STATUS_ATENDIDO
                && !\in_array($agendamento->getStatus(), [
                    ClinicAgendamento::STATUS_CANCELADO,
                    ClinicAgendamento::STATUS_FALTOU,
                ], true)
            ) {
                $this->agenda->changeStatus($agendamento, $empresa, ClinicAgendamento::STATUS_EM_ATENDIMENTO);
            }

            return $existing;
        }

        if (\in_array($agendamento->getStatus(), [
            ClinicAgendamento::STATUS_CANCELADO,
            ClinicAgendamento::STATUS_FALTOU,
            ClinicAgendamento::STATUS_ATENDIDO,
        ], true)) {
            throw new \InvalidArgumentException('Não é possível atender um horário cancelado, com falta ou já atendido.');
        }

        $atendimento = new ClinicAtendimento();
        $atendimento->setEmpresa($empresa);
        $atendimento->setAgendamento($agendamento);
        $atendimento->setPaciente($agendamento->getPaciente());
        $atendimento->setMedico($agendamento->getMedico() ?? $ator);
        $atendimento->setStatus(ClinicAtendimento::STATUS_EM_ANDAMENTO);

        $this->em->persist($atendimento);
        $this->em->flush();

        if ($agendamento->getStatus() !== ClinicAgendamento::STATUS_EM_ATENDIMENTO) {
            $this->agenda->changeStatus($agendamento, $empresa, ClinicAgendamento::STATUS_EM_ATENDIMENTO);
        }

        return $atendimento;
    }

    /**
     * @param array{queixa?: string, exame?: string, conduta?: string, observacao?: string} $data
     */
    public function saveDraft(ClinicAtendimento $atendimento, Empresa $empresa, array $data): ClinicAtendimento
    {
        $this->assertScope($atendimento, $empresa);
        if (!$atendimento->isEmAndamento()) {
            throw new \InvalidArgumentException('Atendimento já finalizado.');
        }

        $this->applySoap($atendimento, $data);
        $atendimento->touch();
        $this->em->flush();

        return $atendimento;
    }

    /**
     * @param array{queixa?: string, exame?: string, conduta?: string, observacao?: string} $data
     */
    public function finalize(ClinicAtendimento $atendimento, Empresa $empresa, User $autor, array $data = []): ClinicAtendimento
    {
        $this->assertScope($atendimento, $empresa);
        if (!$atendimento->isEmAndamento()) {
            throw new \InvalidArgumentException('Atendimento já finalizado.');
        }

        $this->applySoap($atendimento, $data);
        $atendimento->setStatus(ClinicAtendimento::STATUS_FINALIZADO);
        $atendimento->setFinalizadoEm(new \DateTimeImmutable());
        $atendimento->touch();
        $this->em->flush();

        $agendamento = $atendimento->getAgendamento();
        if ($agendamento->getStatus() !== ClinicAgendamento::STATUS_ATENDIDO) {
            $this->agenda->changeStatus($agendamento, $empresa, ClinicAgendamento::STATUS_ATENDIDO);
        }

        $evolucao = $this->buildEvolucaoText($atendimento);
        if ($evolucao !== '') {
            $this->pacientes->recordEvolucao($atendimento->getPaciente(), $autor, $evolucao);
        }

        $this->contas->ensureFromAtendimento($empresa, $atendimento);

        return $atendimento;
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicAtendimento
    {
        return $this->atendimentos->findOneByEmpresa($empresa, $id);
    }

    public function findByAgendamentoId(Empresa $empresa, int $agendamentoId): ?ClinicAtendimento
    {
        $agendamento = $this->agendamentos->findOneByEmpresa($empresa, $agendamentoId);
        if ($agendamento === null) {
            return null;
        }

        return $this->atendimentos->findOneByAgendamento($empresa, $agendamento);
    }

    public function requireAgendamento(Empresa $empresa, int $id): ClinicAgendamento
    {
        $agendamento = $this->agendamentos->findOneByEmpresa($empresa, $id);
        if ($agendamento === null) {
            throw new \InvalidArgumentException('Agendamento não encontrado.');
        }

        return $agendamento;
    }

    /** @return list<ClinicAtendimento> */
    public function listRecent(Empresa $empresa): array
    {
        return $this->atendimentos->findRecentByEmpresa($empresa);
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            ClinicAtendimento::STATUS_EM_ANDAMENTO => 'Em andamento',
            ClinicAtendimento::STATUS_FINALIZADO => 'Finalizado',
            ClinicAtendimento::STATUS_CANCELADO => 'Cancelado',
        ];
    }

    /**
     * @param array{queixa?: string, exame?: string, conduta?: string, observacao?: string} $data
     */
    private function applySoap(ClinicAtendimento $atendimento, array $data): void
    {
        if (\array_key_exists('queixa', $data)) {
            $atendimento->setQueixa($this->nullableText($data['queixa'] ?? null));
        }
        if (\array_key_exists('exame', $data)) {
            $atendimento->setExame($this->nullableText($data['exame'] ?? null));
        }
        if (\array_key_exists('conduta', $data)) {
            $atendimento->setConduta($this->nullableText($data['conduta'] ?? null));
        }
        if (\array_key_exists('observacao', $data)) {
            $atendimento->setObservacao($this->nullableText($data['observacao'] ?? null));
        }
    }

    private function buildEvolucaoText(ClinicAtendimento $atendimento): string
    {
        $parts = [];
        if ($atendimento->getQueixa()) {
            $parts[] = 'Queixa: '.$atendimento->getQueixa();
        }
        if ($atendimento->getExame()) {
            $parts[] = 'Exame: '.$atendimento->getExame();
        }
        if ($atendimento->getConduta()) {
            $parts[] = 'Conduta: '.$atendimento->getConduta();
        }
        if ($atendimento->getObservacao()) {
            $parts[] = 'Obs.: '.$atendimento->getObservacao();
        }

        if ($parts === []) {
            $titulo = $atendimento->getAgendamento()->getTitulo() ?: 'Consulta';

            return 'Atendimento finalizado ('.$titulo.').';
        }

        return 'Atendimento: '.implode(' | ', $parts);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 8000);
    }

    private function assertScope(ClinicAtendimento $atendimento, Empresa $empresa): void
    {
        if ($atendimento->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Atendimento fora do escopo.');
        }
    }
}
