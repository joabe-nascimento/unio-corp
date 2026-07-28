<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BuscarProcessoTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoProcessoRepository $processoRepo,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'buscar_processo';
    }

    public function getDescription(): string
    {
        return 'Busca processos por número, cliente ou área jurídica';
    }

    public function getRequiredScopes(): array
    {
        return [];
    }

    public function supports(User $user): bool
    {
        return $this->organismoCopy->isJuridicoProfile();
    }

    public function execute(User $user, array $params): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return ['summary' => 'Nenhum escritório ativo.', 'results' => []];
        }

        $query = trim((string) ($params['query'] ?? $params['q'] ?? ''));
        if ($query === '') {
            return ['summary' => 'Informe o número do processo, o nome do cliente ou a área.', 'results' => []];
        }

        $found = $this->processoRepo->findForEmpresa($empresa, null, $query);
        $results = [];
        foreach (\array_slice($found, 0, 8) as $processo) {
            $cliente = $processo->getCliente();
            $results[] = [
                'label' => sprintf(
                    '%s — %s%s',
                    $processo->getNumero(),
                    $cliente?->getNome() ?? 'sem cliente vinculado',
                    $processo->getArea() ? ' · ' . $processo->getArea() : '',
                ),
                'url' => $this->router->generate('app_juridico_processo_show', ['id' => $processo->getId()]),
            ];
        }

        $count = \count($results);
        $summary = $count === 0
            ? sprintf('Nenhum processo encontrado para "%s".', $query)
            : ($count === 1 ? 'Encontrei 1 processo.' : sprintf('Encontrei %d processos.', $count));

        return ['summary' => $summary, 'results' => $results];
    }
}
