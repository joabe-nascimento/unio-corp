<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Juridico\JuridicoProcessoTimelineService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BuscarTimelineProcessoTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoProcessoTimelineService $timeline,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string { return 'buscar_timeline_processo'; }
    public function getDescription(): string { return 'Mostra a linha do tempo unificada de um processo (publicações, prazos, documentos)'; }
    public function getRequiredScopes(): array { return []; }
    public function supports(User $user): bool { return $this->organismoCopy->isJuridicoProfile(); }

    public function execute(User $user, array $params): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return ['summary' => 'Nenhum escritório ativo.', 'results' => []];
        }
        $numero = trim((string) ($params['numero_processo'] ?? $params['query'] ?? ''));
        if ($numero === '') {
            return ['summary' => 'Me diga o número do processo para montar a linha do tempo.', 'results' => []];
        }
        $encontrados = $this->processoRepo->findForEmpresa($empresa, null, $numero);
        $processo = $encontrados[0] ?? null;
        if ($processo === null) {
            return ['summary' => 'Não encontrei esse processo na carteira.', 'results' => []];
        }
        $eventos = $this->timeline->montar($processo, 12);
        $linhas = array_map(static fn (array $e) => sprintf('%s — %s', $e['ocorreu_em']->format('d/m'), $e['titulo']), $eventos);

        return [
            'summary' => $linhas === []
                ? 'Ainda não há eventos registrados neste processo.'
                : "Linha do tempo de {$processo->getNumero()}:\n".implode("\n", $linhas),
            'results' => [['label' => 'Abrir processo', 'url' => $this->router->generate('app_juridico_processo_show', ['id' => $processo->getId()])]],
        ];
    }
}
