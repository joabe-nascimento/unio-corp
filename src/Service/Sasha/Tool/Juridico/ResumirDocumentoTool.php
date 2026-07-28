<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Service\Juridico\JurisFlowAiClient;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;

/**
 * Resume um texto colado pelo usuário (petição, contrato, parecer) usando a
 * chain `summarize` do JurisFlow — não persiste nada, só devolve o resumo.
 */
final class ResumirDocumentoTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JurisFlowAiClient $jurisFlowAi,
    ) {
    }

    public function getName(): string
    {
        return 'resumir_documento';
    }

    public function getDescription(): string
    {
        return 'Resume um texto/peça processual colado pelo usuário';
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
        $texto = trim((string) ($params['texto'] ?? $params['text'] ?? ''));
        if ($texto === '') {
            return ['summary' => 'Cole o texto do documento que você quer que eu resuma.', 'results' => []];
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $escritorioId = $empresa?->getId() !== null ? (string) $empresa->getId() : '';

        $resumo = $this->jurisFlowAi->resumirDocumento($texto, $escritorioId);
        if ($resumo === null || trim($resumo) === '') {
            return ['summary' => 'Não consegui resumir o documento agora. Tente novamente em instantes.', 'results' => []];
        }

        return ['summary' => $resumo, 'results' => []];
    }
}
