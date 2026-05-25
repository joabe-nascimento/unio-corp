<?php

namespace App\Controller\Auth;

use App\Form\ForgotPasswordFormType;
use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ForgotPasswordController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function request(
        Request $request,
        PasswordResetService $passwordReset,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $devResetUrl = $passwordReset->requestReset($form->get('email')->getData());

            $this->addFlash(
                'auth_info',
                'Se o e-mail estiver cadastrado, você receberá instruções para redefinir a senha em alguns minutos.'
            );

            if ($devResetUrl !== null && $this->getParameter('kernel.debug')) {
                $this->addFlash(
                    'auth_dev_reset_link',
                    'Ambiente de desenvolvimento (e-mail não enviado): ' . $devResetUrl
                );
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/forgot_password.html.twig', [
            'form' => $form,
        ]);
    }
}
