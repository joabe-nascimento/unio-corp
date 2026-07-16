<?php

namespace App\Controller\Module\Comercial;

use App\Entity\Crm\CrmAtividade;
use App\Entity\Crm\CrmConta;
use App\Entity\Crm\CrmLead;
use App\Entity\Crm\CrmOportunidade;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\Crm\CrmAtividadeRepository;
use App\Repository\Crm\CrmContaRepository;
use App\Repository\Crm\CrmLeadRepository;
use App\Service\Crm\CrmService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comercial')]
#[IsGranted('ROLE_USER')]
class ComercialController extends AbstractController
{
    private const T = 'modules/comercial/';

    public function __construct(
        private WorkspaceService $workspace,
        private CrmService $crm,
        private CrmLeadRepository $leadRepo,
        private CrmContaRepository $contaRepo,
        private CrmAtividadeRepository $atividadeRepo,
    ) {}

    #[Route('', name: 'app_comercial')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);

        if ($request->query->getBoolean('seed')) {
            $this->crm->ensureDemoData($empresa, $user);
            $this->addFlash('success', 'Dados de demonstração do CRM carregados.');

            return $this->redirectToRoute('app_comercial');
        }

        return $this->render(self::T . 'index.html.twig', $this->crm->getDashboard($user));
    }

    #[Route('/leads', name: 'app_comercial_leads', methods: ['GET', 'POST'])]
    public function leads(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);

        if ($request->isMethod('POST')) {
            $this->requireCsrf($request, 'crm_lead_novo');
            try {
                $lead = $this->crm->createLead($empresa, $user, $request->request->all());
                $this->addFlash('success', 'Lead salvo.');

                return $this->redirectToRoute('app_comercial_lead_show', ['id' => $lead->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $status = trim($request->query->getString('status'));

        return $this->render(self::T . 'leads.html.twig', [
            'crm_section' => 'leads',
            'leads' => $this->leadRepo->findByEmpresa($empresa, $status !== '' ? $status : null),
            'filter_status' => $status,
            'status_counts' => $this->leadRepo->countSummaryByEmpresa($empresa),
            'status_options' => CrmLead::statusList(),
            'status_labels' => CrmLead::statusLabels(),
            'origem_options' => CrmLead::origemList(),
            'origem_labels' => CrmLead::origemLabels(),
            'open_novo' => $request->query->getBoolean('novo'),
        ]);
    }

    #[Route('/leads/{id}', name: 'app_comercial_lead_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function leadShow(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $lead = $this->crm->loadLead($empresa, $id);

        if ($request->isMethod('POST')) {
            $this->requireCsrf($request, 'crm_lead_edit_' . $id);
            try {
                $this->crm->updateLead($lead, $request->request->all());
                $this->addFlash('success', 'Lead salvo.');

                return $this->redirectToRoute('app_comercial_lead_show', ['id' => $id]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(self::T . 'lead_show.html.twig', [
            'crm_section' => 'leads',
            'lead' => $lead,
            'status_options' => CrmLead::statusList(),
            'status_labels' => CrmLead::statusLabels(),
            'origem_options' => CrmLead::origemList(),
            'origem_labels' => CrmLead::origemLabels(),
        ]);
    }

    #[Route('/leads/{id}/converter', name: 'app_comercial_lead_converter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function leadConverter(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $lead = $this->crm->loadLead($empresa, $id);
        $this->requireCsrf($request, 'crm_lead_convert_' . $id);
        $conta = $this->crm->convertLead($lead, $user);
        $this->addFlash('success', 'Lead convertido em cliente e oportunidade no funil.');

        return $this->redirectToRoute('app_comercial_cliente_show', ['id' => $conta->getId()]);
    }

    #[Route('/leads/{id}/paciente', name: 'app_comercial_lead_paciente', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function leadPaciente(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $lead = $this->crm->loadLead($empresa, $id);
        $this->requireCsrf($request, 'crm_lead_paciente_' . $id);

        try {
            $paciente = $this->crm->convertLeadToPaciente($lead, $user);
            $this->addFlash('success', 'Paciente clínico criado a partir deste lead.');

            return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_ficha' => $paciente->getId()]);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_comercial_lead_show', ['id' => $id]);
        }
    }

    #[Route('/leads/{id}/excluir', name: 'app_comercial_lead_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function leadExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $lead = $this->crm->loadLead($empresa, $id);
        $this->requireCsrf($request, 'crm_lead_delete_' . $id);
        $this->crm->deleteLead($lead);
        $this->addFlash('success', 'Lead excluído.');

        return $this->redirectToRoute('app_comercial_leads');
    }

    #[Route('/pipeline', name: 'app_comercial_pipeline', methods: ['GET', 'POST'])]
    public function pipeline(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);

        if ($request->isMethod('POST')) {
            $this->requireCsrf($request, 'crm_oportunidade_nova');
            try {
                $op = $this->crm->createOportunidade($empresa, $user, $request->request->all());
                $this->addFlash('success', 'Oportunidade salva.');

                return $this->redirectToRoute('app_comercial_oportunidade_show', ['id' => $op->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $board = $this->crm->buildPipelineBoard($empresa);

        return $this->render(self::T . 'pipeline.html.twig', [
            'crm_section' => 'pipeline',
            'pipeline_board' => $board,
            'stage_meta' => CrmOportunidade::stageMeta(),
            'stages' => CrmOportunidade::stagesAll(),
            'contas' => $this->contaRepo->findByEmpresa($empresa),
            'leads' => $this->leadRepo->findByEmpresa($empresa, null, 50),
            'open_novo' => $request->query->getBoolean('novo'),
        ]);
    }

    #[Route('/oportunidades/{id}', name: 'app_comercial_oportunidade_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function oportunidadeShow(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $op = $this->crm->loadOportunidade($empresa, $id);

        if ($request->isMethod('POST')) {
            $this->requireCsrf($request, 'crm_oportunidade_edit_' . $id);
            try {
                $this->crm->updateOportunidade($op, $empresa, $request->request->all());
                $this->addFlash('success', 'Oportunidade salva.');

                return $this->redirectToRoute('app_comercial_oportunidade_show', ['id' => $id]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(self::T . 'oportunidade_show.html.twig', [
            'crm_section' => 'pipeline',
            'oportunidade' => $op,
            'stage_meta' => CrmOportunidade::stageMeta(),
            'stages' => CrmOportunidade::stagesAll(),
            'contas' => $this->contaRepo->findByEmpresa($empresa),
            'leads' => $this->leadRepo->findByEmpresa($empresa, null, 50),
        ]);
    }

    #[Route('/oportunidades/{id}/mover', name: 'app_comercial_oportunidade_mover', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function oportunidadeMover(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $op = $this->crm->loadOportunidade($empresa, $id);
        $this->requireCsrf($request, 'crm_oportunidade_move_' . $id);

        $from = $op->getEstagio();
        $to = $request->request->getString('estagio');
        $wantsJson = $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');

        try {
            $this->crm->moveOportunidade($op, $to);
        } catch (\InvalidArgumentException $e) {
            if ($wantsJson) {
                return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_comercial_pipeline');
        }

        if ($wantsJson) {
            return $this->json([
                'ok' => true,
                'message' => 'Estágio da oportunidade atualizado.',
                'id' => $op->getId(),
                'from_estagio' => $from,
                'to_estagio' => $op->getEstagio(),
                'probabilidade' => $op->getProbabilidade(),
            ]);
        }

        $this->addFlash('success', 'Estágio da oportunidade atualizado.');
        $back = $request->request->getString('back', 'pipeline');

        return $back === 'show'
            ? $this->redirectToRoute('app_comercial_oportunidade_show', ['id' => $id])
            : $this->redirectToRoute('app_comercial_pipeline');
    }

    #[Route('/oportunidades/{id}/excluir', name: 'app_comercial_oportunidade_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function oportunidadeExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $op = $this->crm->loadOportunidade($empresa, $id);
        $this->requireCsrf($request, 'crm_oportunidade_delete_' . $id);
        $this->crm->deleteOportunidade($op);
        $this->addFlash('success', 'Oportunidade excluída.');

        return $this->redirectToRoute('app_comercial_pipeline');
    }

    #[Route('/clientes', name: 'app_comercial_clientes', methods: ['GET', 'POST'])]
    public function clientes(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);

        if ($request->isMethod('POST')) {
            $this->requireCsrf($request, 'crm_conta_nova');
            try {
                $conta = $this->crm->createConta($empresa, $user, $request->request->all());
                $this->addFlash('success', 'Cliente salvo.');

                return $this->redirectToRoute('app_comercial_cliente_show', ['id' => $conta->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $status = trim($request->query->getString('status'));

        return $this->render(self::T . 'clientes.html.twig', [
            'crm_section' => 'clientes',
            'contas' => $this->contaRepo->findByEmpresa($empresa, $status !== '' ? $status : null),
            'filter_status' => $status,
            'status_counts' => $this->contaRepo->countSummaryByEmpresa($empresa),
            'status_options' => CrmConta::statusList(),
            'status_labels' => CrmConta::statusLabels(),
            'open_novo' => $request->query->getBoolean('novo'),
        ]);
    }

    #[Route('/clientes/{id}', name: 'app_comercial_cliente_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function clienteShow(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $conta = $this->crm->loadConta($empresa, $id);

        if ($request->isMethod('POST')) {
            $this->requireCsrf($request, 'crm_conta_edit_' . $id);
            try {
                $this->crm->updateConta($conta, $request->request->all());
                $this->addFlash('success', 'Cliente salvo.');

                return $this->redirectToRoute('app_comercial_cliente_show', ['id' => $id]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render(self::T . 'cliente_show.html.twig', [
            'crm_section' => 'clientes',
            'conta' => $conta,
            'status_options' => CrmConta::statusList(),
            'status_labels' => CrmConta::statusLabels(),
        ]);
    }

    #[Route('/clientes/{id}/excluir', name: 'app_comercial_cliente_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function clienteExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $conta = $this->crm->loadConta($empresa, $id);
        $this->requireCsrf($request, 'crm_conta_delete_' . $id);
        $this->crm->deleteConta($conta);
        $this->addFlash('success', 'Cliente excluído.');

        return $this->redirectToRoute('app_comercial_clientes');
    }

    #[Route('/atividades', name: 'app_comercial_atividades', methods: ['GET', 'POST'])]
    public function atividades(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);

        if ($request->isMethod('POST')) {
            $this->requireCsrf($request, 'crm_atividade_nova');
            try {
                $this->crm->createAtividade($empresa, $user, $request->request->all());
                $this->addFlash('success', 'Atividade salva.');

                return $this->redirectToRoute('app_comercial_atividades');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $tipo = $request->query->getString('tipo', '');
        if ($tipo !== '' && !\in_array($tipo, CrmAtividade::tipoList(), true)) {
            $tipo = '';
        }
        $vencidas = $request->query->getString('vencidas', '');
        $q = trim($request->query->getString('q'));
        $all = $this->atividadeRepo->findPendentes($empresa, 200);
        $hoje = new \DateTimeImmutable('today');
        $atividades = array_values(array_filter(
            $all,
            static function (CrmAtividade $atividade) use ($tipo, $vencidas, $q, $hoje): bool {
                if ($tipo !== '' && $atividade->getTipo() !== $tipo) {
                    return false;
                }
                if ($vencidas === '1') {
                    $vence = $atividade->getVenceEm();
                    if ($vence === null || $vence >= $hoje) {
                        return false;
                    }
                }
                if ($q !== '') {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        $atividade->getTitulo(),
                        $atividade->getDescricao(),
                        $atividade->getLead()?->getNome(),
                        $atividade->getConta()?->getNome(),
                    ])));
                    if (!str_contains($haystack, mb_strtolower($q))) {
                        return false;
                    }
                }

                return true;
            },
        ));
        $tipoLabels = CrmAtividade::tipoLabels();
        $atividadeCounts = ['total' => \count($all)];
        foreach (CrmAtividade::tipoList() as $tipoKey) {
            $atividadeCounts[$tipoKey] = \count(array_filter(
                $all,
                static fn (CrmAtividade $a) => $a->getTipo() === $tipoKey,
            ));
        }
        $atividadeCounts['vencidas'] = \count(array_filter(
            $all,
            static fn (CrmAtividade $a): bool => $a->getVenceEm() !== null && $a->getVenceEm() < $hoje,
        ));

        return $this->render(self::T . 'atividades.html.twig', [
            'crm_section' => 'atividades',
            'atividades' => $atividades,
            'tipo_options' => CrmAtividade::tipoList(),
            'tipo_labels' => $tipoLabels,
            'leads' => $this->leadRepo->findByEmpresa($empresa, null, 50),
            'contas' => $this->contaRepo->findByEmpresa($empresa),
            'open_novo' => $request->query->getBoolean('novo'),
            'filter_tipo' => $tipo,
            'filter_vencidas' => $vencidas,
            'filter_q' => $q,
            'atividade_counts' => $atividadeCounts,
        ]);
    }

    #[Route('/atividades/{id}/concluir', name: 'app_comercial_atividade_concluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function atividadeConcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $a = $this->crm->loadAtividade($empresa, $id);
        $this->requireCsrf($request, 'crm_atividade_done_' . $id);
        $this->crm->concluirAtividade($a, true);
        $this->addFlash('success', 'Atividade concluída.');

        return $this->redirectToRoute('app_comercial_atividades');
    }

    #[Route('/atividades/{id}/excluir', name: 'app_comercial_atividade_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function atividadeExcluir(int $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);
        $a = $this->crm->loadAtividade($empresa, $id);
        $this->requireCsrf($request, 'crm_atividade_delete_' . $id);
        $this->crm->deleteAtividade($a);
        $this->addFlash('success', 'Atividade excluída.');

        return $this->redirectToRoute('app_comercial_atividades');
    }

    #[Route('/analytics', name: 'app_comercial_analytics')]
    public function analytics(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa($user);

        return $this->render(self::T . 'analytics.html.twig', [
            'crm_section' => 'analytics',
            'analytics' => $this->crm->getAnalytics($empresa),
        ]);
    }

    private function requireEmpresa(User $user): Empresa
    {
        try {
            return $this->crm->requireEmpresa($user);
        } catch (\RuntimeException $e) {
            throw $this->createAccessDeniedException($e->getMessage());
        }
    }

    private function requireCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Sessão expirada ou formulário inválido. Atualize a página e tente de novo.');
        }
    }
}
