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
        return $this->render(self::T . 'projetos.html.twig');
    }

    #[Route('/cronograma', name: 'app_engenharia_cronograma')]
    public function cronograma(): Response
    {
        return $this->render(self::T . 'cronograma.html.twig');
    }

    #[Route('/orcamentos', name: 'app_engenharia_orcamentos')]
    public function orcamentos(): Response
    {
        return $this->render(self::T . 'orcamentos.html.twig');
    }

    #[Route('/equipes', name: 'app_engenharia_equipes')]
    public function equipes(): Response
    {
        return $this->render(self::T . 'equipes.html.twig');
    }

    #[Route('/documentacao', name: 'app_engenharia_documentacao')]
    public function documentacao(): Response
    {
        return $this->render(self::T . 'documentacao.html.twig');
    }

    #[Route('/normas', name: 'app_engenharia_normas')]
    public function normas(): Response
    {
        return $this->render(self::T . 'normas.html.twig');
    }
}
