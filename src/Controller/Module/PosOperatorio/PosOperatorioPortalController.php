<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\PosOperatorioAuditService;
use App\Service\PosOperatorio\PosOperatorioPacienteService;
use App\Service\PosOperatorio\PosOperatorioPortalInviteService;
use App\Service\PosOperatorio\PosOperatorioPortalInteractionService;
use App\Service\PosOperatorio\PosOperatorioPortalService;
use App\Service\PosOperatorio\PosOperatorioQuestionarioService;
use App\Service\WorkspaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioPortalController extends AbstractController
{
    private const T = 'modules/pos-operatorio/';

    public function __construct(
        private WorkspaceService $workspace,
        private PosOperatorioPacienteRepository $pacienteRepo,
        private PosOperatorioPacienteService $pacienteService,
        private PosOperatorioQuestionarioService $questionarioService,
        private PosOperatorioPortalService $portalService,
        private PosOperatorioPortalInteractionService $portalInteraction,
        private PosOperatorioPortalInviteService $portalInvite,
        private PosOperatorioAuditService $audit,
        private EntityManagerInterface $em,
    ) {}

    #[Route('/portal', name: 'app_pos_operatorio_portal')]
    public function portal(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $token = (string) $request->getSession()->get($this->portalInvite->sessionKey(), '');
        if ($token !== '') {
            $invitePaciente = $this->portalInvite->findValidPaciente($token);
            if ($invitePaciente !== null && $this->portalInvite->acceptInvite($invitePaciente, $user)) {
                $request->getSession()->remove($this->portalInvite->sessionKey());
                $this->addFlash('success', sprintf('Portal vinculado a %s.', $invitePaciente->getNome()));
            }
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $paciente = $this->pacienteRepo->findOneBy(['portalUser' => $user]);

        if ($paciente !== null && ($empresa === null || $paciente->getEmpresa()->getId() !== $empresa->getId())) {
            $empresa = $paciente->getEmpresa();
        }

        if ($paciente instanceof PosOperatorioPaciente) {
            $this->audit->logAccess($paciente, $user, 'portal_paciente', $request);
        }

        $portalView = $this->portalService->buildView($paciente);
        $questionarioResumo = null;
        if ($portalView['questionario_hoje']) {
            $questionarioResumo = $this->portalService->mapQuestionarioResumo($portalView['questionario_hoje']);
        }

        return $this->render(self::T . 'portal.html.twig', array_merge([
            'paciente' => $paciente,
            'empresa' => $empresa,
            'dia_pos' => $paciente?->getDiaPosOperatorio(),
            'has_consent' => $paciente ? $this->audit->hasConsent($paciente) : false,
            'consent_at' => $paciente?->getConsentimentoLgpdEm(),
            'questionario_resumo' => $questionarioResumo,
        ], $portalView));
    }

    #[Route('/portal/vincular', name: 'app_pos_operatorio_portal_vincular', methods: ['POST'])]
    public function vincular(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('pos_portal_vincular', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            $this->addFlash('error', 'Área de trabalho indisponível.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        $pacienteId = $request->request->getInt('paciente_id');
        $paciente = $this->pacienteService->findForEmpresa($empresa, $pacienteId);
        if (!$paciente) {
            $this->addFlash('error', 'Paciente não encontrado.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        if ($paciente->getPortalUser() !== null && $paciente->getPortalUser()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Este paciente já está vinculado a outro usuário.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        $existing = $this->pacienteRepo->findOneBy(['portalUser' => $user, 'empresa' => $empresa]);
        if ($existing && $existing->getId() !== $paciente->getId()) {
            $existing->setPortalUser(null);
        }

        $paciente->setPortalUser($user);
        $this->em->flush();

        $this->addFlash('success', sprintf('Login vinculado a %s (%s).', $paciente->getNome(), $paciente->getCodigo()));

        return $this->redirectToRoute('app_pos_operatorio_portal');
    }

    #[Route('/portal/consentimento', name: 'app_pos_operatorio_portal_consentimento', methods: ['POST'])]
    public function consentimento(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $paciente = $this->resolvePortalPaciente($user);
        if ($paciente === null) {
            $this->addFlash('error', 'Nenhum acompanhamento vinculado.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        if (!$request->request->getBoolean('aceite')) {
            $this->addFlash('error', 'É necessário aceitar o consentimento para continuar.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        $this->audit->logConsent($paciente, $user, $request);
        $this->addFlash('success', 'Consentimento registrado. Você já pode enviar o questionário.');

        return $this->redirectToRoute('app_pos_operatorio_portal');
    }

    #[Route('/portal/questionario', name: 'app_pos_operatorio_portal_questionario', methods: ['POST'])]
    public function submitQuestionario(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $paciente = $this->resolvePortalPaciente($user);
        if ($paciente === null) {
            $this->addFlash('error', 'Nenhum acompanhamento vinculado ao seu usuário.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        if (!$this->audit->hasConsent($paciente)) {
            $this->addFlash('error', 'Registre o consentimento LGPD antes de enviar o questionário.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        $portalView = $this->portalService->buildView($paciente);
        if ($portalView['questionario_hoje']) {
            $this->addFlash('info', 'Você já respondeu o questionário de hoje.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        $respostas = $this->collectRespostas($request, $portalView['perguntas']);
        $this->questionarioService->submit($paciente, $respostas, $user);
        $this->addFlash('success', 'Questionário enviado. A equipe clínica foi notificada se necessário.');

        return $this->redirectToRoute('app_pos_operatorio_portal');
    }

    #[Route('/portal/ajuda', name: 'app_pos_operatorio_portal_ajuda', methods: ['POST'])]
    public function requestHelp(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('pos_portal_ajuda', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        /** @var User $user */
        $user = $this->getUser();
        $paciente = $this->resolvePortalPaciente($user);
        if ($paciente === null || !$this->audit->hasConsent($paciente)) {
            $this->addFlash('error', 'Acompanhamento indisponível.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        $mensagem = trim((string) $request->request->get('mensagem', ''));
        $result = $this->portalInteraction->requestHelp(
            $paciente,
            $user,
            $mensagem !== '' ? $mensagem : null,
        );

        if ($result['already_open']) {
            $this->addFlash('info', 'Sua solicitação já está com a equipe. Aguarde o contato.');
        } else {
            $this->addFlash('success', 'Pedido de ajuda enviado. A equipe foi notificada.');
        }

        return $this->redirectToRoute('app_pos_operatorio_portal');
    }

    #[Route('/portal/retorno', name: 'app_pos_operatorio_portal_retorno', methods: ['POST'])]
    public function confirmRetorno(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('pos_portal_retorno', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        /** @var User $user */
        $user = $this->getUser();
        $paciente = $this->resolvePortalPaciente($user);
        if ($paciente === null || !$this->audit->hasConsent($paciente)) {
            $this->addFlash('error', 'Acompanhamento indisponível.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        if (!$this->portalInteraction->confirmRetorno($paciente, $user)) {
            $this->addFlash('info', 'Retorno já confirmado hoje.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        $this->addFlash('success', 'Retorno confirmado. A clínica foi avisada.');

        return $this->redirectToRoute('app_pos_operatorio_portal');
    }

    #[Route('/portal/mensagem', name: 'app_pos_operatorio_portal_mensagem', methods: ['POST'])]
    public function postMessage(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('pos_portal_mensagem', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        /** @var User $user */
        $user = $this->getUser();
        $paciente = $this->resolvePortalPaciente($user);
        if ($paciente === null || !$this->audit->hasConsent($paciente)) {
            $this->addFlash('error', 'Acompanhamento indisponível.');

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        try {
            $this->portalInteraction->postMessage($paciente, $user, (string) $request->request->get('mensagem', ''));
            $this->addFlash('success', 'Mensagem enviada à equipe.');
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'Escreva uma mensagem antes de enviar.');
        }

        return $this->redirectToRoute('app_pos_operatorio_portal');
    }

    /** @param list<array<string, mixed>> $perguntas */
    private function collectRespostas(Request $request, array $perguntas): array
    {
        $respostas = [];
        foreach ($perguntas as $pergunta) {
            $id = (string) ($pergunta['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $tipo = (string) ($pergunta['tipo'] ?? 'texto');
            $respostas[$id] = match ($tipo) {
                'escala' => $request->request->getInt($id, (int) ($pergunta['default'] ?? 0)),
                'numero' => (float) str_replace(',', '.', (string) $request->request->get($id, '0')),
                'select', 'texto' => (string) $request->request->get($id, ''),
                default => $request->request->get($id),
            };
        }

        return $respostas;
    }

    private function resolvePortalPaciente(User $user): ?PosOperatorioPaciente
    {
        $paciente = $this->pacienteRepo->findOneBy(['portalUser' => $user]);

        return $paciente instanceof PosOperatorioPaciente ? $paciente : null;
    }
}
