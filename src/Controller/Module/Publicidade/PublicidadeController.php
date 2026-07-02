<?php

namespace App\Controller\Module\Publicidade;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/publicidade')]
#[IsGranted('ROLE_USER')]
class PublicidadeController extends AbstractController
{
    private const T = 'modules/publicidade/';

    #[Route('', name: 'app_publicidade')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }

    #[Route('/campanhas', name: 'app_publicidade_campanhas')]
    public function campanhas(): Response
    {
        return $this->redirectToRoute('app_publicidade');
    }

    #[Route('/clientes', name: 'app_publicidade_clientes')]
    public function clientes(): Response
    {
        return $this->redirectToRoute('app_publicidade');
    }

    #[Route('/criativos', name: 'app_publicidade_criativos')]
    public function criativos(): Response
    {
        return $this->redirectToRoute('app_publicidade');
    }

    #[Route('/midia', name: 'app_publicidade_midia')]
    public function midia(): Response
    {
        return $this->redirectToRoute('app_publicidade');
    }

    #[Route('/briefings', name: 'app_publicidade_briefings')]
    public function briefings(): Response
    {
        return $this->redirectToRoute('app_publicidade');
    }

    #[Route('/metricas', name: 'app_publicidade_metricas')]
    public function metricas(): Response
    {
        return $this->redirectToRoute('app_publicidade');
    }
}
