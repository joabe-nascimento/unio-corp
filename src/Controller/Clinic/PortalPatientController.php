<?php

namespace App\Controller\Clinic;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Entrada pública e login do portal do paciente (sem shell da equipe clínica).
 */
#[Route('/clinica/portal')]
final class PortalPatientController extends AbstractController
{
    #[Route('', name: 'app_clinica_portal')]
    public function entry(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_portal_patient_login');
        }

        return $this->redirectToRoute('app_pos_operatorio_portal');
    }

    #[Route('/login', name: 'app_portal_patient_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        return $this->render('clinic/portal_login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    #[Route('/sair', name: 'app_portal_patient_logout')]
    public function logoutHint(): Response
    {
        return $this->redirectToRoute('app_logout');
    }
}
