<?php

namespace App\Controller\Module\Comercial;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comercial')]
#[IsGranted('ROLE_USER')]
class ComercialController extends AbstractController
{
    private const T = 'modules/comercial/';

    #[Route('', name: 'app_comercial')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }
}
