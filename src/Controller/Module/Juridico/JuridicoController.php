<?php

namespace App\Controller\Module\Juridico;

use App\Config\JuridicoModuleRegistry;
use App\Entity\User;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoHonorarioLancamentoRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Juridico\JuridicoSeedService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico')]
#[IsGranted('ROLE_USER')]
class JuridicoController extends AbstractController
{
    public function __construct(
        private JuridicoSeedService $seedService,
        private WorkspaceService $workspace,
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoClienteRepository $clienteRepo,
        private JuridicoHonorarioLancamentoRepository $honorarioRepo,
    ) {
    }

    #[Route('', name: 'app_juridico')]
    public function index(): Response
    {
        return $this->render('modules/juridico/index.html.twig', [
            'sections' => JuridicoModuleRegistry::grouped(),
            'kpis' => JuridicoModuleRegistry::dashboardKpis(),
            'module_count' => \count(JuridicoModuleRegistry::MODULES),
            'metricas' => $this->getDashboardMetricas(),
        ]);
    }

    #[Route('/modulo/{slug}', name: 'app_juridico_modulo', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function module(string $slug): Response
    {
        $module = JuridicoModuleRegistry::findBySlug($slug);
        if ($module === null) {
            throw new NotFoundHttpException();
        }

        if (JuridicoModuleRegistry::isGraduated($slug)) {
            return $this->redirectToRoute(JuridicoModuleRegistry::graduatedRoute($slug));
        }

        $seedData = $this->getSeedDataForModule($slug);

        return $this->render('modules/juridico/module.html.twig', [
            'module' => $module,
            'status_label' => JuridicoModuleRegistry::statusLabel($module['status']),
            'seed_data' => $seedData,
        ]);
    }

    /**
     * @return array{processos_ativos: int, prazos_criticos: int, clientes_premium: int, receita_mes: string, taxa_exito: string, horas_faturadas: string}
     */
    private function getDashboardMetricas(): array
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $empresa = $user ? $this->workspace->getActiveEmpresa($user) : null;

        if (!$empresa) {
            return $this->seedService->getDashboardMetricas();
        }

        $taxaExito = $this->processoRepo->taxaExito($empresa);
        $receitaMes = $this->honorarioRepo->sumReceitaMes($empresa);
        $horasMes = $this->honorarioRepo->sumHorasMes($empresa);

        return [
            'processos_ativos' => $this->processoRepo->countByEmpresaAndStatus($empresa, 'ativo')
                + $this->processoRepo->countByEmpresaAndStatus($empresa, 'critico'),
            'prazos_criticos' => $this->prazoRepo->countCriticosByEmpresa($empresa),
            'clientes_premium' => $this->clienteRepo->countByEmpresaAndStatus($empresa, 'premium'),
            'receita_mes' => 'R$ ' . number_format($receitaMes, 2, ',', '.'),
            'taxa_exito' => $taxaExito !== null ? $taxaExito . '%' : '—',
            'horas_faturadas' => number_format($horasMes, 0, ',', '.') . 'h',
        ];
    }

    private function getSeedDataForModule(string $slug): array
    {
        return match ($slug) {
            'contratos' => ['contratos' => $this->seedService->getContratosExemplo()],
            default => [],
        };
    }
}
