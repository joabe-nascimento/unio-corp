<?php

namespace App\Controller\Module\Parceiros;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parceiros')]
#[IsGranted('ROLE_USER')]
class ParceirosController extends AbstractController
{
    private const T = 'modules/parceiros/';

    #[Route('', name: 'app_parceiros')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }
}
