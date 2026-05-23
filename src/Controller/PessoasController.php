<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pessoas')]
#[IsGranted('ROLE_SUPERVISOR')]
class PessoasController extends AbstractController
{
    #[Route('', name: 'app_pessoas')]
    public function index(): Response
    {
        return $this->render('pessoas/index.html.twig');
    }

    #[Route('/equipes', name: 'app_pessoas_equipes')]
    public function equipes(): Response
    {
        return $this->render('pessoas/equipes.html.twig');
    }

    #[Route('/cargos', name: 'app_pessoas_cargos')]
    public function cargos(): Response
    {
        return $this->render('pessoas/cargos.html.twig');
    }

    #[Route('/organograma', name: 'app_pessoas_organograma')]
    public function organograma(): Response
    {
        return $this->render('pessoas/organograma.html.twig');
    }

    #[Route('/avaliacao', name: 'app_pessoas_avaliacao')]
    public function avaliacao(): Response
    {
        return $this->render('pessoas/avaliacao.html.twig');
    }
}
