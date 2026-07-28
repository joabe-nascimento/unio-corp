<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Service\Juridico\JurisFlowAiClient;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;

/**
 * Analisa cláusulas de risco de um contrato colado pelo usuário usando a chain
 * `contract-analysis` do JurisFlow — não persiste nada, só devolve a análise.
 */
final class AnalisarContratoTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JurisFlowAiClient $jurisFlowAi,
    ) {
    }

    public function getName(): string
    {
        return 'analisar_contrato';
    }

    public function getDescription(): string
    {
        return 'Analisa cláusulas de risco de um contrato colado pelo usuário';
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
        $texto = trim((string) ($params['texto'] ?? $params['contract_text'] ?? ''));
        if ($texto === '') {
            return ['summary' => 'Cole o texto do contrato que você quer que eu analise.', 'results' => []];
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $escritorioId = $empresa?->getId() !== null ? (string) $empresa->getId() : '';

        $analise = $this->jurisFlowAi->analisarContrato($texto, $escritorioId);
        if ($analise === null || trim($analise) === '') {
            return ['summary' => 'Não consegui analisar o contrato agora. Tente novamente em instantes.', 'results' => []];
        }

        return ['summary' => $analise, 'results' => []];
    }
}
