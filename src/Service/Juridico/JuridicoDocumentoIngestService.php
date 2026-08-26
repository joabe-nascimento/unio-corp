<?php

namespace App\Service\Juridico;

use App\Contract\LegalAiClientInterface;
use App\Entity\JuridicoDocumento;
use App\Entity\JuridicoProcessoEvento;
use App\Service\Sasha\DocumentTextExtractorService;

final class JuridicoDocumentoIngestService
{
    public function __construct(
        private DocumentTextExtractorService $extractor,
        private LegalAiClientInterface $ai,
        private JuridicoDocumentoService $documentos,
        private JuridicoProcessoTimelineService $timeline,
        private JuridicoWebhookDispatcher $webhooks,
    ) {
    }

    /**
     * @return array{texto: string, metadata: array<string, mixed>}
     */
    public function processar(JuridicoDocumento $documento): array
    {
        $path = $this->documentos->resolveAbsolutePath($documento);
        $ext = strtolower(pathinfo($documento->getNome(), PATHINFO_EXTENSION));
        $texto = '';
        if (is_file($path)) {
            try {
                $extracted = $this->extractor->extractFromPath($path, $ext);
                $texto = (string) ($extracted['text'] ?? '');
            } catch (\Throwable) {
                $texto = '';
            }
        }

        $metadata = $this->extrairMetadados($texto);
        $job = $this->ai->submitJob('document.analyze', (string) $documento->getEmpresa()->getId(), [
            'documento_id' => $documento->getId(),
            'texto' => mb_substr($texto, 0, 12000),
        ]);
        if (\is_array($job['result']['metadata'] ?? null)) {
            $metadata = array_merge($metadata, $job['result']['metadata']);
        }

        $documento->setTextoExtraido($texto !== '' ? $texto : null);
        $documento->setOcrEm(new \DateTimeImmutable());
        $documento->setMetadataJson($metadata);
        $this->documentos->persistOnly($documento);

        if ($documento->getProcesso() !== null) {
            $this->timeline->registrar(
                $documento->getProcesso(),
                JuridicoProcessoEvento::TIPO_DOCUMENTO,
                'Documento indexado: '.$documento->getNome(),
                $metadata['tipo_documento'] ?? $documento->getCategoria(),
                'documento',
                $documento->getId(),
                $documento->isVisivelPortal(),
            );
        }
        $this->webhooks->dispatch($documento->getEmpresa(), 'documento.indexado', [
            'id' => $documento->getId(),
            'nome' => $documento->getNome(),
        ]);

        return ['texto' => $texto, 'metadata' => $metadata];
    }

    /** @return array<string, mixed> */
    private function extrairMetadados(string $texto): array
    {
        $meta = ['tipo_documento' => null, 'numero_cnj' => null];
        if (preg_match('/\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}/', $texto, $m)) {
            $meta['numero_cnj'] = $m[0];
        }
        $lower = mb_strtolower($texto);
        $meta['tipo_documento'] = match (true) {
            str_contains($lower, 'sentença') || str_contains($lower, 'sentenca') => 'sentenca',
            str_contains($lower, 'contestação') || str_contains($lower, 'contestacao') => 'contestacao',
            str_contains($lower, 'procuração') || str_contains($lower, 'procuracao') => 'procuracao',
            str_contains($lower, 'contrato') => 'contrato',
            str_contains($lower, 'petição') || str_contains($lower, 'peticao') => 'peticao_inicial',
            default => null,
        };

        return $meta;
    }
}
