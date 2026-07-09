<?php

namespace App\Service\Vitoria\Tool;

use App\Entity\User;
use App\Service\NavigationService;
use App\Service\Vitoria\VitoriaToolInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AbrirAdmissaoTool implements VitoriaToolInterface
{
    public function __construct(
        private NavigationService $navigation,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'abrir_admissao';
    }

    public function getDescription(): string
    {
        return 'Abre o fluxo de nova admissão (cena de onboarding)';
    }

    public function getRequiredScopes(): array
    {
        return ['product_rh'];
    }

    public function supports(User $user): bool
    {
        return $this->navigation->showModuloRh($user);
    }

    public function execute(User $user, array $params): array
    {
        $url = $this->router->generate('app_rh_admissoes', ['open_nova' => 1]);

        return [
            'summary' => 'Pronto para iniciar uma cena de admissão.',
            'results' => [
                [
                    'action' => 'open_url',
                    'label' => 'Iniciar admissão',
                    'url' => $url,
                ],
            ],
        ];
    }
}
