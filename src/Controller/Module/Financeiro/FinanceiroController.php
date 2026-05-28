<?php

namespace App\Controller\Module\Financeiro;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/financeiro')]
#[IsGranted('ROLE_USER')]
class FinanceiroController extends AbstractController
{
    #[Route('', name: 'app_financeiro')]
    public function index(): Response
    {
        return $this->render('modules/financeiro/index.html.twig');
    }
}
