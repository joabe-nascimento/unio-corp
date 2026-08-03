<?php

namespace App\Controller\Module\Juridico;

use App\Entity\User;
use App\Repository\JuridicoClienteRepository;
use App\Service\Juridico\JuridicoModuleMetricsService;
use App\Service\Juridico\JuridicoPortalInviteService;
use App\Service\Juridico\JuridicoPortalService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/portal')]
#[IsGranted('ROLE_USER')]
class JuridicoPortalController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoClienteRepository $clienteRepo,
        private JuridicoPortalService $portalService,
        private JuridicoPortalInviteService $portalInvite,
        private JuridicoModuleMetricsService $moduleMetrics,
    ) {
    }

    #[Route('', name: 'app_juridico_portal')]
    public function portal(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $token = (string) $request->getSession()->get($this->portalInvite->sessionKey(), '');
        if ($token !== '') {
            $inviteCliente = $this->portalInvite->findValidCliente($token);
            if ($inviteCliente !== null && $this->portalInvite->acceptInvite($inviteCliente, $user)) {
                $request->getSession()->remove($this->portalInvite->sessionKey());
                $this->addFlash('success', sprintf('Portal vinculado a %s.', $inviteCliente->getNome()));
            }
        }

        $cliente = $this->clienteRepo->findOneBy(['portalUser' => $user]);
        $empresa = $cliente?->getEmpresa() ?? $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();

        $portalView = $cliente ? $this->portalService->buildView($cliente) : [
            'processos' => [],
            'documentos' => [],
            'timeline' => [],
        ];

        $metricas = $empresa !== null
            ? $this->moduleMetrics->portal($empresa)
            : ['clientes_portal' => 0, 'convites_pendentes' => 0, 'docs_compartilhados' => 0];

        return $this->render('modules/juridico/portal.html.twig', array_merge([
            'cliente' => $cliente,
            'empresa' => $empresa,
            'is_cliente_portal' => $cliente !== null,
            'metricas' => $metricas,
        ], $portalView));
    }

    #[Route('/convite/{token}', name: 'app_juridico_portal_convite')]
    public function convite(string $token, Request $request): Response
    {
        $cliente = $this->portalInvite->findValidCliente($token);
        if ($cliente === null) {
            $this->addFlash('error', 'Convite inválido ou expirado. Peça um novo link ao escritório.');

            return $this->redirectToRoute('app_login');
        }

        $request->getSession()->set($this->portalInvite->sessionKey(), $token);

        $user = $this->getUser();
        if ($user instanceof User && $this->portalInvite->acceptInvite($cliente, $user)) {
            $this->addFlash('success', sprintf('Bem-vindo(a), %s! Portal vinculado com sucesso.', $cliente->getNome()));

            return $this->redirectToRoute('app_juridico_portal');
        }

        $this->addFlash('info', sprintf('Faça login para acompanhar os processos de %s.', $cliente->getNome()));

        return $this->redirectToRoute('app_login');
    }
}
