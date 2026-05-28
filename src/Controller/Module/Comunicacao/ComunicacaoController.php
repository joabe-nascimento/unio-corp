<?php

namespace App\Controller\Module\Comunicacao;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comunicacao')]
#[IsGranted('ROLE_USER')]
class ComunicacaoController extends AbstractController
{
    #[Route('', name: 'app_comunicacao')]
    public function index(): Response
    {
        return $this->render('modules/comunicacao/index.html.twig');
    }
}
