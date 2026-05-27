<?php

namespace App\Controller\Module\Rh;

use App\Service\Rh\RhModuleStatsService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/analytics')]
#[IsGranted('ROLE_USER')]
class RhAnalyticsController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/analytics/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhModuleStatsService $stats,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_analytics')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();
        $modules = $this->stats->hubModules($empresa);

        $phase1 = array_filter($modules, static fn (array $m) => ($m['phase'] ?? 1) === 1);
        $phase2 = array_filter($modules, static fn (array $m) => ($m['phase'] ?? 1) === 2);
        $phase3 = array_filter($modules, static fn (array $m) => ($m['phase'] ?? 1) === 3);

        return $this->render(self::T . 'index.html.twig', [
            'modules' => $modules,
            'phase1' => $phase1,
            'phase2' => $phase2,
            'phase3' => $phase3,
        ]);
    }
}
