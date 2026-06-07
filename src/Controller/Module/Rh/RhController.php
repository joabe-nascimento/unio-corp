<?php

namespace App\Controller\Module\Rh;

use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Entity\RhProcessDocument;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Rh\RhProcessDisplay;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\OnboardingProgressService;
use App\Service\RhDocumentService;
use App\Service\RhHubService;
use App\Service\RhOffboardingService;
use App\Service\RhOnboardingService;
use App\Service\Analytics\RhAnalyticsService;
use App\Service\RhUserProvisioningService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_USER')]
class RhController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhOnboardingService $onboarding,
        private RhOffboardingService $offboarding,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private FuncionarioRepository $funcionarioRepo,
        private RhHubService $hub,
        private RhDocumentService $documents,
        private RhUserProvisioningService $userProvisioning,
        private RhAnalyticsService $rhAnalytics,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->requireEmpresa();
        $chartPayload = $this->rhAnalytics->getHubChartPayload($user, $empresa);

        return $this->render(self::T . 'index.html.twig', array_merge(
            [
                'empresa' => $empresa,
                'chart_sections' => $chartPayload['sections'],
                'chart_executive' => $chartPayload['executive'],
            ],
            $this->hub->dashboard($empresa),
        ));
    }

    #[Route('/hub/ticker', name: 'app_rh_hub_ticker', methods: ['GET'])]
    public function hubTicker(): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $slides = [];

        foreach ($this->hub->tickerSlides($empresa) as $slide) {
            $item = [
                'tag' => $slide['tag'],
                'title' => $slide['title'],
                'text' => $slide['text'],
                'icon' => $slide['icon'],
                'tone' => $slide['tone'],
            ];
            if (isset($slide['route'])) {
                $item['url'] = $this->generateUrl($slide['route'], $slide['route_params'] ?? []);
                $item['route_label'] = $slide['route_label'] ?? 'Ver mais';
            }
            $slides[] = $item;
        }

        return $this->json([
            'slides' => $slides,
            'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/admissoes', name: 'app_rh_admissoes')]
    public function admissoes(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->renderAdmissoesList($empresa, $request);
    }

    #[Route('/admissoes/nova', name: 'app_rh_admissoes_nova', methods: ['GET', 'POST'])]
    public function admissoesNova(Request $request, OnboardingProgressService $onboarding): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_rh_admissoes', ['open_nova' => 1]);
        }

        try {
            if ($csrf = $this->validateCsrfOrJson($request, 'rh_admissao_form')) {
                return $csrf;
            }
            $nome = trim((string) $request->request->get('nome', ''));
            $email = trim((string) $request->request->get('email', ''));
            if ($nome === '' || $email === '') {
                throw new RhProcessException('Nome e e-mail são obrigatórios.');
            }
            if (RhProcessDisplay::isGenericHubName($nome)) {
                throw new RhProcessException('Informe o nome do colaborador, não o nome de um núcleo ou módulo.');
            }
            if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                throw new RhProcessException('Informe um e-mail válido.');
            }
            $process = $this->onboarding->create(
                $empresa,
                $nome,
                $email,
                $request->request->get('cargo') ?: null,
                $this->parseDate($request->request->get('data_prevista')),
                $request->request->get('observacoes') ?: null,
            );
            $onboarding->markStepComplete('funcionario');
            $message = 'Processo de onboarding iniciado.';
            $this->addFlash('success', $message);

            if ($this->wantsJson($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => $message,
                    'process' => $this->onboardingProcessPayload($process),
                ]);
            }

            return $this->redirectToRoute('app_rh_admissoes_show', ['id' => $process->getId()]);
        } catch (RhProcessException $e) {
            if ($this->wantsJson($request)) {
                return $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
            }

            $this->addFlash('error', $e->getMessage());

            return $this->renderAdmissoesList($empresa, $request, [
                'open_admissao_offcanvas' => true,
                'nome' => $request->request->get('nome'),
                'email' => $request->request->get('email'),
                'cargo' => $request->request->get('cargo'),
                'data_prevista' => $request->request->get('data_prevista'),
                'observacoes' => $request->request->get('observacoes'),
            ]);
        }
    }

    #[Route('/admissoes/{id}', name: 'app_rh_admissoes_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function admissoesShow(int $id, Request $request, OnboardingProgressService $onboarding): Response
    {
        $process = $this->loadOnboarding($id);

        if ($request->isMethod('POST')) {
            if ($csrf = $this->validateCsrfOrJson($request, 'rh_process_action')) {
                return $csrf;
            }

            $action = (string) $request->request->get('action');
            try {
                match ($action) {
                    'toggle' => $this->onboarding->toggleChecklistItem(
                        $process,
                        (string) $request->request->get('item_id', ''),
                        $request->request->getBoolean('done')
                    ),
                    'complete' => (function () use ($process, $onboarding) {
                        $this->onboarding->complete($process);
                        $onboarding->markStepComplete('funcionario');
                        $this->addFlash('success', 'Onboarding concluído. Funcionário cadastrado.');
                    })(),
                    'cancel' => (function () use ($process) {
                        $this->onboarding->cancel($process);
                        $this->addFlash('success', 'Onboarding cancelado.');
                    })(),
                    'update' => (function () use ($process, $request) {
                        $this->onboarding->update(
                            $process,
                            trim((string) $request->request->get('nome', '')),
                            trim((string) $request->request->get('email', '')),
                            $request->request->get('cargo') ?: null,
                            $this->parseDate($request->request->get('data_prevista')),
                            $request->request->get('observacoes') ?: null,
                        );
                        $this->addFlash('success', 'Dados atualizados.');
                    })(),
                    'provision_user' => (function () use ($process, $request) {
                        $senha = (string) $request->request->get('senha', '');
                        $this->userProvisioning->provisionFromOnboarding($process, $senha, (string) $request->request->get('perfil', 'MEMBRO'));
                        $this->addFlash('success', 'Usuário da plataforma criado e checklist atualizado.');
                    })(),
                    'link_existing_user' => (function () use ($process, $request) {
                        $this->userProvisioning->linkExistingUserFromOnboarding(
                            $process,
                            (string) $request->request->get('perfil', 'MEMBRO'),
                        );
                        $this->addFlash('success', 'Conta existente vinculada à empresa e checklist atualizado.');
                    })(),
                    'upload_doc' => (function () use ($process, $request) {
                        $file = $request->files->get('documento');
                        if (!$file) {
                            throw new RhProcessException('Selecione um arquivo.');
                        }
                        /** @var User $user */
                        $user = $this->getUser();
                        $this->documents->uploadForOnboarding($process, $file, RhProcessDocument::CAT_ADMISSIONAL, $user);
                        $this->addFlash('success', 'Documento anexado.');
                    })(),
                    default => throw new RhProcessException('Ação inválida.'),
                };
            } catch (RhProcessException $e) {
                if ($this->wantsJson($request)) {
                    return $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
                }

                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_rh_admissoes_show', ['id' => $id]);
            }

            if ($this->wantsJson($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => $this->processActionMessage($action, 'onboarding'),
                    'process' => $this->onboardingProcessPayload($process),
                ]);
            }

            return $this->redirectToRoute('app_rh_admissoes_show', ['id' => $id]);
        }

        return $this->render(self::T . 'admissao_show.html.twig', [
            'process' => $process,
            'documentos' => $this->documents->listOnboarding($process),
            'platformAccount' => $this->userProvisioning->resolvePlatformAccountState($process),
        ]);
    }

    #[Route('/demissoes', name: 'app_rh_demissoes')]
    public function demissoes(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->renderDemissoesList($empresa, $request);
    }

    #[Route('/demissoes/nova', name: 'app_rh_demissoes_nova', methods: ['GET', 'POST'])]
    public function demissoesNova(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $funcionarios = $this->offboarding->listActiveFuncionariosForOffboarding($empresa);
        $allActive = $this->offboarding->listActiveFuncionarios($empresa);

        if ($request->isMethod('GET')) {
            $params = ['open_nova' => 1];
            $preselect = (int) $request->query->get('funcionario_id', 0);
            if ($preselect > 0) {
                $params['funcionario_id'] = $preselect;
            }

            return $this->redirectToRoute('app_rh_demissoes', $params);
        }

        $funcionarioId = (int) $request->request->get('funcionario_id', 0);
        $funcionario = $funcionarioId > 0
            ? $this->funcionarioRepo->findOneBy(['id' => $funcionarioId, 'empresa' => $empresa])
            : null;

        if (!$funcionario) {
            if ($this->wantsJson($request)) {
                return $this->json(['ok' => false, 'error' => 'Selecione um funcionário ativo.'], 400);
            }

            $this->addFlash('error', 'Selecione um funcionário ativo.');

            return $this->renderDemissoesList($empresa, $request, [
                'open_demissao_offcanvas' => true,
                'funcionario_id' => $funcionarioId > 0 ? $funcionarioId : '',
                'data_prevista' => $request->request->get('data_prevista'),
                'motivo' => $request->request->get('motivo'),
                'observacoes' => $request->request->get('observacoes'),
            ]);
        }

        try {
            if ($csrf = $this->validateCsrfOrJson($request, 'rh_demissao_form')) {
                return $csrf;
            }
            $process = $this->offboarding->create(
                $empresa,
                $funcionario,
                $this->parseDate($request->request->get('data_prevista')),
                $request->request->get('motivo') ?: null,
                $request->request->get('observacoes') ?: null,
            );
            $message = 'Processo de offboarding iniciado.';
            $this->addFlash('success', $message);

            if ($this->wantsJson($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => $message,
                    'process' => $this->offboardingProcessPayload($process),
                ]);
            }

            return $this->redirectToRoute('app_rh_demissoes_show', ['id' => $process->getId()]);
        } catch (RhProcessException $e) {
            if ($this->wantsJson($request)) {
                return $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
            }

            $this->addFlash('error', $e->getMessage());

            return $this->renderDemissoesList($empresa, $request, [
                'open_demissao_offcanvas' => true,
                'funcionario_id' => $funcionarioId,
                'data_prevista' => $request->request->get('data_prevista'),
                'motivo' => $request->request->get('motivo'),
                'observacoes' => $request->request->get('observacoes'),
            ]);
        }
    }

    #[Route('/demissoes/{id}', name: 'app_rh_demissoes_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function demissoesShow(int $id, Request $request): Response
    {
        $process = $this->loadOffboarding($id);

        if ($request->isMethod('POST')) {
            if ($csrf = $this->validateCsrfOrJson($request, 'rh_process_action')) {
                return $csrf;
            }

            $action = (string) $request->request->get('action');
            try {
                match ($action) {
                    'toggle' => $this->offboarding->toggleChecklistItem(
                        $process,
                        (string) $request->request->get('item_id', ''),
                        $request->request->getBoolean('done')
                    ),
                    'complete' => (function () use ($process) {
                        $this->offboarding->complete($process);
                        $this->addFlash('success', 'Offboarding concluído. Funcionário desativado.');
                    })(),
                    'cancel' => (function () use ($process) {
                        $this->offboarding->cancel($process);
                        $this->addFlash('success', 'Offboarding cancelado.');
                    })(),
                    'upload_doc' => (function () use ($process, $request) {
                        $file = $request->files->get('documento');
                        if (!$file) {
                            throw new RhProcessException('Selecione um arquivo.');
                        }
                        /** @var User $user */
                        $user = $this->getUser();
                        $this->documents->uploadForOffboarding($process, $file, RhProcessDocument::CAT_RESCISORIA, $user);
                        $this->addFlash('success', 'Documento anexado.');
                    })(),
                    default => throw new RhProcessException('Ação inválida.'),
                };
            } catch (RhProcessException $e) {
                if ($this->wantsJson($request)) {
                    return $this->json(['ok' => false, 'error' => $e->getMessage()], 400);
                }

                $this->addFlash('error', $e->getMessage());

                return $this->redirectToRoute('app_rh_demissoes_show', ['id' => $id]);
            }

            if ($this->wantsJson($request)) {
                return $this->json([
                    'ok' => true,
                    'message' => $this->processActionMessage($action, 'offboarding'),
                    'process' => $this->offboardingProcessPayload($process),
                ]);
            }

            return $this->redirectToRoute('app_rh_demissoes_show', ['id' => $id]);
        }

        return $this->render(self::T . 'demissao_show.html.twig', [
            'process' => $process,
            'documentos' => $this->documents->listOffboarding($process),
        ]);
    }

    private function renderAdmissoesList(\App\Entity\Empresa $empresa, Request $request, array $formData = []): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', '');

        return $this->render(self::T . 'admissoes.html.twig', array_merge([
            'processos' => $this->onboarding->listForEmpresa($empresa, $q !== '' ? $q : null, $status !== '' ? $status : null),
            'filter_q' => $q,
            'filter_status' => $status,
            'empresa' => $empresa,
            'checklist_preview' => RhOnboardingProcess::defaultChecklist(),
            'open_admissao_offcanvas' => $request->query->getBoolean('open_nova'),
        ], $formData));
    }

    private function renderAdmissaoForm(\App\Entity\Empresa $empresa, array $formData = []): Response
    {
        return $this->render(self::T . 'admissao_form.html.twig', array_merge([
            'empresa' => $empresa,
            'checklist_preview' => RhOnboardingProcess::defaultChecklist(),
        ], $formData));
    }

    private function renderDemissoesList(\App\Entity\Empresa $empresa, Request $request, array $formData = []): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', '');
        $funcionarios = $this->offboarding->listActiveFuncionariosForOffboarding($empresa);
        $allActive = $this->offboarding->listActiveFuncionarios($empresa);

        return $this->render(self::T . 'demissoes.html.twig', array_merge([
            'processos' => $this->offboarding->listForEmpresa($empresa, $q !== '' ? $q : null, $status !== '' ? $status : null),
            'filter_q' => $q,
            'filter_status' => $status,
            'empresa' => $empresa,
            'funcionarios_offboarding' => $funcionarios,
            'checklist_preview' => RhOffboardingProcess::defaultChecklist(),
            'all_active_blocked' => \count($allActive) > 0 && \count($funcionarios) === 0,
            'open_demissao_offcanvas' => $request->query->getBoolean('open_nova'),
            'funcionario_id' => $request->query->get('funcionario_id', ''),
        ], $formData));
    }

    private function renderDemissaoForm(\App\Entity\Empresa $empresa, array $funcionarios, array $formData = []): Response
    {
        return $this->render(self::T . 'demissao_form.html.twig', array_merge([
            'empresa' => $empresa,
            'funcionarios' => $funcionarios,
            'checklist_preview' => RhOffboardingProcess::defaultChecklist(),
        ], $formData));
    }

    private function loadOnboarding(int $id): RhOnboardingProcess
    {
        $empresa = $this->requireEmpresa();
        $process = $this->onboardingRepo->find($id);

        if (!$process || $process->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException();
        }

        return $process;
    }

    private function loadOffboarding(int $id): RhOffboardingProcess
    {
        $empresa = $this->requireEmpresa();
        $process = $this->offboardingRepo->find($id);

        if (!$process || $process->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException();
        }

        return $process;
    }

    private function wantsJson(Request $request): bool
    {
        $accept = $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json')
            || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }

    private function validateCsrfOrJson(Request $request, string $tokenId): ?JsonResponse
    {
        if ($this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            return null;
        }

        if ($this->wantsJson($request)) {
            return $this->json(['ok' => false, 'error' => 'Token de segurança inválido.'], 403);
        }

        throw $this->createAccessDeniedException('Token de segurança inválido.');
    }

    /** @return array<string, mixed> */
    private function onboardingProcessPayload(RhOnboardingProcess $process): array
    {
        return [
            'id' => $process->getId(),
            'nome' => $process->getNome(),
            'email' => $process->getEmail(),
            'cargo' => $process->getCargo(),
            'checklist' => $process->getChecklist(),
            'progress' => $process->checklistProgress(),
            'doneCount' => $process->checklistDoneCount(),
            'totalCount' => \count($process->getChecklist()),
            'status' => $process->getStatus(),
            'complete' => $process->isChecklistComplete(),
        ];
    }

    /** @return array<string, mixed> */
    private function offboardingProcessPayload(RhOffboardingProcess $process): array
    {
        $funcionario = $process->getFuncionario();

        return [
            'id' => $process->getId(),
            'nome' => $funcionario->getNome(),
            'funcionarioNome' => $funcionario->getNome(),
            'cargo' => $funcionario->getCargo(),
            'motivo' => $process->getMotivo(),
            'checklist' => $process->getChecklist(),
            'progress' => $process->checklistProgress(),
            'doneCount' => $process->checklistDoneCount(),
            'totalCount' => \count($process->getChecklist()),
            'status' => $process->getStatus(),
            'complete' => $process->isChecklistComplete(),
        ];
    }

    private function processActionMessage(string $action, string $variant): string
    {
        return match ($action) {
            'toggle' => 'Item atualizado.',
            'complete' => $variant === 'offboarding'
                ? 'Offboarding concluído. Funcionário desativado.'
                : 'Onboarding concluído. Funcionário cadastrado.',
            'cancel' => $variant === 'offboarding'
                ? 'Offboarding cancelado.'
                : 'Onboarding cancelado.',
            'update' => 'Dados atualizados.',
            'provision_user' => 'Usuário da plataforma criado e checklist atualizado.',
            'link_existing_user' => 'Conta existente vinculada à empresa e checklist atualizado.',
            'upload_doc' => 'Documento anexado.',
            default => 'Ação concluída.',
        };
    }
}
