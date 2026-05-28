<?php

namespace App\Controller\Module\Clima;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clima')]
#[IsGranted('ROLE_USER')]
class ClimaController extends AbstractController
{
    #[Route('', name: 'app_clima')]
    public function index(): Response
    {
        return $this->render('modules/clima/index.html.twig');
    }
}
