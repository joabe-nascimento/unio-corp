<?php

namespace App\Controller\Module\Operacoes;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hub/operacoes')]
#[IsGranted('ROLE_USER')]
class OperacoesController extends AbstractController
{
    #[Route('', name: 'app_hub_operacoes')]
    public function index(): Response
    {
        return $this->render('modules/operacoes/index.html.twig');
    }
}
