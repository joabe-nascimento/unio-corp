<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Form\RegistrationFormType;
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
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);

            if ($existingUser) {
                $this->addFlash('register_error', 'Este e-mail já está cadastrado.');

                return $this->render('auth/login.html.twig', [
                    'registrationForm' => $form,
                    'error'            => null,
                    'last_username'    => '',
                    'active_tab'       => 'register',
                ]);
            }

            $plain = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plain));
            $user->setRoles(['ROLE_MEMBRO']);
            $user->setPerfil('MEMBRO');

            $em->persist($user);
            $em->flush();

            $this->addFlash('register_success', 'Conta criada com sucesso! Faça login para continuar.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/login.html.twig', [
            'registrationForm' => $form,
            'error'            => null,
            'last_username'    => '',
            'active_tab'       => 'register',
        ]);
    }
}
