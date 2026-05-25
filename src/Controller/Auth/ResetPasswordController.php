<?php

namespace App\Controller\Auth;

use App\Form\ResetPasswordFormType;
use App\Service\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function reset(
        string $token,
        Request $request,
        PasswordResetService $passwordReset,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = $passwordReset->resolveUserFromToken($token);
        if (!$user) {
            $this->addFlash('auth_error', 'Link inválido ou expirado. Solicite uma nova redefinição de senha.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();

            if ($passwordReset->consumeTokenAndResetPassword($token, $user, $plain, $passwordHasher)) {
                $this->addFlash('register_success', 'Senha redefinida com sucesso! Faça login com a nova senha.');

                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('auth_error', 'Não foi possível redefinir a senha. Solicite um novo link.');
        }

        return $this->render('auth/reset_password.html.twig', [
            'form'  => $form,
            'email' => $user->getEmail(),
        ]);
    }
}
