<?php

namespace App\Controller\Module\Juridico;

use App\Config\JuridicoModuleRegistry;
use App\Service\Juridico\JuridicoSeedService;
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
    ) {
    }

    #[Route('', name: 'app_juridico')]
    public function index(): Response
    {
        return $this->render('modules/juridico/index.html.twig', [
            'sections' => JuridicoModuleRegistry::grouped(),
            'kpis' => JuridicoModuleRegistry::dashboardKpis(),
            'module_count' => \count(JuridicoModuleRegistry::MODULES),
            'metricas' => $this->seedService->getDashboardMetricas(),
        ]);
    }

    #[Route('/modulo/{slug}', name: 'app_juridico_modulo', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function module(string $slug): Response
    {
        $module = JuridicoModuleRegistry::findBySlug($slug);
        if ($module === null) {
            throw new NotFoundHttpException();
        }

        $seedData = $this->getSeedDataForModule($slug);

        return $this->render('modules/juridico/module.html.twig', [
            'module' => $module,
            'status_label' => JuridicoModuleRegistry::statusLabel($module['status']),
            'seed_data' => $seedData,
        ]);
    }

    private function getSeedDataForModule(string $slug): array
    {
        return match ($slug) {
            'processos' => ['processos' => $this->seedService->getProcessosExemplo()],
            'prazos' => ['prazos' => $this->seedService->getPrazosExemplo()],
            'clientes' => ['clientes' => $this->seedService->getClientesExemplo()],
            'contratos' => ['contratos' => $this->seedService->getContratosExemplo()],
            'documentos' => ['documentos' => $this->seedService->getDocumentosExemplo()],
            'jurisprudencia' => ['jurisprudencia' => $this->seedService->getJurisprudenciaExemplo()],
            'honorarios' => ['advogados' => $this->seedService->getProducaoAdvogadosExemplo()],
            default => [],
        };
    }
}
