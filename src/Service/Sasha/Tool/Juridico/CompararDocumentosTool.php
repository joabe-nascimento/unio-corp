<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Service\Juridico\JurisFlowAiClient;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;

/**
 * Compara dois documentos anexados/colados pelo usuário usando a chain
 * `document-comparison` do JurisFlow — destaca o que foi alterado, adicionado
 * e removido entre as duas versões. Não persiste nada.
 */
final class CompararDocumentosTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JurisFlowAiClient $jurisFlowAi,
    ) {
    }

    public function getName(): string
    {
        return 'comparar_documentos';
    }

    public function getDescription(): string
    {
        return 'Compara dois documentos e destaca as diferenças entre eles';
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
        $textoA = trim((string) ($params['documento_a'] ?? ''));
        $textoB = trim((string) ($params['documento_b'] ?? ''));
        $nomeA = trim((string) ($params['nome_a'] ?? 'Documento A'));
        $nomeB = trim((string) ($params['nome_b'] ?? 'Documento B'));

        if ($textoA === '' || $textoB === '') {
            return ['summary' => 'Para comparar documentos, anexe exatamente 2 arquivos (PDF, DOCX ou TXT) na mensagem, usando o botão de anexo do chat, e peça para comparar.', 'results' => []];
        }

        if (mb_strlen($textoA) < 50 || mb_strlen($textoB) < 50) {
            return ['summary' => 'Um dos documentos anexados parece incompleto ou muito curto para comparação. Confira os arquivos e tente novamente.', 'results' => []];
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $escritorioId = $empresa?->getId() !== null ? (string) $empresa->getId() : '';

        $comparacao = $this->jurisFlowAi->compararDocumentos($textoA, $textoB, $escritorioId);
        if ($comparacao === null || trim($comparacao) === '') {
            return ['summary' => 'O serviço de IA está temporariamente indisponível. Aguarde alguns instantes e tente novamente.', 'results' => []];
        }

        $summary = sprintf("Comparação entre **%s** e **%s**:\n\n%s", $nomeA, $nomeB, $comparacao);

        return ['summary' => $summary, 'results' => []];
    }
}
