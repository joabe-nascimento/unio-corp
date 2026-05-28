<?php

namespace App\Controller\Module\Analytics;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/analytics')]
#[IsGranted('ROLE_USER')]
class AnalyticsController extends AbstractController
{
    #[Route('', name: 'app_analytics')]
    public function index(): Response
    {
        return $this->render('modules/analytics/index.html.twig');
    }
}
