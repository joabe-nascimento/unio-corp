<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/talentos')]
#[IsGranted('ROLE_GESTOR')]
class TalentosController extends AbstractController
{
    #[Route('', name: 'app_talentos')]
    public function index(): Response
    {
        return $this->render('talentos/index.html.twig');
    }

    #[Route('/banco', name: 'app_talentos_banco')]
    public function banco(): Response
    {
        return $this->render('talentos/banco.html.twig');
    }

    #[Route('/vagas', name: 'app_talentos_vagas')]
    public function vagas(): Response
    {
        return $this->render('talentos/vagas.html.twig');
    }

    #[Route('/trilhas', name: 'app_talentos_trilhas')]
    public function trilhas(): Response
    {
        return $this->render('talentos/trilhas.html.twig');
    }

    #[Route('/mentoria', name: 'app_talentos_mentoria')]
    public function mentoria(): Response
    {
        return $this->render('talentos/mentoria.html.twig');
    }
}
