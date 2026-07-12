<?php

namespace App\Controller\Clinic;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Compatibilidade — redireciona rotas /clinica/* para o núcleo pós-operatório real.
 */
#[Route('/clinica')]
#[IsGranted('ROLE_USER')]
final class ClinicController extends AbstractController
{
    #[Route('/pacientes', name: 'app_clinic_pacientes')]
    public function pacientes(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_pacientes');
    }

    #[Route('/pacientes/{id}', name: 'app_clinic_paciente_show', requirements: ['id' => '\d+'])]
    public function pacienteShow(int $id): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_ficha' => $id]);
    }

    #[Route('/sala-critica', name: 'app_clinic_sala_critica')]
    public function salaCritica(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_sala_critica');
    }

    #[Route('/alertas', name: 'app_clinic_alertas')]
    public function alertas(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_alertas');
    }

    #[Route('/protocolos', name: 'app_clinic_protocolos')]
    public function protocolos(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_protocolos');
    }

    #[Route('/questionarios', name: 'app_clinic_questionarios')]
    public function questionarios(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_questionarios');
    }

    #[Route('/retornos', name: 'app_clinic_retornos')]
    public function retornos(): Response
    {
        return $this->redirectToRoute('app_pos_operatorio_retornos');
    }
}
