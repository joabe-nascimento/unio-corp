<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Contract\LegalAiClientInterface;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;

/**
 * Analisa uma sentença colada/anexada pelo usuário usando a chain
 * `sentence-analysis` do JurisFlow — identifica chances de recurso e pontos
 * fracos da fundamentação. Não persiste nada, só devolve a análise.
 */
final class AnalisarSentencaTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private LegalAiClientInterface $jurisFlowAi,
    ) {
    }

    public function getName(): string
    {
        return 'analisar_sentenca';
    }

    public function getDescription(): string
    {
        return 'Analisa uma sentença identificando chances de recurso e pontos fracos da fundamentação';
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
        $texto = trim((string) ($params['texto'] ?? $params['sentence_text'] ?? ''));
        if ($texto === '') {
            return ['summary' => 'Para analisar uma sentença, cole o texto completo dela na mensagem (ou anexe o arquivo). Vou identificar pontos fracos da fundamentação e sugerir teses recursais.', 'results' => []];
        }

        // Valida se não é placeholder ou texto muito curto. Anexos de arquivo chegam
        // no formato "[Anexo: nome.pdf]\n<texto>" — o regex só rejeita placeholders
        // de exemplo tipo "[cole o texto aqui]", nunca um anexo real.
        if (mb_strlen($texto) < 150 || preg_match('/\[\s*(cole|insira|preencha|descreva)/ui', $texto) === 1) {
            return ['summary' => 'O texto está muito curto ou parece incompleto. Cole a sentença completa que você deseja analisar.', 'results' => []];
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        $escritorioId = $empresa?->getId() !== null ? (string) $empresa->getId() : '';

        $analise = $this->jurisFlowAi->analisarSentenca($texto, $escritorioId);
        if ($analise === null || trim($analise) === '') {
            return ['summary' => 'O serviço de IA está temporariamente indisponível. Aguarde alguns instantes e tente novamente.', 'results' => []];
        }

        return ['summary' => $analise, 'results' => []];
    }
}
