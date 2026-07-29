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
            return ['summary' => 'Para resumir um documento, cole o texto completo na mensagem. Posso resumir petições, contratos, decisões judiciais e outros documentos jurídicos.', 'results' => []];
        }

        // Valida se não é placeholder ou texto muito curto. Note: anexos de arquivo
        // chegam no formato "[Anexo: nome.pdf]\n<texto>" — o regex abaixo só rejeita
        // placeholders de exemplo tipo "[cole o texto aqui]", nunca um anexo real.
        if (mb_strlen($texto) < 100 || preg_match('/\[\s*(cole|insira|preencha|descreva)/ui', $texto) === 1) {
            return ['summary' => 'O texto está muito curto ou parece incompleto. Cole o documento completo que você deseja resumir.', 'results' => []];
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $escritorioId = $empresa?->getId() !== null ? (string) $empresa->getId() : '';

        $resumo = $this->jurisFlowAi->resumirDocumento($texto, $escritorioId);
        if ($resumo === null || trim($resumo) === '') {
            return ['summary' => 'O serviço de IA está temporariamente indisponível. Aguarde alguns instantes e tente novamente.', 'results' => []];
        }

        return ['summary' => $resumo, 'results' => []];
    }
}
