<?php

namespace App\Controller\Module\Sst;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sst')]
#[IsGranted('ROLE_USER')]
class SstController extends AbstractController
{
    #[Route('', name: 'app_sst')]
    public function index(): Response
    {
        return $this->render('modules/sst/index.html.twig');
    }
}
