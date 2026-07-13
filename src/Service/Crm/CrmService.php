<?php

namespace App\Service\Crm;

use App\Entity\Crm\CrmAtividade;
use App\Entity\Crm\CrmConta;
use App\Entity\Crm\CrmLead;
use App\Entity\Crm\CrmOportunidade;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\Crm\CrmAtividadeRepository;
use App\Repository\Crm\CrmContaRepository;
use App\Repository\Crm\CrmLeadRepository;
use App\Repository\Crm\CrmOportunidadeRepository;
use App\Repository\PosOperatorioProtocoloRepository;
use App\Service\PosOperatorio\PosOperatorioPacienteService;
use App\Service\WorkspaceService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Núcleo Comercial — CRM (leads, pipeline, clientes, atividades).
 */
final class CrmService
{
    public function __construct(
        private WorkspaceService $workspace,
        private EntityManagerInterface $em,
        private CrmLeadRepository $leads,
        private CrmContaRepository $contas,
        private CrmOportunidadeRepository $oportunidades,
        private CrmAtividadeRepository $atividades,
        private PosOperatorioPacienteService $pacientes,
        private PosOperatorioProtocoloRepository $protocolos,
    ) {}

    public function requireEmpresa(User $user): Empresa
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw new \RuntimeException('Selecione uma área de trabalho para acessar o CRM.');
        }

        return $empresa;
    }

    /** @return array<string, mixed> */
    public function getDashboard(User $user): array
    {
        $empresa = $this->requireEmpresa($user);
        $leadsNovos = $this->leads->countByEmpresa($empresa, CrmLead::STATUS_NOVO);
        $leadsTotal = $this->leads->countByEmpresa($empresa);
        $contasTotal = $this->contas->countByEmpresa($empresa);
        $ativPendentes = $this->atividades->countPendentes($empresa);
        $pipelineAberto = $this->oportunidades->sumValorAberto($empresa);
        $ganhos = $this->oportunidades->countByEmpresaAndStage($empresa, CrmOportunidade::STAGE_GANHO);
        $board = $this->buildPipelineBoard($empresa);

        return [
            'crm_section' => 'overview',
            'empresa' => $empresa,
            'kpis' => [
                ['value' => $leadsNovos, 'label' => 'Leads novos', 'sub' => $leadsTotal . ' no total', 'route' => 'app_comercial_leads', 'route_params' => ['status' => CrmLead::STATUS_NOVO]],
                ['value' => $this->countOpenDeals($board), 'label' => 'No pipeline', 'sub' => 'Oportunidades abertas', 'route' => 'app_comercial_pipeline'],
                ['value' => $this->formatMoney($pipelineAberto), 'label' => 'Pipeline R$', 'sub' => 'Valor em aberto', 'route' => 'app_comercial_pipeline'],
                ['value' => $ganhos, 'label' => 'Ganhos', 'sub' => 'Oportunidades fechadas', 'route' => 'app_comercial_pipeline'],
                ['value' => $contasTotal, 'label' => 'Clientes', 'sub' => 'Contas cadastradas', 'route' => 'app_comercial_clientes'],
                ['value' => $ativPendentes, 'label' => 'Atividades', 'sub' => 'Pendentes', 'route' => 'app_comercial_atividades'],
            ],
            'recent_leads' => $this->leads->findByEmpresa($empresa, null, 6),
            'pending_atividades' => $this->atividades->findPendentes($empresa, 6),
            'pipeline_preview' => $board,
            'modules' => $this->getModules(),
        ];
    }

    /** @return list<array{id: string, label: string, icon: string, route: string, subtitle: string}> */
    public function getModules(): array
    {
        return [
            ['id' => 'leads', 'label' => 'Leads', 'icon' => 'fa-user-plus', 'route' => 'app_comercial_leads', 'subtitle' => 'Captação e qualificação'],
            ['id' => 'pipeline', 'label' => 'Pipeline', 'icon' => 'fa-diagram-project', 'route' => 'app_comercial_pipeline', 'subtitle' => 'Kanban de oportunidades'],
            ['id' => 'clientes', 'label' => 'Clientes', 'icon' => 'fa-building', 'route' => 'app_comercial_clientes', 'subtitle' => 'Contas e contas ativas'],
            ['id' => 'atividades', 'label' => 'Atividades', 'icon' => 'fa-list-check', 'route' => 'app_comercial_atividades', 'subtitle' => 'Ligações, reuniões e tarefas'],
            ['id' => 'analytics', 'label' => 'Analytics', 'icon' => 'fa-chart-pie', 'route' => 'app_comercial_analytics', 'subtitle' => 'Funil e conversão'],
        ];
    }

    /** @return array<string, list<CrmOportunidade>> */
    public function buildPipelineBoard(Empresa $empresa): array
    {
        $board = [];
        foreach (CrmOportunidade::stagesAll() as $stage) {
            $board[$stage] = [];
        }
        foreach ($this->oportunidades->findByEmpresa($empresa) as $op) {
            $stage = $op->getEstagio();
            if (!isset($board[$stage])) {
                $board[$stage] = [];
            }
            $board[$stage][] = $op;
        }

        return $board;
    }

    /** @return array<string, mixed> */
    public function getAnalytics(Empresa $empresa): array
    {
        $byOrigem = [];
        foreach (CrmLead::origemList() as $origem) {
            $byOrigem[$origem] = 0;
        }
        foreach ($this->leads->findByEmpresa($empresa, null, 500) as $lead) {
            $o = $lead->getOrigem();
            $byOrigem[$o] = ($byOrigem[$o] ?? 0) + 1;
        }

        $byStage = [];
        foreach (CrmOportunidade::stagesAll() as $stage) {
            $byStage[$stage] = $this->oportunidades->countByEmpresaAndStage($empresa, $stage);
        }

        $leadsTotal = $this->leads->countByEmpresa($empresa);
        $convertidos = $this->leads->countByEmpresa($empresa, CrmLead::STATUS_CONVERTIDO);
        $ganhos = $byStage[CrmOportunidade::STAGE_GANHO] ?? 0;
        $perdidos = $byStage[CrmOportunidade::STAGE_PERDIDO] ?? 0;
        $fechados = $ganhos + $perdidos;

        return [
            'leads_total' => $leadsTotal,
            'leads_convertidos' => $convertidos,
            'conversao_lead_pct' => $leadsTotal > 0 ? round(($convertidos / $leadsTotal) * 100, 1) : null,
            'win_rate_pct' => $fechados > 0 ? round(($ganhos / $fechados) * 100, 1) : null,
            'pipeline_valor' => $this->oportunidades->sumValorAberto($empresa),
            'by_origem' => $byOrigem,
            'by_stage' => $byStage,
            'stage_meta' => CrmOportunidade::stageMeta(),
        ];
    }

    public function loadLead(Empresa $empresa, int $id): CrmLead
    {
        $lead = $this->leads->find($id);
        if (!$lead instanceof CrmLead || $lead->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Lead não encontrado.');
        }

        return $lead;
    }

    public function loadConta(Empresa $empresa, int $id): CrmConta
    {
        $conta = $this->contas->find($id);
        if (!$conta instanceof CrmConta || $conta->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Cliente não encontrado.');
        }

        return $conta;
    }

    public function loadOportunidade(Empresa $empresa, int $id): CrmOportunidade
    {
        $op = $this->oportunidades->find($id);
        if (!$op instanceof CrmOportunidade || $op->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Oportunidade não encontrada.');
        }

        return $op;
    }

    public function loadAtividade(Empresa $empresa, int $id): CrmAtividade
    {
        $a = $this->atividades->find($id);
        if (!$a instanceof CrmAtividade || $a->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Atividade não encontrada.');
        }

        return $a;
    }

    /** @param array<string, mixed> $data */
    public function createLead(Empresa $empresa, User $user, array $data): CrmLead
    {
        $lead = new CrmLead();
        $lead->setEmpresa($empresa);
        $lead->setResponsavel($user);
        $this->applyLeadData($lead, $data);
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }

    /** @param array<string, mixed> $data */
    public function updateLead(CrmLead $lead, array $data): CrmLead
    {
        $this->applyLeadData($lead, $data);
        $lead->touch();
        $this->em->flush();

        return $lead;
    }

    public function deleteLead(CrmLead $lead): void
    {
        $this->em->remove($lead);
        $this->em->flush();
    }

    /**
     * Converte lead em conta + oportunidade no estágio lead.
     */
    public function convertLead(CrmLead $lead, User $user): CrmConta
    {
        $conta = new CrmConta();
        $conta->setEmpresa($lead->getEmpresa());
        $conta->setNome($lead->getEmpresaNome() ?: $lead->getNome());
        $conta->setEmail($lead->getEmail());
        $conta->setTelefone($lead->getTelefone());
        $conta->setStatus(CrmConta::STATUS_PROSPECT);
        $conta->setOwner($user);
        $conta->setNotas('Convertido do lead #' . $lead->getId() . ' — ' . $lead->getNome());

        $op = new CrmOportunidade();
        $op->setEmpresa($lead->getEmpresa());
        $op->setConta($conta);
        $op->setLead($lead);
        $op->setTitulo('Oportunidade — ' . $conta->getNome());
        $op->setEstagio(CrmOportunidade::STAGE_QUALIFICACAO);
        $op->setProbabilidade(40);
        $op->setOwner($user);

        $lead->setStatus(CrmLead::STATUS_CONVERTIDO);

        $this->em->persist($conta);
        $this->em->persist($op);
        $this->em->flush();

        return $conta;
    }

    /**
     * Converte lead em paciente clínico (+ conta CRM se ainda não convertido).
     */
    public function convertLeadToPaciente(CrmLead $lead, User $user): PosOperatorioPaciente
    {
        if ($lead->getStatus() !== CrmLead::STATUS_CONVERTIDO) {
            $this->convertLead($lead, $user);
        }

        $obs = trim((string) $lead->getNotas());
        $bridgeNote = 'CRM lead #'.$lead->getId().' · '.$lead->getNome();
        if ($obs !== '') {
            $bridgeNote .= "\n".$obs;
        }

        $payload = [
            'nome' => $lead->getNome(),
            'telefone' => (string) ($lead->getTelefone() ?? ''),
            'email_contato' => (string) ($lead->getEmail() ?? ''),
            'observacoes' => $bridgeNote,
        ];

        $ativos = $this->protocolos->findAtivosByEmpresa($lead->getEmpresa());
        if ($ativos !== [] && $ativos[0]->getId() !== null) {
            $payload['protocolo_id'] = $ativos[0]->getId();
        }

        return $this->pacientes->create($lead->getEmpresa(), $payload, $user);
    }

    /** @param array<string, mixed> $data */
    public function createConta(Empresa $empresa, User $user, array $data): CrmConta
    {
        $conta = new CrmConta();
        $conta->setEmpresa($empresa);
        $conta->setOwner($user);
        $this->applyContaData($conta, $data);
        $this->em->persist($conta);
        $this->em->flush();

        return $conta;
    }

    /** @param array<string, mixed> $data */
    public function updateConta(CrmConta $conta, array $data): CrmConta
    {
        $this->applyContaData($conta, $data);
        $conta->touch();
        $this->em->flush();

        return $conta;
    }

    public function deleteConta(CrmConta $conta): void
    {
        $this->em->remove($conta);
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    public function createOportunidade(Empresa $empresa, User $user, array $data): CrmOportunidade
    {
        $op = new CrmOportunidade();
        $op->setEmpresa($empresa);
        $op->setOwner($user);
        $this->applyOportunidadeData($op, $empresa, $data);
        $this->em->persist($op);
        $this->em->flush();

        return $op;
    }

    /** @param array<string, mixed> $data */
    public function updateOportunidade(CrmOportunidade $op, Empresa $empresa, array $data): CrmOportunidade
    {
        $this->applyOportunidadeData($op, $empresa, $data);
        $op->touch();
        $this->em->flush();

        return $op;
    }

    public function moveOportunidade(CrmOportunidade $op, string $estagio): CrmOportunidade
    {
        if (!\in_array($estagio, CrmOportunidade::stagesAll(), true)) {
            throw new \InvalidArgumentException('Estágio inválido.');
        }
        $op->setEstagio($estagio);
        if ($estagio === CrmOportunidade::STAGE_GANHO) {
            $op->setProbabilidade(100);
        } elseif ($estagio === CrmOportunidade::STAGE_PERDIDO) {
            $op->setProbabilidade(0);
        }
        $this->em->flush();

        return $op;
    }

    public function deleteOportunidade(CrmOportunidade $op): void
    {
        $this->em->remove($op);
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    public function createAtividade(Empresa $empresa, User $user, array $data): CrmAtividade
    {
        $a = new CrmAtividade();
        $a->setEmpresa($empresa);
        $a->setResponsavel($user);
        $this->applyAtividadeData($a, $empresa, $data);
        $this->em->persist($a);
        $this->em->flush();

        return $a;
    }

    public function concluirAtividade(CrmAtividade $a, bool $concluida = true): CrmAtividade
    {
        $a->setConcluida($concluida);
        $this->em->flush();

        return $a;
    }

    public function deleteAtividade(CrmAtividade $a): void
    {
        $this->em->remove($a);
        $this->em->flush();
    }

    /** Seed leve para demo quando o tenant ainda não tem CRM. */
    public function ensureDemoData(Empresa $empresa, User $user): void
    {
        if ($this->leads->countByEmpresa($empresa) > 0 || $this->contas->countByEmpresa($empresa) > 0) {
            return;
        }

        $lead1 = $this->createLead($empresa, $user, [
            'nome' => 'Marina Alves',
            'email' => 'marina@acme.exemplo',
            'telefone' => '(11) 98888-1001',
            'empresa_nome' => 'Acme Indústria',
            'cargo' => 'Head de People',
            'origem' => CrmLead::ORIGEM_SITE,
            'status' => CrmLead::STATUS_QUALIFICANDO,
            'notas' => 'Pediu demo do Núcleo RH + Recrutamento.',
        ]);
        $this->createLead($empresa, $user, [
            'nome' => 'Pedro Lima',
            'email' => 'pedro@nortech.exemplo',
            'telefone' => '(21) 97777-2002',
            'empresa_nome' => 'Nortech Soft',
            'cargo' => 'CEO',
            'origem' => CrmLead::ORIGEM_INDICACAO,
            'status' => CrmLead::STATUS_NOVO,
        ]);

        $conta = $this->createConta($empresa, $user, [
            'nome' => 'Grupo Horizonte',
            'documento' => '12.345.678/0001-90',
            'email' => 'compras@horizonte.exemplo',
            'telefone' => '(11) 3000-4000',
            'segmento' => 'Serviços',
            'status' => CrmConta::STATUS_ATIVO,
        ]);

        $this->createOportunidade($empresa, $user, [
            'titulo' => 'Unio Work — plano Rede',
            'estagio' => CrmOportunidade::STAGE_PROPOSTA,
            'valor' => '48000.00',
            'probabilidade' => 60,
            'conta_id' => $conta->getId(),
            'notas' => 'Proposta enviada; aguardando comitê.',
        ]);
        $this->createOportunidade($empresa, $user, [
            'titulo' => 'Acme — pacote RH',
            'estagio' => CrmOportunidade::STAGE_QUALIFICACAO,
            'valor' => '18000.00',
            'probabilidade' => 35,
            'lead_id' => $lead1->getId(),
        ]);

        $this->createAtividade($empresa, $user, [
            'tipo' => CrmAtividade::TIPO_REUNIAO,
            'titulo' => 'Demo com Marina (Acme)',
            'descricao' => 'Apresentar hub RH e Recrutamento.',
            'vence_em' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d'),
            'lead_id' => $lead1->getId(),
        ]);
        $this->createAtividade($empresa, $user, [
            'tipo' => CrmAtividade::TIPO_LIGACAO,
            'titulo' => 'Follow-up Grupo Horizonte',
            'vence_em' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
            'conta_id' => $conta->getId(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function applyLeadData(CrmLead $lead, array $data): void
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw new \InvalidArgumentException('Informe o nome do lead.');
        }
        $lead->setNome($nome);
        $lead->setEmail($this->nullableString($data['email'] ?? null));
        $lead->setTelefone($this->nullableString($data['telefone'] ?? null));
        $lead->setEmpresaNome($this->nullableString($data['empresa_nome'] ?? null));
        $lead->setCargo($this->nullableString($data['cargo'] ?? null));
        $origem = (string) ($data['origem'] ?? CrmLead::ORIGEM_MANUAL);
        if (!\in_array($origem, CrmLead::origemList(), true)) {
            $origem = CrmLead::ORIGEM_MANUAL;
        }
        $lead->setOrigem($origem);
        $status = (string) ($data['status'] ?? $lead->getStatus());
        if (!\in_array($status, CrmLead::statusList(), true)) {
            $status = CrmLead::STATUS_NOVO;
        }
        $lead->setStatus($status);
        $lead->setNotas($this->nullableString($data['notas'] ?? null));
    }

    /** @param array<string, mixed> $data */
    private function applyContaData(CrmConta $conta, array $data): void
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw new \InvalidArgumentException('Informe o nome do cliente.');
        }
        $conta->setNome($nome);
        $conta->setDocumento($this->nullableString($data['documento'] ?? null));
        $conta->setEmail($this->nullableString($data['email'] ?? null));
        $conta->setTelefone($this->nullableString($data['telefone'] ?? null));
        $conta->setSite($this->nullableString($data['site'] ?? null));
        $conta->setSegmento($this->nullableString($data['segmento'] ?? null));
        $status = (string) ($data['status'] ?? $conta->getStatus());
        if (!\in_array($status, CrmConta::statusList(), true)) {
            $status = CrmConta::STATUS_PROSPECT;
        }
        $conta->setStatus($status);
        $conta->setNotas($this->nullableString($data['notas'] ?? null));
    }

    /** @param array<string, mixed> $data */
    private function applyOportunidadeData(CrmOportunidade $op, Empresa $empresa, array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('Informe o título da oportunidade.');
        }
        $op->setTitulo($titulo);
        $estagio = (string) ($data['estagio'] ?? $op->getEstagio());
        if (!\in_array($estagio, CrmOportunidade::stagesAll(), true)) {
            $estagio = CrmOportunidade::STAGE_LEAD;
        }
        $op->setEstagio($estagio);
        $valor = trim((string) ($data['valor'] ?? ''));
        $op->setValor($valor !== '' ? str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', $valor) ?? '') : null);
        $op->setProbabilidade((int) ($data['probabilidade'] ?? $op->getProbabilidade()));
        $fecha = trim((string) ($data['fecha_prevista'] ?? ''));
        $op->setFechaPrevista($fecha !== '' ? new \DateTimeImmutable($fecha) : null);
        $op->setNotas($this->nullableString($data['notas'] ?? null));

        $contaId = (int) ($data['conta_id'] ?? 0);
        $op->setConta($contaId > 0 ? $this->loadConta($empresa, $contaId) : null);
        $leadId = (int) ($data['lead_id'] ?? 0);
        $op->setLead($leadId > 0 ? $this->loadLead($empresa, $leadId) : null);
    }

    /** @param array<string, mixed> $data */
    private function applyAtividadeData(CrmAtividade $a, Empresa $empresa, array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('Informe o título da atividade.');
        }
        $a->setTitulo($titulo);
        $tipo = (string) ($data['tipo'] ?? CrmAtividade::TIPO_TAREFA);
        if (!\in_array($tipo, CrmAtividade::tipoList(), true)) {
            $tipo = CrmAtividade::TIPO_TAREFA;
        }
        $a->setTipo($tipo);
        $a->setDescricao($this->nullableString($data['descricao'] ?? null));
        $vence = trim((string) ($data['vence_em'] ?? ''));
        $a->setVenceEm($vence !== '' ? new \DateTimeImmutable($vence) : null);

        $leadId = (int) ($data['lead_id'] ?? 0);
        $a->setLead($leadId > 0 ? $this->loadLead($empresa, $leadId) : null);
        $contaId = (int) ($data['conta_id'] ?? 0);
        $a->setConta($contaId > 0 ? $this->loadConta($empresa, $contaId) : null);
        $opId = (int) ($data['oportunidade_id'] ?? 0);
        $a->setOportunidade($opId > 0 ? $this->loadOportunidade($empresa, $opId) : null);
    }

    private function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }

    /** @param array<string, list<CrmOportunidade>> $board */
    private function countOpenDeals(array $board): int
    {
        $n = 0;
        foreach (CrmOportunidade::stagesOpen() as $stage) {
            $n += \count($board[$stage] ?? []);
        }

        return $n;
    }

    private function formatMoney(float $value): string
    {
        if ($value >= 1000000) {
            return 'R$ ' . number_format($value / 1000000, 1, ',', '.') . 'M';
        }
        if ($value >= 1000) {
            return 'R$ ' . number_format($value / 1000, 1, ',', '.') . 'k';
        }

        return 'R$ ' . number_format($value, 0, ',', '.');
    }
}
