<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhAuditLog;
use App\Entity\RhCandidato;
use App\Entity\RhOnboardingProcess;
use App\Entity\RhVaga;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhAuditLogRepository;
use App\Repository\RhCandidatoRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\RhVagaRepository;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhCandidatoOrigem;
use App\Rh\RhVagaTipoContrato;
use App\Service\RhOnboardingService;
use Doctrine\ORM\EntityManagerInterface;

class RhRecrutamentoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhVagaRepository $vagaRepo,
        private RhCandidatoRepository $candidatoRepo,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhAuditLogRepository $auditLogRepo,
        private RhOnboardingService $onboarding,
        private RhAuditService $audit,
        private RhRecruitmentNotificationService $recruitmentNotifications,
    ) {}

    /** @return list<RhVaga> */
    public function listVagas(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        return $this->vagaRepo->findForEmpresa($empresa, $status, $q);
    }

    public function createVaga(
        Empresa $empresa,
        string $titulo,
        ?string $departamento,
        ?string $descricao,
        ?User $actor,
        string $status = RhVaga::STATUS_ABERTA,
        ?string $tipoContrato = null,
        ?string $localTrabalho = null,
        ?string $requisitos = null,
        int $vagasQuantidade = 1,
    ): RhVaga {
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new RhProcessException('Informe o título da vaga.');
        }

        if (!\in_array($status, [RhVaga::STATUS_ABERTA, RhVaga::STATUS_PAUSADA, RhVaga::STATUS_FECHADA], true)) {
            throw new RhProcessException('Status inválido.');
        }

        if (!RhVagaTipoContrato::isValid($tipoContrato)) {
            throw new RhProcessException('Tipo de contrato inválido.');
        }

        $vaga = new RhVaga();
        $vaga->setEmpresa($empresa);
        $vaga->setTitulo($titulo);
        $vaga->setDepartamento($departamento !== '' ? trim((string) $departamento) : null);
        $vaga->setDescricao($descricao !== '' ? trim((string) $descricao) : null);
        $vaga->setStatus($status);
        $vaga->setTipoContrato($tipoContrato !== '' && $tipoContrato !== null ? $tipoContrato : null);
        $vaga->setLocalTrabalho($localTrabalho !== '' ? trim((string) $localTrabalho) : null);
        $vaga->setRequisitos($requisitos !== '' ? trim((string) $requisitos) : null);
        $vaga->setVagasQuantidade(max(1, $vagasQuantidade));

        $this->em->persist($vaga);
        $this->em->flush();

        $this->audit->log($empresa, $actor, 'recrutamento', 'criar_vaga', 'rh_vaga', $vaga->getId());

        return $vaga;
    }

    public function updateVaga(
        RhVaga $vaga,
        string $titulo,
        ?string $departamento,
        ?string $descricao,
        string $status,
        ?User $actor,
        ?string $tipoContrato = null,
        ?string $localTrabalho = null,
        ?string $requisitos = null,
        int $vagasQuantidade = 1,
    ): RhVaga {
        if (!\in_array($status, [RhVaga::STATUS_ABERTA, RhVaga::STATUS_PAUSADA, RhVaga::STATUS_FECHADA], true)) {
            throw new RhProcessException('Status inválido.');
        }

        if (!RhVagaTipoContrato::isValid($tipoContrato)) {
            throw new RhProcessException('Tipo de contrato inválido.');
        }

        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new RhProcessException('Informe o título da vaga.');
        }

        $vaga->setTitulo($titulo);
        $vaga->setDepartamento($departamento !== '' ? $departamento : null);
        $vaga->setDescricao($descricao !== '' ? $descricao : null);
        $vaga->setStatus($status);
        $vaga->setTipoContrato($tipoContrato !== '' && $tipoContrato !== null ? $tipoContrato : null);
        $vaga->setLocalTrabalho($localTrabalho !== '' ? trim((string) $localTrabalho) : null);
        $vaga->setRequisitos($requisitos !== '' ? trim((string) $requisitos) : null);
        $vaga->setVagasQuantidade(max(1, $vagasQuantidade));

        $this->em->flush();
        $this->audit->log($vaga->getEmpresa(), $actor, 'recrutamento', 'atualizar_vaga', 'rh_vaga', $vaga->getId());

        return $vaga;
    }

    public function updateVagaStatus(RhVaga $vaga, string $status, ?User $actor): RhVaga
    {
        if (!\in_array($status, [RhVaga::STATUS_ABERTA, RhVaga::STATUS_PAUSADA, RhVaga::STATUS_FECHADA], true)) {
            throw new RhProcessException('Status inválido.');
        }

        $vaga->setStatus($status);
        $this->em->flush();
        $this->audit->log(
            $vaga->getEmpresa(),
            $actor,
            'recrutamento',
            'atualizar_vaga_status',
            'rh_vaga',
            $vaga->getId(),
            ['status' => $status],
        );

        return $vaga;
    }

    public function addCandidato(
        RhVaga $vaga,
        string $nome,
        string $email,
        ?string $telefone,
        ?User $actor,
        string $origem = RhCandidatoOrigem::MANUAL,
        ?string $linkedin = null,
    ): RhCandidato {
        $nome = trim($nome);
        $email = trim($email);
        if ($nome === '' || $email === '') {
            throw new RhProcessException('Informe nome e e-mail do candidato.');
        }

        if (!RhCandidatoOrigem::isValid($origem)) {
            throw new RhProcessException('Origem do candidato inválida.');
        }

        if ($this->candidatoRepo->existsByEmailAndVaga($email, $vaga)) {
            throw new RhProcessException('Já existe um candidato com este e-mail nesta vaga.');
        }

        $candidato = new RhCandidato();
        $candidato->setVaga($vaga);
        $candidato->setNome($nome);
        $candidato->setEmail($email);
        $candidato->setTelefone($telefone !== '' ? $telefone : null);
        $candidato->setLinkedin($linkedin !== '' ? trim((string) $linkedin) : null);
        $candidato->setOrigem($origem);
        $candidato->setEtapa(RhCandidatoEtapa::TRIAGEM);

        $this->em->persist($candidato);
        $this->em->flush();

        $this->audit->log(
            $vaga->getEmpresa(),
            $actor,
            'recrutamento',
            'adicionar_candidato',
            'rh_candidato',
            $candidato->getId(),
            ['origem' => $origem],
        );

        $this->recruitmentNotifications->notifyNovaCandidatura($candidato);

        return $candidato;
    }

    public function updateCandidato(
        RhCandidato $candidato,
        string $nome,
        string $email,
        ?string $telefone,
        ?User $actor,
        ?string $origem = null,
        ?string $linkedin = null,
    ): RhCandidato {
        $nome = trim($nome);
        $email = trim($email);
        if ($nome === '' || $email === '') {
            throw new RhProcessException('Informe nome e e-mail do candidato.');
        }

        if ($origem !== null && $origem !== '' && !RhCandidatoOrigem::isValid($origem)) {
            throw new RhProcessException('Origem do candidato inválida.');
        }

        if ($this->candidatoRepo->existsByEmailAndVaga($email, $candidato->getVaga(), $candidato->getId())) {
            throw new RhProcessException('Já existe outro candidato com este e-mail nesta vaga.');
        }

        $candidato->setNome($nome);
        $candidato->setEmail($email);
        $candidato->setTelefone($telefone !== '' && $telefone !== null ? $telefone : null);
        if ($origem !== null && $origem !== '') {
            $candidato->setOrigem($origem);
        }
        $candidato->setLinkedin($linkedin !== '' && $linkedin !== null ? trim($linkedin) : null);
        $this->em->flush();

        $this->audit->log(
            $candidato->getVaga()->getEmpresa(),
            $actor,
            'recrutamento',
            'atualizar_candidato',
            'rh_candidato',
            $candidato->getId(),
        );

        return $candidato;
    }

    public function updateCandidatoAvaliacao(
        RhCandidato $candidato,
        ?int $avaliacao,
        ?string $observacoes,
        ?User $actor,
    ): RhCandidato {
        if ($avaliacao !== null && ($avaliacao < 1 || $avaliacao > 5)) {
            throw new RhProcessException('Avaliação deve ser entre 1 e 5 estrelas.');
        }

        $candidato->setAvaliacao($avaliacao);
        $candidato->setObservacoes($observacoes !== '' ? trim((string) $observacoes) : null);
        $this->em->flush();

        $this->audit->log(
            $candidato->getVaga()->getEmpresa(),
            $actor,
            'recrutamento',
            'atualizar_candidato_avaliacao',
            'rh_candidato',
            $candidato->getId(),
            ['avaliacao' => $avaliacao],
        );

        return $candidato;
    }

    /** @return list<array{at: string, event: string, actor: string}> */
    public function buildCandidatoTimeline(Empresa $empresa, RhCandidato $candidato): array
    {
        if ($candidato->getId() === null) {
            return [];
        }

        $events = [];
        foreach ($this->auditLogRepo->findForEntity($empresa, 'recrutamento', 'rh_candidato', $candidato->getId()) as $log) {
            $events[] = $this->formatAuditTimelineEvent($log);
        }

        return $events;
    }

    /** @return array{at: string, event: string, actor: string, kind: string, de: ?string, para: ?string, motivo: ?string} */
    private function formatAuditTimelineEvent(RhAuditLog $log): array
    {
        $payload = $log->getPayload() ?? [];
        $user = $log->getUser();
        $actor = $user !== null ? ($user->getNome() ?: $user->getUserIdentifier()) : 'Sistema';
        $acao = $log->getAcao();
        $kind = 'default';
        $de = null;
        $para = null;
        $motivo = isset($payload['motivo']) && $payload['motivo'] !== '' ? (string) $payload['motivo'] : null;

        $event = match ($acao) {
            'adicionar_candidato' => 'Candidato adicionado ao funil',
            'atualizar_candidato' => 'Dados de contato atualizados',
            'mover_candidato_etapa' => isset($payload['de'], $payload['para'])
                ? sprintf(
                    'Movido de %s para %s',
                    RhCandidatoEtapa::label((string) $payload['de']),
                    RhCandidatoEtapa::label((string) $payload['para']),
                ) . ($motivo !== null ? ' — ' . $motivo : '')
                : 'Etapa atualizada',
            'atualizar_candidato_avaliacao' => isset($payload['avaliacao']) && $payload['avaliacao'] !== null
                ? 'Avaliação atualizada (' . $payload['avaliacao'] . '/5)'
                : 'Notas e avaliação atualizadas',
            'converter_onboarding' => isset($payload['onboarding_id'])
                ? 'Processo de admissão #' . $payload['onboarding_id'] . ' iniciado'
                : 'Conversão para admissão',
            'vincular_onboarding_existente' => isset($payload['onboarding_id'])
                ? 'Vinculado à admissão #' . $payload['onboarding_id']
                : 'Vinculado a admissão existente',
            default => ucfirst(str_replace('_', ' ', $acao)),
        };

        if ($acao === 'mover_candidato_etapa' && isset($payload['de'], $payload['para'])) {
            $kind = 'etapa';
            $de = (string) $payload['de'];
            $para = (string) $payload['para'];
        } elseif ($acao === 'adicionar_candidato') {
            $kind = 'cadastro';
        } elseif (\in_array($acao, ['converter_onboarding', 'vincular_onboarding_existente'], true)) {
            $kind = 'admissao';
        } elseif (\str_contains($acao, 'avaliacao') || \str_contains($acao, 'scorecard')) {
            $kind = 'avaliacao';
        } elseif ($acao === 'atualizar_candidato') {
            $kind = 'contato';
        }

        return [
            'at' => $log->getCriadoEm()->format('d/m/Y H:i'),
            'event' => $event,
            'actor' => $actor,
            'kind' => $kind,
            'de' => $de,
            'para' => $para,
            'motivo' => $motivo,
        ];
    }

    /** @return list<RhCandidato> */
    public function listCandidatosForEmpresa(
        Empresa $empresa,
        ?int $vagaId = null,
        ?string $q = null,
        ?string $etapa = null,
        ?string $origem = null,
    ): array {
        return $this->candidatoRepo->findForEmpresa($empresa, $vagaId, $q, $etapa, $origem);
    }

    /** @return list<RhCandidato> */
    public function listCandidatos(RhVaga $vaga): array
    {
        return $this->candidatoRepo->findByVaga($vaga);
    }

    /**
     * @return array<string, list<RhCandidato>>
     */
    public function buildPipelineBoard(Empresa $empresa, ?int $vagaId = null, ?string $q = null): array
    {
        $board = [];
        foreach (RhCandidatoEtapa::BOARD_ORDER as $etapa) {
            $board[$etapa] = [];
        }

        foreach ($this->candidatoRepo->findForEmpresa($empresa, $vagaId, $q) as $candidato) {
            $etapa = RhCandidatoEtapa::isValid($candidato->getEtapa())
                ? $candidato->getEtapa()
                : RhCandidatoEtapa::TRIAGEM;
            $board[$etapa][] = $candidato;
        }

        return $board;
    }

    public function moveCandidatoEtapa(RhCandidato $candidato, string $etapa, ?User $actor, ?string $motivo = null): RhCandidato
    {
        if (!RhCandidatoEtapa::isValid($etapa)) {
            throw new RhProcessException('Etapa inválida.');
        }

        $from = $candidato->getEtapa();
        if ($from === $etapa) {
            return $candidato;
        }

        return $this->em->wrapInTransaction(function () use ($candidato, $etapa, $actor, $from, $motivo): RhCandidato {
            if ($etapa === RhCandidatoEtapa::CONTRATADO) {
                $this->convertToOnboarding($candidato, $actor);
            }

            if ($etapa === RhCandidatoEtapa::REPROVADO && $motivo !== null && trim($motivo) !== '') {
                $candidato->setMotivoReprovacao(trim($motivo));
            }

            $candidato->setEtapa($etapa);
            $this->em->flush();

            $empresa = $candidato->getVaga()->getEmpresa();
            $auditPayload = ['de' => $from, 'para' => $etapa];
            if ($motivo !== null && trim($motivo) !== '') {
                $auditPayload['motivo'] = trim($motivo);
            }
            $this->audit->log(
                $empresa,
                $actor,
                'recrutamento',
                'mover_candidato_etapa',
                'rh_candidato',
                $candidato->getId(),
                $auditPayload,
            );

            return $candidato;
        });
    }

    public function rejectCandidato(RhCandidato $candidato, ?User $actor, ?string $motivo = null): RhCandidato
    {
        return $this->moveCandidatoEtapa($candidato, RhCandidatoEtapa::REPROVADO, $actor, $motivo);
    }

    /**
     * Cria (ou reutiliza) processo de admissão e vincula ao candidato contratado.
     */
    public function convertToOnboarding(RhCandidato $candidato, ?User $actor): RhOnboardingProcess
    {
        if ($candidato->getOnboardingProcess()) {
            return $candidato->getOnboardingProcess();
        }

        $vaga = $candidato->getVaga();
        $empresa = $vaga->getEmpresa();

        $existing = $this->onboardingRepo->findOpenByEmail($empresa, $candidato->getEmail());
        if ($existing !== null) {
            $candidato->setOnboardingProcess($existing);
            $this->em->flush();

            $this->audit->log(
                $empresa,
                $actor,
                'recrutamento',
                'vincular_onboarding_existente',
                'rh_candidato',
                $candidato->getId(),
                ['onboarding_id' => $existing->getId(), 'vaga_id' => $vaga->getId()],
            );

            return $existing;
        }

        $observacoes = $this->buildOnboardingObservacoes($candidato, $vaga);
        $cargo = $vaga->getTitulo();
        if ($vaga->getDepartamento()) {
            $cargo .= ' · ' . $vaga->getDepartamento();
        }

        $process = $this->onboarding->create(
            $empresa,
            $candidato->getNome(),
            $candidato->getEmail(),
            $cargo,
            null,
            $observacoes,
        );

        $candidato->setOnboardingProcess($process);
        $this->em->flush();

        $this->audit->log(
            $empresa,
            $actor,
            'recrutamento',
            'converter_onboarding',
            'rh_candidato',
            $candidato->getId(),
            ['onboarding_id' => $process->getId(), 'vaga_id' => $vaga->getId()],
        );

        return $process;
    }

    private function buildOnboardingObservacoes(RhCandidato $candidato, RhVaga $vaga): string
    {
        $lines = [
            sprintf('Origem: Núcleo de Recrutamento — vaga "%s" (ID %d).', $vaga->getTitulo(), $vaga->getId()),
            sprintf('Candidato ID %d · canal: %s.', $candidato->getId(), RhCandidatoOrigem::label($candidato->getOrigem())),
        ];

        if ($candidato->getTelefone()) {
            $lines[] = 'Telefone: ' . $candidato->getTelefone();
        }

        if ($vaga->getDescricao()) {
            $lines[] = 'Descrição da vaga: ' . $vaga->getDescricao();
        }

        return implode("\n", $lines);
    }
}
