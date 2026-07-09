<?php

namespace App\Controller\Core;

use App\Service\OnboardingProgressService;
use App\Service\Organismo\OrganismoFeature;
use App\Service\Organismo\OrganismoRedirectService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WorkspaceController extends AbstractController
{
    #[Route('/workspace', name: 'app_workspace_select')]
    public function select(
        WorkspaceService $ws,
        OnboardingProgressService $onboarding,
        OrganismoRedirectService $redirects,
        OrganismoFeature $organismo,
        Request $request,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($organismo->isEnabled()) {
            return $this->bootstrapAndRedirect($user, $ws, $onboarding, $redirects);
        }

        $empresas = $ws->getAvailableEmpresas($user);

        if (empty($empresas)) {
            return $this->redirectToRoute($redirects->afterWorkspaceRoute($user, 0));
        }

        if (count($empresas) === 1 && !$request->query->has('force')) {
            $ws->switchTo($user, $empresas[0]->getId());
            $onboarding->markStepComplete('workspace');

            return $this->redirectToRoute($redirects->afterWorkspaceRoute($user, 1));
        }

        return $this->render('workspace/select.html.twig', [
            'empresas' => $empresas,
            'current'  => $ws->getActiveEmpresa($user),
            'organismo_enabled' => false,
        ]);
    }

    #[Route('/workspace/switch/{id}', name: 'app_workspace_switch', methods: ['GET'])]
    public function switch(
        int $id,
        WorkspaceService $ws,
        OnboardingProgressService $onboarding,
        OrganismoRedirectService $redirects,
        OrganismoFeature $organismo,
        Request $request,
    ): Response {
        if ($organismo->isEnabled()) {
            return $this->redirectToRoute($redirects->homeRoute());
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $ws->switchTo($user, $id);
        $onboarding->markStepComplete('workspace');

        $back = $request->query->get('back');
        if (\is_string($back) && $back !== '') {
            return $this->redirectToRoute($back);
        }

        $empresas = $ws->getAvailableEmpresas($user);

        return $this->redirectToRoute($redirects->afterWorkspaceRoute($user, \count($empresas)));
    }

    private function bootstrapAndRedirect(
        \App\Entity\User $user,
        WorkspaceService $ws,
        OnboardingProgressService $onboarding,
        OrganismoRedirectService $redirects,
    ): Response {
        $empresas = $ws->getAvailableEmpresas($user);
        if ($empresas !== []) {
            $active = $ws->getActiveEmpresa($user) ?? $empresas[0];
            if ($active !== null) {
                $ws->switchTo($user, $active->getId());
            }
            $onboarding->markStepComplete('workspace');
        }

        return $this->redirectToRoute($redirects->afterWorkspaceRoute($user, \count($empresas)));
    }
}
