<?php

namespace App\Service\Juridico;

use App\Contract\LegalAiClientInterface;
use App\Entity\JuridicoDocumento;
use App\Service\Sasha\DocumentTextExtractorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Sincroniza o texto de um {@see JuridicoDocumento} para a base de conhecimento
 * (RAG) do JurisFlow, para alimentar a ferramenta "sugerir peças similares".
 *
 * Reaproveita o RAG genérico já existente do JurisFlow (endpoints
 * `/v1/rag/{escritorio_id}/documents`), sem pipeline de embeddings próprio.
 * Como o store é em memória (some a cada restart do processo Python), este
 * serviço é chamado tanto no momento do upload quanto por um comando de
 * resync em lote ({@see \App\Command\JuridicoRagSyncCommand}).
 *
 * O identificador numérico do {@see JuridicoDocumento} é codificado no campo
 * `source` (formato `jd:123`) para permitir mapear os resultados de busca de
 * volta para o documento original do Symfony.
 */
final class JuridicoDocumentoRagSyncService
{
    private const PREFIXO_SOURCE = 'jd:';

    public function __construct(
        private LegalAiClientInterface $jurisFlowAi,
        private DocumentTextExtractorService $textExtractor,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {
    }

    public static function sourceParaDocumento(int $documentoId): string
    {
        return self::PREFIXO_SOURCE . $documentoId;
    }

    /** Extrai o id do Symfony de um `source` do RAG, ou null se não for um `JuridicoDocumento`. */
    public static function documentoIdDoSource(string $source): ?int
    {
        if (!str_starts_with($source, self::PREFIXO_SOURCE)) {
            return null;
        }

        $id = substr($source, \strlen(self::PREFIXO_SOURCE));

        return ctype_digit($id) ? (int) $id : null;
    }

    /**
     * Extrai o texto do documento e envia para o RAG do JurisFlow. Best-effort:
     * nunca lança exceção — falhas de extração/rede não podem travar o upload.
     */
    public function sync(JuridicoDocumento $documento): bool
    {
        if ($documento->getId() === null) {
            return false;
        }

        $ext = strtolower(pathinfo($documento->getArquivoPath(), \PATHINFO_EXTENSION));
        if (!\in_array($ext, ['pdf', 'docx', 'doc', 'txt'], true)) {
            // Formatos como imagem/planilha não têm extração de texto suportada ainda.
            return false;
        }

        try {
            $absolutePath = $this->projectDir . '/public' . $documento->getArquivoPath();
            $resultado = $this->textExtractor->extractFromPath($absolutePath, $ext);
        } catch (\Throwable $e) {
            $this->logger->info('Não foi possível extrair texto do documento #{id} para o RAG: {msg}', [
                'id' => $documento->getId(),
                'msg' => $e->getMessage(),
            ]);

            return false;
        }

        $texto = $resultado['text'];
        $hash = hash('sha256', $texto);
        if ($documento->getRagHash() === $hash && $documento->isRagSincronizado()) {
            return true;
        }

        $escritorioId = (string) $documento->getEmpresa()->getId();
        $ok = $this->jurisFlowAi->indexarDocumentoRag(
            $escritorioId,
            self::sourceParaDocumento($documento->getId()),
            $documento->getNome(),
            $texto,
            $this->categoriaParaRag($documento->getCategoria()),
        );

        if ($ok) {
            $documento->setRagHash($hash)->setRagSincronizadoEm(new \DateTimeImmutable());
            $this->em->flush();
        }

        return $ok;
    }

    private function categoriaParaRag(string $categoria): string
    {
        return match ($categoria) {
            JuridicoDocumento::CATEGORIA_PETICAO => 'Petições',
            JuridicoDocumento::CATEGORIA_PECA_PROCESSUAL => 'Peças processuais',
            JuridicoDocumento::CATEGORIA_CONTRATO => 'Contratos',
            JuridicoDocumento::CATEGORIA_PARECER => 'Pareceres',
            JuridicoDocumento::CATEGORIA_PROCURACAO => 'Procurações',
            default => 'Documentos do escritório',
        };
    }
}
