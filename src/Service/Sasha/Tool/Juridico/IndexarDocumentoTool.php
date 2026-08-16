<?php

namespace App\Service\Sasha\Tool\Juridico;

use App\Entity\User;
use App\Repository\JuridicoDocumentoRepository;
use App\Service\Juridico\JuridicoDocumentoIngestService;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Sasha\SashaToolInterface;
use App\Service\WorkspaceService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class IndexarDocumentoTool implements SashaToolInterface
{
    use ConfirmableToolTrait;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private JuridicoDocumentoRepository $docRepo,
        private JuridicoDocumentoIngestService $ingest,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function getName(): string { return 'indexar_documento'; }
    public function getDescription(): string { return 'Extrai texto/metadados e reindexa um documento do GED no RAG da Sasha'; }
    public function getRequiredScopes(): array { return []; }
    public function supports(User $user): bool { return $this->organismoCopy->isJuridicoProfile(); }

    public function execute(User $user, array $params): array
    {
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            return ['summary' => 'Nenhum escritório ativo.', 'results' => []];
        }
        $id = (int) ($params['documento_id'] ?? 0);
        $doc = $id > 0 ? $this->docRepo->findOneByEmpresa($empresa, $id) : null;
        if ($doc === null) {
            return ['summary' => 'Não encontrei esse documento. Informe o ID do GED.', 'results' => []];
        }
        if (!$this->confirmado($params)) {
            return ['summary' => 'Posso extrair e indexar este arquivo na base da Sasha.', 'results' => [
                $this->pedirConfirmacao('indexar_documento', ['documento_id' => $id], 'Indexar documento', [
                    ['label' => 'Arquivo', 'value' => $doc->getNome()],
                ], 'Sim, indexar'),
            ]];
        }
        $this->ingest->processar($doc);

        return [
            'summary' => 'Documento indexado. Já posso sugerir peças semelhantes a partir dele.',
            'results' => [['label' => 'Abrir GED', 'url' => $this->router->generate('app_juridico_documentos')]],
        ];
    }
}
