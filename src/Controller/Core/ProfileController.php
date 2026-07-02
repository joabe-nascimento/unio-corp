<?php

namespace App\Controller\Core;

use App\Entity\User;
use App\Exception\UserProfileException;
use App\Form\ChangePasswordFormType;
use App\Form\ProfileIdentityFormType;
use App\Service\UserProfileService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/meu-perfil', name: 'app_profile', methods: ['GET'])]
    public function index(WorkspaceService $workspaceService): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('core/profile/index.html.twig', [
            'empresa' => $workspaceService->getActiveEmpresa($user),
            'empresas' => $workspaceService->getAvailableEmpresas($user),
            'member_since' => $user->getCriadoEm()->format('d/m/Y'),
            'identityForm' => $this->createForm(ProfileIdentityFormType::class, $user)->createView(),
            'passwordForm' => $this->createForm(ChangePasswordFormType::class)->createView(),
        ]);
    }

    #[Route('/meu-perfil/identidade', name: 'app_profile_identity', methods: ['POST'])]
    public function updateIdentity(
        Request $request,
        UserProfileService $profileService,
        WorkspaceService $workspaceService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProfileIdentityFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $profileService->updateIdentity(
                    $user,
                    (string) $form->get('nome')->getData(),
                    $form->get('avatarFile')->getData(),
                    (bool) $form->get('removeAvatar')->getData(),
                );
                $this->addFlash('success', 'Perfil atualizado.');
            } catch (UserProfileException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Não foi possível atualizar o perfil. Verifique os campos.');
        }

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/meu-perfil/senha', name: 'app_profile_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserProfileService $profileService,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $profileService->changePassword(
                    $user,
                    (string) $form->get('currentPassword')->getData(),
                    (string) $form->get('plainPassword')->getData(),
                    $passwordHasher,
                );
                $this->addFlash('success', 'Senha alterada com sucesso.');
            } catch (UserProfileException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Não foi possível alterar a senha. Verifique os campos.');
        }

        return $this->redirectToRoute('app_profile');
    }
}
