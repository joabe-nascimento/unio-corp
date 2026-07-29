<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Juridico\PrevisaoExitoService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ferramenta autônoma: calcula a previsão de êxito de um processo já cadastrado — sem
 * IA generativa, direto do histórico real da carteira do escritório (heurística, ou
 * modelo estatístico treinado quando há histórico suficiente).
 */
final class PreverExitoTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoProcessoRepository $processoRepo,
        private PrevisaoExitoService $previsao,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'prever_exito';
    }

    public function getDescription(): string
    {
        return 'Estima a probabilidade de êxito de um processo com base no histórico real da carteira';
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

        $query = trim((string) ($params['query'] ?? $params['numero'] ?? $params['q'] ?? ''));
        if ($query === '') {
            return ['summary' => 'Informe o número do processo para eu estimar a probabilidade de êxito.', 'results' => []];
        }

        $encontrados = $this->processoRepo->findForEmpresa($empresa, null, $query);
        if ($encontrados === []) {
            return ['summary' => sprintf('Não encontrei nenhum processo para "%s".', $query), 'results' => []];
        }

        $processo = $encontrados[0];
        $score = $this->previsao->preverAuto($processo);

        $fatoresTexto = implode('; ', array_map(
            static fn (array $f) => $f['label'] . ($f['peso'] !== 0 ? sprintf(' (%s%d)', $f['peso'] > 0 ? '+' : '', $f['peso']) : ''),
            \array_slice($score['fatores'], 0, 3),
        ));

        $summary = sprintf(
            'Processo %s: score de %d/100 — %s. Principais fatores: %s.',
            $processo->getNumero(),
            $score['score'],
            $score['label'],
            $fatoresTexto,
        );

        return [
            'summary' => $summary,
            'results' => [[
                'label' => sprintf('%s — abrir processo e ver fatores completos', $processo->getNumero()),
                'url' => $this->router->generate('app_juridico_processo_show', ['id' => $processo->getId()]),
            ]],
        ];
    }
}
