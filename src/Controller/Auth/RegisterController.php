<?php

namespace App\Controller\Auth;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\PasswordResetService;
use App\Service\PlatformConfigService;
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
        PlatformConfigService $platformConfig,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if (!$platformConfig->isRegistroPublico()) {
            $this->addFlash('error', 'O registro público está desativado.');
            return $this->redirectToRoute('app_login');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail($passwordReset->normalizeEmail((string) $user->getEmail()));

            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);

            if ($existingUser) {
                $this->addFlash('register_error', 'Este e-mail já está cadastrado.');

                return $this->renderRegisterView($form, true);
            }

            $plain = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plain));
            $user->setPerfil('MEMBRO');
            $user->setRoles([$user->getRolePrincipal()]);
            $user->setAtivo(true);
            $user->setTermosAceitosEm(new \DateTimeImmutable());
            $user->setTermosVersao($platformConfig->getLegalDocumentsVersion());

            $em->persist($user);

            try {
                $em->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('register_error', 'Este e-mail já está cadastrado.');

                return $this->renderRegisterView($form, true);
            }

            $this->addFlash(
                'register_success',
                'Conta criada com sucesso! Faça login para continuar. Um gestor precisará vincular sua conta a uma empresa antes do acesso completo aos módulos.'
            );

            return $this->redirectToRoute('app_login');
        }

        return $this->renderRegisterView($form, true);
    }

    private function renderRegisterView(\Symfony\Component\Form\FormInterface $form, bool $registroPublico = true): Response
    {
        return $this->render('auth/login.html.twig', [
            'registrationForm' => $form,
            'error'            => null,
            'last_username'    => '',
            'active_tab'       => 'register',
            'registro_publico' => $registroPublico,
        ]);
    }
}
