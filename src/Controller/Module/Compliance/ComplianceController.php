<?php

namespace App\Controller\Module\Compliance;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compliance')]
#[IsGranted('ROLE_USER')]
class ComplianceController extends AbstractController
{
    #[Route('', name: 'app_compliance')]
    public function index(): Response
    {
        return $this->render('modules/compliance/index.html.twig');
    }
}
