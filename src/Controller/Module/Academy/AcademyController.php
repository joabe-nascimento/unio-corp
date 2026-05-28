<?php

namespace App\Controller\Module\Academy;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/academy')]
#[IsGranted('ROLE_USER')]
class AcademyController extends AbstractController
{
    private const T = 'modules/academy/';

    #[Route('', name: 'app_academy')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }
}
