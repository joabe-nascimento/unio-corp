<?php

namespace App\Controller\Module\Beneficios;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/beneficios')]
#[IsGranted('ROLE_USER')]
class BeneficiosController extends AbstractController
{
    private const T = 'modules/beneficios/';

    #[Route('', name: 'app_beneficios')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }
}
