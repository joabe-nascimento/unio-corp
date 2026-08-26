<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoDocumentoRepository;
use App\Contract\LegalAiClientInterface;
use App\Service\Juridico\JuridicoDocumentoRagSyncService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sugere peças da biblioteca do escritório parecidas com o que o usuário
 * descreve, usando o RAG (TF-IDF) do JurisFlow — reaproveita o store já
 * indexado por {@see \App\Service\Juridico\JuridicoDocumentoRagSyncService}.
 */
final class SugerirPecasSimilaresTool implements SashaToolInterface
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private LegalAiClientInterface $jurisFlowAi,
        private JuridicoDocumentoRepository $documentoRepo,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string
    {
        return 'sugerir_pecas_similares';
    }

    public function getDescription(): string
    {
        return 'Sugere peças da biblioteca do escritório parecidas com o que o usuário descreve';
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
        $descricao = trim((string) ($params['descricao'] ?? $params['texto'] ?? $params['query'] ?? ''));
        if ($descricao === '') {
            return ['summary' => 'Descreva o tipo de peça que você procura (ex: "petição inicial de indenização por dano moral") para eu buscar algo parecido na biblioteca do escritório.', 'results' => []];
        }

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return ['summary' => 'Nenhum escritório ativo.', 'results' => []];
        }

        $escritorioId = (string) $empresa->getId();
        $chunks = $this->jurisFlowAi->buscarNaRag($escritorioId, $descricao, 10);

        // Agrupa por documento (várias porções do mesmo arquivo podem bater na busca),
        // mantendo o maior score, e só considera chunks vindos de JuridicoDocumento
        // (source no formato "jd:<id>") — descarta conhecimento genérico/seed do RAG.
        $melhoresPorDocumento = [];
        foreach ($chunks as $chunk) {
            $source = (string) ($chunk['source'] ?? '');
            $documentoId = JuridicoDocumentoRagSyncService::documentoIdDoSource($source);
            if ($documentoId === null) {
                continue;
            }

            $score = (float) ($chunk['score'] ?? 0);
            if (!isset($melhoresPorDocumento[$documentoId]) || $score > $melhoresPorDocumento[$documentoId]) {
                $melhoresPorDocumento[$documentoId] = $score;
            }
        }

        if ($melhoresPorDocumento === []) {
            return ['summary' => 'Não encontrei peças parecidas na biblioteca do escritório. Isso pode acontecer se a biblioteca ainda não foi sincronizada — peça para o administrador rodar "php bin/console app:juridico:rag:sync".', 'results' => []];
        }

        arsort($melhoresPorDocumento);

        $results = [];
        foreach (\array_slice($melhoresPorDocumento, 0, 5, true) as $documentoId => $score) {
            $documento = $this->documentoRepo->findOneByEmpresa($empresa, $documentoId);
            if ($documento === null) {
                continue;
            }

            $results[] = [
                'label' => sprintf('%s (%.0f%% de similaridade)', $documento->getNome(), min($score, 100.0)),
                'url' => $this->router->generate('app_juridico_documento_download', ['id' => $documento->getId()]),
            ];
        }

        if ($results === []) {
            return ['summary' => 'Não encontrei peças parecidas na biblioteca do escritório.', 'results' => []];
        }

        return [
            'summary' => sprintf('Encontrei %d peça(s) parecida(s) na biblioteca do escritório:', \count($results)),
            'results' => $results,
        ];
    }
}
