<?php

namespace App\Controller\Module\Integracoes;

use App\Entity\User;
use App\Service\Integracoes\IntegracoesService;
use App\Service\Integracoes\IntegracaoDeadLetterService;
use App\Service\Integracoes\IntegracaoSloService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/integracoes')]
#[IsGranted('ROLE_USER')]
final class IntegracoesController extends AbstractController
{
    private const T = 'modules/integracoes/';

    public function __construct(
        private IntegracoesService $service,
        private IntegracaoDeadLetterService $deadLetter,
        private IntegracaoSloService $sloService,
        private WorkspaceService $workspace,
    ) {}

    #[Route('', name: 'app_integracoes')]
    public function overview(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . '_base.html.twig', $this->service->getDashboard($user));
    }

    #[Route('/catalogo', name: 'app_integracoes_catalogo')]
    public function catalogo(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'catalogo.html.twig', $this->service->getSection('catalogo', $user));
    }

    #[Route('/conectores', name: 'app_integracoes_conectores')]
    public function conectores(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'conectores.html.twig', $this->service->getSection('conectores', $user));
    }

    #[Route('/webhooks', name: 'app_integracoes_webhooks')]
    public function webhooks(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'webhooks.html.twig', $this->service->getSection('webhooks', $user));
    }

    #[Route('/mapeamentos', name: 'app_integracoes_mapeamentos')]
    public function mapeamentos(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'mapeamentos.html.twig', $this->service->getSection('mapeamentos', $user));
    }

    #[Route('/api', name: 'app_integracoes_api')]
    public function api(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'api.html.twig', $this->service->getSection('api_keys', $user));
    }

    #[Route('/logs', name: 'app_integracoes_logs')]
    public function logs(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $base = $this->service->getSection('logs', $user);

        $filters = array_filter([
            'nivel' => $request->query->get('nivel'),
            'origem' => $request->query->get('origem'),
            'flow_key' => $request->query->get('flow_key'),
            'data_inicio' => $request->query->get('data_inicio'),
        ]);
        $page = max(1, (int) $request->query->get('page', 1));

        if ($filters || $page > 1) {
            $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
            if ($empresa) {
                $paginated = $this->service->getLogsFiltered($empresa, $filters, $page);
                $base['logs'] = $paginated['items'];
                $base['log_pagination'] = $paginated;
                $base['log_filters'] = $filters;
            }
        } else {
            $base['log_filters'] = [];
            $base['log_pagination'] = null;
        }

        return $this->render(self::T . 'logs.html.twig', $base);
    }

    #[Route('/observatorio', name: 'app_integracoes_observatorio')]
    public function observatorio(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'observatorio.html.twig', $this->service->getSection('observatorio', $user));
    }

    #[Route('/playbooks', name: 'app_integracoes_playbooks')]
    public function playbooks(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render(self::T . 'playbooks.html.twig', $this->service->getSection('playbooks', $user));
    }

    #[Route('/dead-letter', name: 'app_integracoes_dead_letter')]
    public function deadLetter(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $base = $this->service->getDashboard($user);
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();

        if ($empresa) {
            $this->deadLetter->seedDemoData($empresa, $this->service->getFirstConector($empresa));
            $base['dead_letters'] = $this->deadLetter->list($empresa);
            $base['dl_stats'] = $this->deadLetter->stats($empresa);
        } else {
            $base['dead_letters'] = [];
            $base['dl_stats'] = ['total' => 0, 'pendente' => 0, 'resolvido' => 0];
        }

        $base['integ_section'] = 'dead_letter';

        return $this->render(self::T . 'dead_letter.html.twig', $base);
    }

    #[Route('/slo', name: 'app_integracoes_slo')]
    public function slo(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $base = $this->service->getDashboard($user);
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();

        if ($empresa) {
            $this->sloService->ensureSlos($empresa);
            $slos = $this->sloService->list($empresa);
            $brechas = array_filter($slos, fn ($s) => $s['em_brecha']);
            $base['slos'] = $slos;
            $base['slo_stats'] = [
                'total' => count($slos),
                'brechas' => count($brechas),
                'ok' => count($slos) - count($brechas),
            ];
        } else {
            $base['slos'] = [];
            $base['slo_stats'] = ['total' => 0, 'brechas' => 0, 'ok' => 0];
        }

        $base['integ_section'] = 'slo';

        return $this->render(self::T . 'slo.html.twig', $base);
    }
}
