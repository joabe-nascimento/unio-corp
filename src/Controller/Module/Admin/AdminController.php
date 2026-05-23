<?php

namespace App\Controller\Module\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    private const T = 'modules/admin/';

    #[Route('', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }

    #[Route('/usuarios', name: 'app_admin_usuarios')]
    public function usuarios(): Response
    {
        return $this->render(self::T . 'usuarios.html.twig');
    }

    #[Route('/empresas', name: 'app_admin_empresas')]
    public function empresas(): Response
    {
        return $this->render(self::T . 'empresas.html.twig');
    }

    #[Route('/configuracoes', name: 'app_admin_configuracoes')]
    public function configuracoes(): Response
    {
        return $this->render(self::T . 'configuracoes.html.twig');
    }
}
