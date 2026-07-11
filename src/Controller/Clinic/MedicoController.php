<?php

namespace App\Controller\Clinic;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Compatibilidade — redireciona rotas /medico/* para o núcleo pós-operatório real.
 */
#[Route('/medico')]
#[IsGranted('ROLE_USER')]
final class MedicoController extends AbstractController
{
    #[Route('', name: 'app_medico')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_trabalho');
    }

    #[Route('/pacientes', name: 'app_medico_pacientes')]
    public function pacientes(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_pacientes');
    }

    #[Route('/pacientes/{id}', name: 'app_medico_paciente_show', requirements: ['id' => '\d+'])]
    public function pacienteShow(int $id): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_ficha' => $id]);
    }

    #[Route('/sala-critica', name: 'app_medico_sala_critica')]
    public function salaCritica(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_sala_critica');
    }

    #[Route('/alertas', name: 'app_medico_alertas')]
    public function alertas(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_alertas');
    }

    #[Route('/protocolos', name: 'app_medico_protocolos')]
    public function protocolos(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_protocolos');
    }

    #[Route('/questionarios', name: 'app_medico_questionarios')]
    public function questionarios(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_questionarios');
    }

    #[Route('/retornos', name: 'app_medico_retornos')]
    public function retornos(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_retornos');
    }

    #[Route('/trabalho', name: 'app_medico_trabalho')]
    public function trabalho(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_trabalho');
    }

    #[Route('/carteirinha', name: 'app_medico_carteirinha')]
    public function carteirinha(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_carteirinha');
    }

    #[Route('/guia-medico', name: 'app_medico_guia_medico')]
    public function guiaMedico(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_guia_medico');
    }
}
