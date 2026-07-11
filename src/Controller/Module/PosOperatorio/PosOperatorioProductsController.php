<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\PosOperatorio\ClinicProductCatalog;
use App\Service\PosOperatorio\ClinicProductConfigService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioProductsController extends AbstractController
{
    private const T = 'modules/pos-operatorio/ops/';

    public function __construct(
        private WorkspaceService $workspace,
        private ClinicProductConfigService $products,
    ) {}

    #[Route('/produtos', name: 'app_pos_operatorio_produtos', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_products', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Sessão expirada. Tente novamente.');

                return $this->redirectToRoute('app_pos_operatorio_produtos');
            }

            $payload = [];
            foreach (ClinicProductCatalog::defaultEnabledMap() as $id => $_) {
                $payload[$id] = $request->request->has('product_' . $id);
            }
            $this->products->save($empresa, $payload);
            $this->addFlash('success', 'Produtos clínicos atualizados.');

            return $this->redirectToRoute('app_pos_operatorio_produtos');
        }

        $focus = (string) $request->query->get('produto', '');

        return $this->render(self::T . 'produtos.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'produtos',
            'clinic_products' => $this->products->productsForEmpresa($empresa),
            'focus_product' => ClinicProductCatalog::find($focus) !== null ? $focus : null,
        ]);
    }

    private function requireEmpresa(): Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Selecione uma clínica para continuar.');
        }

        return $empresa;
    }
}
