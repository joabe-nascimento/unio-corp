<?php

namespace App\Controller\Clinic;

use App\Entity\User;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\PosOperatorio\PosOperatorioPortalInviteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
        private PosOperatorioPortalInviteService $invites,
    ) {
    }

    #[Route('', name: 'app_clinica_portal')]
    public function entry(Request $request): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $this->tryAcceptInvite($request, $user);
            if ($this->hasPortalPaciente()) {
                return $this->redirectToRoute('app_pos_operatorio_portal');
            }

            return $this->redirectToRoute('app_portal_patient_login');
        }

        return $this->redirectToRoute('app_paciente_hub');
    }

    #[Route('/login', name: 'app_portal_patient_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            $this->tryAcceptInvite($request, $user);
            if ($this->hasPortalPaciente()) {
                return $this->redirectToRoute('app_pos_operatorio_portal');
            }
        }

        return $this->render('clinic/portal_login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'staff_logged_in' => $user instanceof User,
            'hub_url' => $this->generateUrl('app_paciente_hub'),
        ]);
    }

    #[Route('/convite/{token}', name: 'app_portal_patient_invite')]
    public function invite(string $token, Request $request): Response
    {
        $paciente = $this->invites->findValidPaciente($token);
        if ($paciente === null) {
            $this->addFlash('error', 'Convite inválido ou expirado. Peça um novo link à clínica.');

            return $this->redirectToRoute('app_portal_patient_login');
        }

        $request->getSession()->set($this->invites->sessionKey(), $token);

        $user = $this->getUser();
        if ($user instanceof User && $this->invites->acceptInvite($paciente, $user)) {
            $this->addFlash('success', sprintf('Bem-vindo(a), %s! Portal vinculado com sucesso.', $paciente->getNome()));

            return $this->redirectToRoute('app_pos_operatorio_portal');
        }

        $this->addFlash('info', sprintf('Faça login para acessar o acompanhamento de %s.', $paciente->getNome()));

        return $this->redirectToRoute('app_portal_patient_login');
    }

    #[Route('/sair', name: 'app_portal_patient_logout')]
    public function logoutHint(): Response
    {
        return $this->redirectToRoute('app_logout');
    }

    private function tryAcceptInvite(Request $request, User $user): void
    {
        if ($this->hasPortalPaciente()) {
            return;
        }

        $token = (string) $request->getSession()->get($this->invites->sessionKey(), '');
        if ($token === '') {
            return;
        }

        $paciente = $this->invites->findValidPaciente($token);
        if ($paciente === null) {
            $request->getSession()->remove($this->invites->sessionKey());

            return;
        }

        if ($this->invites->acceptInvite($paciente, $user)) {
            $request->getSession()->remove($this->invites->sessionKey());
            $this->addFlash('success', sprintf('Portal vinculado a %s (%s).', $paciente->getNome(), $paciente->getCodigo()));
        }
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
