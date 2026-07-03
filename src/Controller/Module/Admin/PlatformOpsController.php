<?php

namespace App\Controller\Module\Admin;

use App\Service\PlatformOpsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operacoes')]
#[IsGranted('ROLE_PLATFORM_OWNER')]
class PlatformOpsController extends AbstractController
{
    private const T = 'modules/admin/';

    /** @var list<string> */
    public const INCIDENT_TABS = [
        'errors',
        'warnings',
        'routes',
        'access',
        'integrations',
        'deprecations',
    ];

    /** @var list<string> */
    public const LOG_TABS = [
        'log_prod',
        'log_errors',
        'log_dev',
    ];

    /** @var list<string> */
    public const ALL_TABS = [
        'overview',
        'errors',
        'warnings',
        'routes',
        'access',
        'integrations',
        'deprecations',
        'deploy',
        'log_prod',
        'log_errors',
        'log_dev',
    ];

    #[Route('', name: 'app_admin_operacoes')]
    public function index(Request $request, PlatformOpsService $ops): Response
    {
        $tab = (string) $request->query->get('tab', 'overview');
        if (!in_array($tab, self::ALL_TABS, true)) {
            $tab = 'overview';
        }

        $perPage = $request->query->getInt('per_page', 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $page = max(1, $request->query->getInt('page', 1));
        $levelFilter = strtoupper(trim((string) $request->query->get('level', '')));
        $allowedLevels = ['', 'DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL'];
        if (!in_array($levelFilter, $allowedLevels, true)) {
            $levelFilter = '';
        }

        $view = $ops->buildView($tab, $page, $perPage, $levelFilter);

        $queryBase = ['tab' => $tab];
        if ($levelFilter !== '') {
            $queryBase['level'] = $levelFilter;
        }

        return $this->render(self::T . 'operacoes.html.twig', [
            'view' => $view,
            'snapshot' => $view['snapshot'],
            'log_analysis' => $view['log_analysis'],
            'list_items' => $view['list_items'],
            'pagination' => $view['pagination'],
            'log_meta' => $view['log_meta'],
            'level_filter' => $levelFilter,
            'active_tab' => $tab,
            'incident_tabs' => self::INCIDENT_TABS,
            'log_tabs' => self::LOG_TABS,
            'query_base' => $queryBase,
        ]);
    }
}
