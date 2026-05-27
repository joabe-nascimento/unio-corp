<?php

namespace App\Controller\Module\Rh;

use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Entity\RhProcessDocument;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\OnboardingProgressService;
use App\Service\RhDocumentService;
use App\Service\RhHubService;
use App\Service\RhOffboardingService;
use App\Service\RhOnboardingService;
use App\Service\RhUserProvisioningService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'index.html.twig', array_merge(
            ['empresa' => $empresa],
            $this->hub->dashboard($empresa)
        ));
    }

    #[Route('/admissoes', name: 'app_rh_admissoes')]
    public function admissoes(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', '');

        return $this->render(self::T . 'admissoes.html.twig', [
            'processos' => $this->onboarding->listForEmpresa($empresa, $q !== '' ? $q : null, $status !== '' ? $status : null),
            'filter_q' => $q,
            'filter_status' => $status,
        ]);
    }

    #[Route('/admissoes/nova', name: 'app_rh_admissoes_nova', methods: ['GET', 'POST'])]
    public function admissoesNova(Request $request, OnboardingProgressService $onboarding): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_admissao_form');
                $nome = trim((string) $request->request->get('nome', ''));
                $email = trim((string) $request->request->get('email', ''));
                if ($nome === '' || $email === '') {
                    throw new RhProcessException('Nome e e-mail são obrigatórios.');
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
                $this->addFlash('success', 'Processo de onboarding iniciado.');

                return $this->redirectToRoute('app_rh_admissoes_show', ['id' => $process->getId()]);
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->renderAdmissaoForm($empresa, [
                    'nome' => $request->request->get('nome'),
                    'email' => $request->request->get('email'),
                    'cargo' => $request->request->get('cargo'),
                    'data_prevista' => $request->request->get('data_prevista'),
                    'observacoes' => $request->request->get('observacoes'),
                ]);
            }
        }

        return $this->renderAdmissaoForm($empresa);
    }

    #[Route('/admissoes/{id}', name: 'app_rh_admissoes_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function admissoesShow(int $id, Request $request, OnboardingProgressService $onboarding): Response
    {
        $process = $this->loadOnboarding($id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_process_action');
                $action = $request->request->get('action');
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
                        /** @var User $user */
                        $user = $this->getUser();
                        $senha = (string) $request->request->get('senha', '');
                        $this->userProvisioning->provisionFromOnboarding($process, $senha, (string) $request->request->get('perfil', 'MEMBRO'));
                        $this->addFlash('success', 'Usuário da plataforma criado e checklist atualizado.');
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
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_admissoes_show', ['id' => $id]);
        }

        return $this->render(self::T . 'admissao_show.html.twig', [
            'process' => $process,
            'documentos' => $this->documents->listOnboarding($process),
        ]);
    }

    #[Route('/demissoes', name: 'app_rh_demissoes')]
    public function demissoes(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', '');

        return $this->render(self::T . 'demissoes.html.twig', [
            'processos' => $this->offboarding->listForEmpresa($empresa, $q !== '' ? $q : null, $status !== '' ? $status : null),
            'filter_q' => $q,
            'filter_status' => $status,
        ]);
    }

    #[Route('/demissoes/nova', name: 'app_rh_demissoes_nova', methods: ['GET', 'POST'])]
    public function demissoesNova(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $funcionarios = $this->offboarding->listActiveFuncionariosForOffboarding($empresa);

        if ($request->isMethod('POST')) {
            $funcionarioId = (int) $request->request->get('funcionario_id', 0);
            $funcionario = $funcionarioId > 0
                ? $this->funcionarioRepo->findOneBy(['id' => $funcionarioId, 'empresa' => $empresa])
                : null;

            if (!$funcionario) {
                $this->addFlash('error', 'Selecione um funcionário ativo.');

                return $this->renderDemissaoForm($empresa, $funcionarios, [
                    'funcionario_id' => $funcionarioId > 0 ? $funcionarioId : '',
                    'data_prevista' => $request->request->get('data_prevista'),
                    'motivo' => $request->request->get('motivo'),
                    'observacoes' => $request->request->get('observacoes'),
                ]);
            }

            try {
                $this->requireCsrf($request, 'rh_demissao_form');
                $process = $this->offboarding->create(
                    $empresa,
                    $funcionario,
                    $this->parseDate($request->request->get('data_prevista')),
                    $request->request->get('motivo') ?: null,
                    $request->request->get('observacoes') ?: null,
                );
                $this->addFlash('success', 'Processo de offboarding iniciado.');

                return $this->redirectToRoute('app_rh_demissoes_show', ['id' => $process->getId()]);
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());

                return $this->renderDemissaoForm($empresa, $funcionarios, [
                    'funcionario_id' => $funcionarioId,
                    'data_prevista' => $request->request->get('data_prevista'),
                    'motivo' => $request->request->get('motivo'),
                    'observacoes' => $request->request->get('observacoes'),
                ]);
            }
        }

        $allActive = $this->offboarding->listActiveFuncionarios($empresa);

        $preselect = (int) $request->query->get('funcionario_id', 0);

        return $this->renderDemissaoForm($empresa, $funcionarios, [
            'all_active_blocked' => \count($allActive) > 0 && \count($funcionarios) === 0,
            'funcionario_id' => $preselect > 0 ? $preselect : '',
        ]);
    }

    #[Route('/demissoes/{id}', name: 'app_rh_demissoes_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function demissoesShow(int $id, Request $request): Response
    {
        $process = $this->loadOffboarding($id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'rh_process_action');
                $action = $request->request->get('action');
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
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_demissoes_show', ['id' => $id]);
        }

        return $this->render(self::T . 'demissao_show.html.twig', [
            'process' => $process,
            'documentos' => $this->documents->listOffboarding($process),
        ]);
    }

    private function renderAdmissaoForm(\App\Entity\Empresa $empresa, array $formData = []): Response
    {
        return $this->render(self::T . 'admissao_form.html.twig', array_merge([
            'empresa' => $empresa,
            'checklist_preview' => RhOnboardingProcess::defaultChecklist(),
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
}
