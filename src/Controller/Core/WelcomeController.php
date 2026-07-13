<?php

namespace App\Controller\Core;

use App\Service\NavigationService;
use App\Service\OnboardingProgressService;
use App\Service\WelcomeAnalyticsService;
use App\Service\WelcomeContentService;
use App\Service\WelcomeNewsFeedService;
use App\Service\WelcomePresentationService;
use App\Service\WelcomeService;
use App\Service\Organismo\OrganismoRedirectService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class WelcomeController extends AbstractController
{
    #[Route('/bem-vindo', name: 'app_welcome')]
    public function index(
        WelcomeService $welcome,
        WelcomePresentationService $presentation,
        WelcomeContentService $welcomeContent,
        WelcomeAnalyticsService $analytics,
        OnboardingProgressService $onboardingProgress,
        WorkspaceService $workspace,
        NavigationService $navigation,
        OrganismoRedirectService $redirects,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresas = $workspace->getAvailableEmpresas($user);

        $home = $redirects->homeRoute($user);
        if ($home === 'app_pulso' || str_starts_with($home, 'app_pos_operatorio_')) {
            return $this->redirectToRoute($home);
        }

        $dt = $welcome->getDateTimeInfo();
        $empresa = $workspace->getActiveEmpresa($user);
        $empresasCount = \count($empresas);
        $layout = $navigation->getLayout($user);
        $chartPayload = $analytics->getChartPayload($user, $empresa);

        return $this->render('core/welcome/index.html.twig', [
            'greeting' => $welcome->getGreeting(),
            'date_label' => $dt['date_label'],
            'time_label' => $dt['time_label'],
            'weekday' => $dt['weekday'],
            'hubs' => $welcome->getHubsForUser($user),
            'novidades' => $welcome->getNovidadesForUser($user),
            'presentation' => $presentation->build($user, $empresa, $empresasCount),
            'welcome_content' => $welcomeContent->build($user, $empresa, $layout, $empresasCount),
            'layout' => $layout,
            'empresa' => $empresa,
            'empresas_count' => $empresasCount,
            'chart_sections' => $chartPayload['sections'],
            'chart_executive' => $chartPayload['executive'],
            'perfil_label' => $user->getPerfilLabel(),
            'perfil_class' => $user->getPerfilClass(),
            'onboarding' => $onboardingProgress->build($user, $empresa, $empresasCount),
        ]);
    }

    #[Route('/bem-vindo/noticias/{slug}', name: 'app_welcome_news_show', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function newsShow(
        string $slug,
        WelcomeNewsFeedService $newsFeed,
        WorkspaceService $workspace,
        NavigationService $navigation,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $empresa = $workspace->getActiveEmpresa($user);
        $layout = $navigation->getLayout($user);

        $article = $newsFeed->findArticleForUser($user, $slug, $layout, $empresa);
        if ($article === null) {
            return $this->render('core/welcome/news_not_found.html.twig', [
                'slug' => $slug,
                'layout' => $layout,
            ]);
        }

        $newsFeed->markArticleRead($user, $slug, $empresa);
        $article['is_read'] = true;

        return $this->render('core/welcome/news_show.html.twig', [
            'article' => $article,
            'layout' => $layout,
            'news_read_api_url' => $this->generateUrl('app_welcome_news_api_read', ['slug' => $slug]),
        ]);
    }
}
