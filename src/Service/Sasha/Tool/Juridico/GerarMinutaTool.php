<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Service\Juridico\JurisFlowAiClient;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;

/**
 * Gera uma minuta (petição, contestação, procuração, contrato, etc.) a partir
 * de uma descrição do usuário, usando a chain `document-generation` do
 * JurisFlow — devolve um rascunho para revisão, nada é salvo automaticamente.
 */
final class GerarMinutaTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JurisFlowAiClient $jurisFlowAi,
    ) {
    }

    public function getName(): string
    {
        return 'gerar_minuta';
    }

    public function getDescription(): string
    {
        return 'Gera uma minuta de petição, contestação, procuração ou contrato a partir de uma descrição';
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
        $descricao = trim((string) ($params['descricao'] ?? $params['dados'] ?? $params['texto'] ?? ''));
        if ($descricao === '') {
            return ['summary' => 'Descreva o que a minuta deve conter (partes, pedido, fatos relevantes).', 'results' => []];
        }

        $tipo = trim((string) ($params['tipo'] ?? 'petição')) ?: 'petição';

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $escritorioId = $empresa?->getId() !== null ? (string) $empresa->getId() : '';

        $minuta = $this->jurisFlowAi->gerarMinuta($tipo, $descricao, $escritorioId);
        if ($minuta === null || trim($minuta) === '') {
            return ['summary' => 'Não consegui gerar a minuta agora. Tente novamente em instantes.', 'results' => []];
        }

        return ['summary' => $minuta, 'results' => []];
    }
}
