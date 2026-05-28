<?php

namespace App\Controller\Module\Juridico;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico')]
#[IsGranted('ROLE_USER')]
class JuridicoController extends AbstractController
{
    #[Route('', name: 'app_juridico')]
    public function index(): Response
    {
        return $this->render('modules/juridico/index.html.twig');
    }
}
