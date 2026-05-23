<?php

namespace App\Controller\Module\Maturidade;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maturidade')]
#[IsGranted('ROLE_GESTOR_EQUIPE')]
class MaturidadeController extends AbstractController
{
    private const T = 'modules/maturidade/';

    #[Route('', name: 'app_maturidade')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }

    #[Route('/avaliacao', name: 'app_maturidade_avaliacao')]
    public function avaliacao(): Response
    {
        return $this->render(self::T . 'avaliacao.html.twig');
    }

    #[Route('/radar', name: 'app_maturidade_radar')]
    public function radar(): Response
    {
        return $this->render(self::T . 'radar.html.twig');
    }

    #[Route('/plano', name: 'app_maturidade_plano')]
    public function plano(): Response
    {
        return $this->render(self::T . 'plano.html.twig');
    }

    #[Route('/historico', name: 'app_maturidade_historico')]
    public function historico(): Response
    {
        return $this->render(self::T . 'historico.html.twig');
    }
}
