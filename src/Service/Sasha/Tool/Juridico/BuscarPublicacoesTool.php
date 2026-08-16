<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoPublicacaoRepository;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BuscarPublicacoesTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoPublicacaoRepository $pubRepo,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string { return 'buscar_publicacoes'; }
    public function getDescription(): string { return 'Lista publicações DJEN recentes do escritório ou de um processo'; }
    public function getRequiredScopes(): array { return []; }
    public function supports(User $user): bool { return $this->organismoCopy->isJuridicoProfile(); }

    public function execute(User $user, array $params): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return ['summary' => 'Nenhum escritório ativo.', 'results' => []];
        }
        $q = trim((string) ($params['query'] ?? $params['numero_processo'] ?? ''));
        $pubs = \array_slice($this->pubRepo->findForEmpresa($empresa, null, null, $q ?: null), 0, 8);
        if ($pubs === []) {
            return ['summary' => 'Nenhuma publicação encontrada.', 'results' => []];
        }
        $linhas = [];
        foreach ($pubs as $p) {
            $linhas[] = sprintf('#%d %s · %s', $p->getId(), $p->tituloCurto(), $p->getNumeroProcesso() ?? 'sem CNJ');
        }

        return [
            'summary' => implode("\n", $linhas),
            'results' => [['label' => 'Abrir publicações', 'url' => $this->router->generate('app_juridico_publicacoes')]],
        ];
    }
}
