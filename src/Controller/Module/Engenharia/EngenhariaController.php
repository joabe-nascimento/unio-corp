<?php

namespace App\Controller\Module\Engenharia;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/engenharia')]
#[IsGranted('ROLE_USER')]
class EngenhariaController extends AbstractController
{
    private const T = 'modules/engenharia/';

    #[Route('', name: 'app_engenharia')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }

    #[Route('/projetos', name: 'app_engenharia_projetos')]
    public function projetos(): Response
    {
        return $this->redirectToRoute('app_engenharia');
    }

    #[Route('/cronograma', name: 'app_engenharia_cronograma')]
    public function cronograma(): Response
    {
        return $this->redirectToRoute('app_engenharia');
    }

    #[Route('/orcamentos', name: 'app_engenharia_orcamentos')]
    public function orcamentos(): Response
    {
        return $this->redirectToRoute('app_engenharia');
    }

    #[Route('/equipes', name: 'app_engenharia_equipes')]
    public function equipes(): Response
    {
        return $this->redirectToRoute('app_engenharia');
    }

    #[Route('/documentacao', name: 'app_engenharia_documentacao')]
    public function documentacao(): Response
    {
        return $this->redirectToRoute('app_engenharia');
    }

    #[Route('/normas', name: 'app_engenharia_normas')]
    public function normas(): Response
    {
        return $this->redirectToRoute('app_engenharia');
    }
}
