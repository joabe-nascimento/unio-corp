<?php

namespace App\Controller\Module\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Atalho legado — redireciona para o Núcleo de Recrutamento.
 */
#[Route('/rh/recrutamento')]
#[IsGranted('ROLE_USER')]
class RhRecrutamentoController extends AbstractController
{
    #[Route('', name: 'app_rh_recrutamento', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->forward('App\Controller\Module\Recrutamento\RecrutamentoController::vagas', [
                'request' => $request,
            ]);
        }

        return $this->redirectToRoute('app_recrutamento_vagas', $request->query->all(), Response::HTTP_FOUND);
    }

    #[Route('/pipeline', name: 'app_rh_recrutamento_pipeline', methods: ['GET'])]
    public function pipeline(Request $request): Response
    {
        return $this->redirectToRoute('app_recrutamento_pipeline', $request->query->all(), Response::HTTP_FOUND);
    }

    #[Route('/{id}/candidato', name: 'app_rh_recrutamento_candidato', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidato(int $id, Request $request): Response
    {
        return $this->forward('App\Controller\Module\Recrutamento\RecrutamentoController::candidato', [
            'id' => $id,
            'request' => $request,
        ]);
    }

    #[Route('/candidatos/{id}/etapa', name: 'app_rh_recrutamento_candidato_etapa', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidatoEtapa(int $id, Request $request): Response
    {
        return $this->forward('App\Controller\Module\Recrutamento\RecrutamentoController::candidatoEtapa', [
            'id' => $id,
            'request' => $request,
        ]);
    }

    #[Route('/vagas/{id}/status', name: 'app_rh_recrutamento_vaga_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vagaStatus(int $id, Request $request): Response
    {
        return $this->forward('App\Controller\Module\Recrutamento\RecrutamentoController::vagaStatus', [
            'id' => $id,
            'request' => $request,
        ]);
    }

    #[Route('/candidatos/{id}/reprovar', name: 'app_rh_recrutamento_candidato_reprovar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function candidatoReprovar(int $id, Request $request): Response
    {
        return $this->forward('App\Controller\Module\Recrutamento\RecrutamentoController::candidatoReprovar', [
            'id' => $id,
            'request' => $request,
        ]);
    }
}
