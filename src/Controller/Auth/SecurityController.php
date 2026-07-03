<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\PlatformConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils, PlatformConfigService $config): Response
    {
        if ($this->getUser()) {
            if (!$this->isGranted('ROLE_USER')) {
                return $this->redirectToRoute('app_sessao_encerrar');
            }

            $user = $this->getUser();
            if ($config->isMaintenanceMode() && $user instanceof User && !$user->hasPlatformAccess()) {
                return $this->redirectToRoute('app_logout');
            }

            return $this->redirectToRoute('app_workspace_select');
        }

        if ($request->query->getBoolean('timeout')) {
            $this->addFlash('auth_info', 'Sua sessão expirou por inatividade. Faça login novamente.');
        }

        if ($request->query->getBoolean('relogin')) {
            $this->addFlash('auth_info', 'Sua sessão foi encerrada. Faça login novamente.');
        }

        $registroPublico = $config->isRegistroPublico();

        $registrationForm = $registroPublico
            ? $this->createForm(RegistrationFormType::class, new User())
            : null;

        return $this->render('auth/login.html.twig', [
            'error'            => $authenticationUtils->getLastAuthenticationError(),
            'last_username'    => $authenticationUtils->getLastUsername(),
            'registrationForm' => $registrationForm,
            'active_tab'       => 'login',
            'registro_publico' => $registroPublico,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by firewall.');
    }
}
