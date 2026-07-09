<?php

namespace App\Service\Vitoria\Tool;

use App\Entity\User;
use App\Repository\RhFeriasRepository;
use App\Service\NavigationService;
use App\Service\Vitoria\VitoriaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class FeriasPendentesTool implements VitoriaToolInterface
{
    public function __construct(
        private WorkspaceService $workspace,
        private RhFeriasRepository $feriasRepo,
        private NavigationService $navigation,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'ferias_pendentes';
    }

    public function getDescription(): string
    {
        return 'Lista férias aguardando aprovação na colônia';
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
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return ['summary' => 'Nenhuma colônia ativa.', 'results' => []];
        }

        $pendentes = $this->feriasRepo->findPendingRecent($empresa, 10);
        $results = [];
        foreach ($pendentes as $ferias) {
            $func = $ferias->getFuncionario();
            $results[] = [
                'id' => $ferias->getId(),
                'membro' => $func?->getNome(),
                'status' => $ferias->getStatus(),
                'url' => $this->router->generate('app_rh_ferias'),
            ];
        }

        $count = \count($results);
        $summary = $count === 0
            ? 'Não há férias pendentes de aprovação.'
            : sprintf('%d solicitação(ões) aguardando aprovação.', $count);

        return ['summary' => $summary, 'results' => $results];
    }
}
