<?php

namespace App\Controller\Module\Extended;

use App\Config\PlannedHubRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ExtendedHubController extends AbstractController
{
    #[Route('/portal-colaborador', name: 'app_hub_portal')]
    public function portal(): Response
    {
        return $this->renderPlannedHub('portal');
    }

    #[Route('/recrutamento', name: 'app_hub_recrutamento')]
    public function recrutamento(): Response
    {
        return $this->renderPlannedHub('recrutamento');
    }

    #[Route('/esg', name: 'app_esg')]
    public function esg(): Response
    {
        return $this->renderPlannedHub('esg');
    }

    #[Route('/suprimentos', name: 'app_suprimentos')]
    public function suprimentos(): Response
    {
        return $this->renderPlannedHub('suprimentos');
    }

    #[Route('/expansao', name: 'app_expansao')]
    public function expansao(): Response
    {
        return $this->renderPlannedHub('expansao');
    }

    #[Route('/qualidade', name: 'app_qualidade')]
    public function qualidade(): Response
    {
        return $this->renderPlannedHub('qualidade');
    }

    #[Route('/facilities', name: 'app_facilities')]
    public function facilities(): Response
    {
        return $this->renderPlannedHub('facilities');
    }

    #[Route('/patrimonio', name: 'app_patrimonio')]
    public function patrimonio(): Response
    {
        return $this->renderPlannedHub('patrimonio');
    }

    #[Route('/conhecimento', name: 'app_conhecimento')]
    public function conhecimento(): Response
    {
        return $this->renderPlannedHub('conhecimento');
    }

    #[Route('/customer-success', name: 'app_customer_success')]
    public function customerSuccess(): Response
    {
        return $this->renderPlannedHub('customer_success');
    }

    #[Route('/holdings', name: 'app_holdings')]
    public function holdings(): Response
    {
        return $this->renderPlannedHub('holdings');
    }

    #[Route('/seguros', name: 'app_seguros')]
    public function seguros(): Response
    {
        return $this->renderPlannedHub('seguros');
    }

    #[Route('/saude-ocupacional', name: 'app_saude_ocupacional')]
    public function saudeOcupacional(): Response
    {
        return $this->renderPlannedHub('saude_ocupacional');
    }

    #[Route('/licitacoes', name: 'app_licitacoes')]
    public function licitacoes(): Response
    {
        return $this->renderPlannedHub('licitacoes');
    }

    #[Route('/marketing', name: 'app_marketing')]
    public function marketing(): Response
    {
        return $this->renderPlannedHub('marketing');
    }

    #[Route('/lakehouse', name: 'app_lakehouse')]
    public function lakehouse(): Response
    {
        return $this->renderPlannedHub('lakehouse');
    }

    #[Route('/franquias', name: 'app_franquias')]
    public function franquias(): Response
    {
        return $this->renderPlannedHub('franquias');
    }

    #[Route('/seguranca-informacao', name: 'app_seguranca_info')]
    public function segurancaInfo(): Response
    {
        return $this->renderPlannedHub('seguranca_info');
    }

    #[Route('/pmo', name: 'app_pmo')]
    public function pmo(): Response
    {
        return $this->renderPlannedHub('pmo');
    }

    #[Route('/treinamento-regulatorio', name: 'app_treinamento_regulatorio')]
    public function treinamentoRegulatorio(): Response
    {
        return $this->renderPlannedHub('treinamento_regulatorio');
    }

    #[Route('/terceiros', name: 'app_terceiros')]
    public function terceiros(): Response
    {
        return $this->renderPlannedHub('terceiros');
    }

    private function renderPlannedHub(string $id): Response
    {
        $hub = PlannedHubRegistry::findById($id);
        if ($hub === null) {
            throw new NotFoundHttpException();
        }

        return $this->render('components/planned_hub_index.html.twig', [
            'hub' => $hub,
        ]);
    }
}
