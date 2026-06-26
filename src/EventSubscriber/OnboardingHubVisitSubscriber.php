<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\OnboardingProgressService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Marca visita a hub no checklist de onboarding quando o usuário entra em rotas de módulo/hub.
 */
class OnboardingHubVisitSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    private const HUB_ROUTE_PREFIXES = [
        'app_hub_',
        'app_talentos',
        'app_maturidade',
        'app_recrutamento',
        'app_comercial',
        'app_beneficios',
        'app_academy',
        'app_parceiros',
        'app_financeiro',
        'app_compliance',
        'app_analytics',
        'app_juridico',
        'app_clima',
        'app_sst',
        'app_comunicacao',
        'app_hub_portal',
        'app_esg',
        'app_suprimentos',
        'app_ti',
        'app_expansao',
        'app_qualidade',
        'app_facilities',
        'app_patrimonio',
        'app_conhecimento',
        'app_integracoes',
        'app_customer_success',
        'app_inovacao',
        'app_holdings',
        'app_seguros',
        'app_saude_ocupacional',
        'app_pos_operatorio',
        'app_licitacoes',
        'app_marketing',
        'app_lakehouse',
        'app_franquias',
        'app_seguranca_info',
        'app_pmo',
        'app_treinamento_regulatorio',
        'app_terceiros',
        'app_publicidade',
        'app_engenharia',
        'app_rh',
        'app_pessoas_',
        'app_admin',
    ];

    /** @var list<string> */
    private const SKIP_ROUTES = [
        'app_welcome',
        'app_dashboard',
        'app_home',
        'app_workspace_select',
        'app_workspace_switch',
        'app_profile',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private OnboardingProgressService $onboarding,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 5],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!\is_string($route) || $route === '' || str_starts_with($route, '_')) {
            return;
        }

        if (\in_array($route, self::SKIP_ROUTES, true) || !$this->isHubRoute($route)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $this->onboarding->markHubVisited();
    }

    private function isHubRoute(string $route): bool
    {
        foreach (self::HUB_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
