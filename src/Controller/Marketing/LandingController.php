<?php

namespace App\Controller\Marketing;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_workspace_select');
        }

        return $this->render('marketing/home.html.twig');
    }
}
