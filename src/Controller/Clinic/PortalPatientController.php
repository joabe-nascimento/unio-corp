<?php

namespace App\Controller\Clinic;

use App\Entity\User;
use App\Repository\PosOperatorioPacienteRepository;
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
    public function __construct(
        private PosOperatorioPacienteRepository $pacienteRepo,
    ) {
    }

    #[Route('', name: 'app_clinica_portal')]
    public function entry(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_portal_patient_login');
        }

        if ($this->hasPortalPaciente()) {
            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        return $this->redirectToRoute('app_portal_patient_login');
    }

    #[Route('/login', name: 'app_portal_patient_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $this->hasPortalPaciente()) {
            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        return $this->render('clinic/portal_login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'staff_logged_in' => $user instanceof User,
        ]);
    }

    #[Route('/sair', name: 'app_portal_patient_logout')]
    public function logoutHint(): Response
    {
        return $this->redirectToRoute('app_logout');
    }

    private function hasPortalPaciente(): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->pacienteRepo->findOneBy(['portalUser' => $user]) !== null;
    }
}
