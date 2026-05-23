<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/usuarios', name: 'app_admin_usuarios')]
    public function usuarios(): Response
    {
        return $this->render('admin/usuarios.html.twig');
    }

    #[Route('/empresas', name: 'app_admin_empresas')]
    public function empresas(): Response
    {
        return $this->render('admin/empresas.html.twig');
    }

    #[Route('/configuracoes', name: 'app_admin_configuracoes')]
    public function configuracoes(): Response
    {
        return $this->render('admin/configuracoes.html.twig');
    }
}
