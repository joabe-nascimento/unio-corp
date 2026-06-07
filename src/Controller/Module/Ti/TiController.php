<?php

namespace App\Controller\Module\Ti;

use App\Entity\User;
use App\Security\TiGrantService;
use App\Service\Ti\TiNotificationService;
use App\Service\Ti\TiService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ti')]
#[IsGranted('ROLE_USER')]
final class TiController extends AbstractController
{
    private const T = 'modules/ti/';

    public function __construct(
        private TiService $service,
        private TiGrantService $tiGrants,
        private TiNotificationService $notifications,
        private WorkspaceService $workspace,
    ) {}

    #[Route('/notificacoes/poll', name: 'app_ti_notificacoes_poll', methods: ['GET'])]
    public function notificacoesPoll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            return new JsonResponse(['ok' => false, 'error' => 'Sem workspace.'], 400);
        }

        $sinceId = max(0, (int) $request->query->get('since', 0));
        $payload = $this->notifications->pollUnread($empresa, $user, $sinceId);

        return new JsonResponse([
            'ok' => true,
            'count' => $payload['count'],
            'latest_id' => $payload['latest_id'],
            'new_count' => $payload['new_count'],
            'notifications' => $payload['notifications'],
        ]);
    }

    #[Route('', name: 'app_ti')]
    public function overview(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . '_base.html.twig', $this->service->getDashboard($user));
    }

    #[Route('/chamados', name: 'app_ti_chamados')]
    public function chamados(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'chamados.html.twig', $this->service->getSection('chamados', $user));
    }

    #[Route('/ativos', name: 'app_ti_ativos')]
    public function ativos(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'ativos.html.twig', $this->service->getSection('ativos', $user));
    }

    #[Route('/licencas', name: 'app_ti_licencas')]
    public function licencas(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'licencas.html.twig', $this->service->getSection('licencas', $user));
    }

    #[Route('/sla', name: 'app_ti_sla')]
    public function sla(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'sla.html.twig', $this->service->getSection('sla', $user));
    }

    #[Route('/manutencoes', name: 'app_ti_manutencoes')]
    public function manutencoes(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'manutencoes.html.twig', $this->service->getSection('manutencoes', $user));
    }

    #[Route('/catalogo', name: 'app_ti_catalogo')]
    public function catalogo(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'catalogo.html.twig', $this->service->getSection('catalogo', $user));
    }

    #[Route('/integracoes', name: 'app_ti_integracoes')]
    public function integracoes(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'integracoes.html.twig', $this->service->getSection('integracoes', $user));
    }

    #[Route('/cortex', name: 'app_ti_cortex')]
    public function cortex(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'cortex.html.twig', $this->service->getSection('cortex', $user));
    }

    #[Route('/analytics', name: 'app_ti_analytics')]
    public function analytics(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'analytics.html.twig', $this->service->getSection('analytics', $user));
    }

    #[Route('/novidades', name: 'app_ti_novidades')]
    public function novidades(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'novidades.html.twig', $this->service->getSection('novidades', $user));
    }

    #[Route('/kb', name: 'app_ti_kb')]
    public function kb(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'kb.html.twig', $this->service->getSection('kb', $user));
    }

    #[Route('/problemas', name: 'app_ti_problemas')]
    public function problemas(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'problemas.html.twig', $this->service->getSection('problemas', $user));
    }

    #[Route('/meus-chamados', name: 'app_ti_meus_chamados')]
    public function meusChamados(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'meus_chamados.html.twig', $this->service->getSection('meus_chamados', $user));
    }

    #[Route('/analytics/export', name: 'app_ti_analytics_export')]
    public function analyticsExport(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->service->requireEmpresaForUser($user);
        $csv = $this->service->exportAnalytics($empresa);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="ti-analytics.csv"',
        ]);
    }

    #[Route('/chamados/novo', name: 'app_ti_chamado_novo', methods: ['GET'])]
    public function novoChamado(): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->redirectToRoute(
            $this->tiGrants->canOperateChamados($user) ? 'app_ti_chamados' : 'app_ti_meus_chamados',
            ['open_novo' => 1],
        );
    }
}
