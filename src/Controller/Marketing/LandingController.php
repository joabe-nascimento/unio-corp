<?php

namespace App\Controller\Marketing;

use App\Service\Organismo\OrganismoRedirectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LandingController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(OrganismoRedirectService $redirects): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($redirects->afterLoginRoute());
        }

        return $this->render('marketing/home.html.twig');
    }
}
