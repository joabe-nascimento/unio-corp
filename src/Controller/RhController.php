<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_SUPERVISOR')]
class RhController extends AbstractController
{
    #[Route('', name: 'app_rh')]
    public function index(): Response
    {
        return $this->render('rh/index.html.twig');
    }

    #[Route('/funcionarios', name: 'app_rh_funcionarios')]
    public function funcionarios(): Response
    {
        return $this->render('rh/funcionarios.html.twig');
    }

    #[Route('/admissoes', name: 'app_rh_admissoes')]
    public function admissoes(): Response
    {
        return $this->render('rh/admissoes.html.twig');
    }

    #[Route('/demissoes', name: 'app_rh_demissoes')]
    public function demissoes(): Response
    {
        return $this->render('rh/demissoes.html.twig');
    }

    #[Route('/ferias', name: 'app_rh_ferias')]
    public function ferias(): Response
    {
        return $this->render('rh/ferias.html.twig');
    }

    #[Route('/folha', name: 'app_rh_folha')]
    public function folha(): Response
    {
        return $this->render('rh/folha.html.twig');
    }
}
