<?php

namespace App\Service\Vitoria\Tool;

use App\Entity\User;
use App\Repository\FuncionarioRepository;
use App\Service\NavigationService;
use App\Service\Vitoria\VitoriaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BuscarMembroTool implements VitoriaToolInterface
{
    public function __construct(
        private WorkspaceService $workspace,
        private FuncionarioRepository $funcionarioRepo,
        private NavigationService $navigation,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'buscar_membro';
    }

    public function getDescription(): string
    {
        return 'Busca membros da colônia por nome, e-mail ou CPF';
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

        $query = trim((string) ($params['query'] ?? $params['q'] ?? ''));
        if ($query === '') {
            return ['summary' => 'Informe um nome ou termo de busca.', 'results' => []];
        }

        $found = $this->funcionarioRepo->findForEmpresa($empresa, null, $query);
        $results = [];
        foreach (\array_slice($found, 0, 8) as $func) {
            $results[] = [
                'id' => $func->getId(),
                'nome' => $func->getNome(),
                'email' => $func->getEmail(),
                'url' => $this->router->generate('app_rh_funcionario_show', ['id' => $func->getId()]),
            ];
        }

        $count = \count($results);
        $summary = $count === 0
            ? 'Nenhum membro encontrado.'
            : ($count === 1 ? 'Encontrei 1 membro.' : sprintf('Encontrei %d membros.', $count));

        return ['summary' => $summary, 'results' => $results];
    }
}
