<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\Service\PosOperatorio\PosOperatorioAlertQueueService;
use App\Service\PosOperatorio\PosOperatorioAlertService;
use App\Service\PosOperatorio\PosOperatorioAuditService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioAlertController extends AbstractController
{
    private const T = 'modules/pos-operatorio/';

    public function __construct(
        private WorkspaceService $workspace,
        private PosOperatorioAlertQueueService $queue,
        private PosOperatorioAlertService $alertService,
        private PosOperatorioAuditService $audit,
    ) {}

    #[Route('/alertas', name: 'app_pos_operatorio_alertas')]
    public function alertas(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'alertas.html.twig', array_merge(
            ['empresa' => $empresa, 'pos_section' => 'alertas'],
            $this->queue->buildQueue(
                $empresa,
                $request->query->get('prioridade'),
                $request->query->get('status', 'ativos'),
            ),
        ));
    }

    #[Route('/sala-critica', name: 'app_pos_operatorio_sala_critica')]
    public function salaCritica(): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render(self::T . 'sala_critica.html.twig', array_merge(
            ['empresa' => $empresa, 'pos_section' => 'sala_critica'],
            $this->queue->buildWarRoom($empresa),
        ));
    }

    #[Route('/alertas/{id}/assumir', name: 'app_pos_operatorio_alerta_assumir', methods: ['POST'])]
    public function assumir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();
        $alerta = $this->alertService->getById($empresa, $id);
        if (!$alerta) {
            throw $this->createNotFoundException();
        }

        $this->audit->logAccess($alerta->getPaciente(), $user, 'fila_alertas', $request);
        $this->alertService->claim($alerta, $user);
        $this->addFlash('success', 'Alerta assumido.');

        return $this->redirectToRoute('app_pos_operatorio_alertas');
    }

    #[Route('/alertas/{id}/resolver', name: 'app_pos_operatorio_alerta_resolver', methods: ['POST'])]
    public function resolver(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        /** @var User $user */
        $user = $this->getUser();
        $alerta = $this->alertService->getById($empresa, $id);
        if (!$alerta) {
            throw $this->createNotFoundException();
        }

        $nota = trim((string) $request->request->get('nota', ''));
        $this->alertService->resolve($alerta, $user, $nota !== '' ? $nota : null);
        $this->addFlash('success', 'Alerta resolvido.');

        return $this->redirectToRoute('app_pos_operatorio_alertas');
    }

    private function requireEmpresa(): \App\Entity\Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw $this->createAccessDeniedException('Selecione um workspace.');
        }

        return $empresa;
    }
}
