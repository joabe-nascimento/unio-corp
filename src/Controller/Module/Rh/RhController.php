<?php

namespace App\Controller\Module\Rh;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Repository\FuncionarioRepository;
use App\Repository\RhOffboardingProcessRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Service\RhOffboardingService;
use App\Service\RhOnboardingService;
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
    private const T = 'modules/rh/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhOnboardingService $onboarding,
        private RhOffboardingService $offboarding,
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhOffboardingProcessRepository $offboardingRepo,
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    #[Route('', name: 'app_rh')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'index.html.twig', [
            'onboarding_open' => $this->onboarding->countOpen($empresa),
            'offboarding_open' => $this->offboarding->countOpen($empresa),
        ]);
    }

    #[Route('/funcionarios', name: 'app_rh_funcionarios')]
    public function funcionarios(): Response
    {
        $empresa = $this->requireEmpresa();
        $funcionarios = $this->funcionarioRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']);

        return $this->render(self::T . 'funcionarios.html.twig', [
            'funcionarios' => $funcionarios,
        ]);
    }

    #[Route('/admissoes', name: 'app_rh_admissoes')]
    public function admissoes(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'admissoes.html.twig', [
            'processos' => $this->onboarding->listForEmpresa($empresa),
        ]);
    }

    #[Route('/admissoes/nova', name: 'app_rh_admissoes_nova', methods: ['GET', 'POST'])]
    public function admissoesNova(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            $nome = trim((string) $request->request->get('nome', ''));
            $email = trim((string) $request->request->get('email', ''));
            if ($nome === '' || $email === '') {
                $this->addFlash('error', 'Nome e e-mail são obrigatórios.');

                return $this->render(self::T . 'admissao_form.html.twig', [
                    'empresa' => $empresa,
                    'nome' => $nome,
                    'email' => $email,
                    'cargo' => $request->request->get('cargo'),
                    'data_prevista' => $request->request->get('data_prevista'),
                    'observacoes' => $request->request->get('observacoes'),
                ]);
            }

            $dataPrevista = $this->parseDate($request->request->get('data_prevista'));
            $process = $this->onboarding->create(
                $empresa,
                $nome,
                $email,
                $request->request->get('cargo') ?: null,
                $dataPrevista,
                $request->request->get('observacoes') ?: null,
            );

            $this->addFlash('success', 'Processo de onboarding iniciado.');

            return $this->redirectToRoute('app_rh_admissoes_show', ['id' => $process->getId()]);
        }

        return $this->render(self::T . 'admissao_form.html.twig', ['empresa' => $empresa]);
    }

    #[Route('/admissoes/{id}', name: 'app_rh_admissoes_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function admissoesShow(int $id, Request $request): Response
    {
        $process = $this->loadOnboarding($id);

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            if ($action === 'toggle' && $process->getStatus() !== RhOnboardingProcess::STATUS_CONCLUIDO) {
                $this->onboarding->toggleChecklistItem(
                    $process,
                    (string) $request->request->get('item_id', ''),
                    $request->request->getBoolean('done')
                );
            }
            if ($action === 'complete' && $process->getStatus() !== RhOnboardingProcess::STATUS_CONCLUIDO) {
                $this->onboarding->complete($process);
                $this->addFlash('success', 'Onboarding concluído. Funcionário cadastrado.');
            }

            return $this->redirectToRoute('app_rh_admissoes_show', ['id' => $id]);
        }

        return $this->render(self::T . 'admissao_show.html.twig', ['process' => $process]);
    }

    #[Route('/demissoes', name: 'app_rh_demissoes')]
    public function demissoes(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'demissoes.html.twig', [
            'processos' => $this->offboarding->listForEmpresa($empresa),
        ]);
    }

    #[Route('/demissoes/nova', name: 'app_rh_demissoes_nova', methods: ['GET', 'POST'])]
    public function demissoesNova(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $funcionarios = $this->offboarding->listActiveFuncionarios($empresa);

        if ($request->isMethod('POST')) {
            $funcionarioId = (int) $request->request->get('funcionario_id', 0);
            $funcionario = $funcionarioId > 0
                ? $this->funcionarioRepo->findOneBy(['id' => $funcionarioId, 'empresa' => $empresa])
                : null;

            if (!$funcionario) {
                $this->addFlash('error', 'Selecione um funcionário ativo.');

                return $this->render(self::T . 'demissao_form.html.twig', [
                    'empresa' => $empresa,
                    'funcionarios' => $funcionarios,
                ]);
            }

            $process = $this->offboarding->create(
                $empresa,
                $funcionario,
                $this->parseDate($request->request->get('data_prevista')),
                $request->request->get('motivo') ?: null,
                $request->request->get('observacoes') ?: null,
            );

            $this->addFlash('success', 'Processo de offboarding iniciado.');

            return $this->redirectToRoute('app_rh_demissoes_show', ['id' => $process->getId()]);
        }

        return $this->render(self::T . 'demissao_form.html.twig', [
            'empresa' => $empresa,
            'funcionarios' => $funcionarios,
        ]);
    }

    #[Route('/demissoes/{id}', name: 'app_rh_demissoes_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function demissoesShow(int $id, Request $request): Response
    {
        $process = $this->loadOffboarding($id);

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            if ($action === 'toggle' && $process->getStatus() !== RhOffboardingProcess::STATUS_CONCLUIDO) {
                $this->offboarding->toggleChecklistItem(
                    $process,
                    (string) $request->request->get('item_id', ''),
                    $request->request->getBoolean('done')
                );
            }
            if ($action === 'complete' && $process->getStatus() !== RhOffboardingProcess::STATUS_CONCLUIDO) {
                $this->offboarding->complete($process);
                $this->addFlash('success', 'Offboarding concluído. Funcionário desativado.');
            }

            return $this->redirectToRoute('app_rh_demissoes_show', ['id' => $id]);
        }

        return $this->render(self::T . 'demissao_show.html.twig', ['process' => $process]);
    }

    #[Route('/ferias', name: 'app_rh_ferias')]
    public function ferias(): Response
    {
        return $this->render(self::T . 'ferias.html.twig');
    }

    #[Route('/folha', name: 'app_rh_folha')]
    public function folha(): Response
    {
        return $this->render(self::T . 'folha.html.twig');
    }

    private function requireEmpresa(): Empresa
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user);

        if (!$empresa) {
            throw $this->createAccessDeniedException('Selecione uma área de trabalho para acessar o RH.');
        }

        return $empresa;
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

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        return DateNormalizer::fromFormDate($value);
    }
}
