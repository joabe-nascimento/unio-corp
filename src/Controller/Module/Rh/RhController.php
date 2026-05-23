<?php

namespace App\Controller\Module\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_SUPERVISOR')]
class RhController extends AbstractController
{
    private const T = 'modules/rh/';

    #[Route('', name: 'app_rh')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }

    #[Route('/funcionarios', name: 'app_rh_funcionarios')]
    public function funcionarios(): Response
    {
        return $this->render(self::T . 'funcionarios.html.twig');
    }

    #[Route('/admissoes', name: 'app_rh_admissoes')]
    public function admissoes(): Response
    {
        return $this->render(self::T . 'admissoes.html.twig');
    }

    #[Route('/demissoes', name: 'app_rh_demissoes')]
    public function demissoes(): Response
    {
        return $this->render(self::T . 'demissoes.html.twig');
    }

    #[Route('/ferias', name: 'app_rh_ferias')]
    public function ferias(): Response
    {
        return $this->render(self::T . 'ferias.html.twig');
    }

    #[Route('/folha', name: 'app_rh_folha')]
    public function folha(): Response
    {
        return $this->render(self::T . 'folha.html.twig');
    }
}
