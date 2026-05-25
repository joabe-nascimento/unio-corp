<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\PasswordResetService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        PasswordResetService $passwordReset,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail($passwordReset->normalizeEmail((string) $user->getEmail()));

            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);

            if ($existingUser) {
                $this->addFlash('register_error', 'Este e-mail já está cadastrado.');

                return $this->renderRegisterView($form);
            }

            $plain = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plain));
            $user->setPerfil('MEMBRO');
            $user->setRoles([$user->getRolePrincipal()]);
            $user->setAtivo(true);

            $em->persist($user);

            try {
                $em->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('register_error', 'Este e-mail já está cadastrado.');

                return $this->renderRegisterView($form);
            }

            $this->addFlash(
                'register_success',
                'Conta criada com sucesso! Faça login para continuar. Um gestor precisará vincular sua conta a uma empresa antes do acesso completo aos módulos.'
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->renderRegisterView($form);
    }

    private function renderRegisterView(\Symfony\Component\Form\FormInterface $form): Response
    {
        return $this->render('auth/login.html.twig', [
            'registrationForm' => $form,
            'error'            => null,
            'last_username'    => '',
            'active_tab'       => 'register',
        ]);
    }
}
