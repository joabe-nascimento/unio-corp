<?php

namespace App\Service\Sasha;

use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Extrai texto simples de arquivos anexados no chat da Sasha (PDF/DOCX/TXT),
 * para que o conteúdo possa ser tratado como se o usuário tivesse colado o
 * texto direto na mensagem — sem precisar guardar o arquivo em lugar nenhum.
 *
 * Uso ad-hoc (chat): nada aqui é persistido. Para documentos que devem ficar
 * salvos na biblioteca do escritório, ver {@see \App\Service\Juridico\JuridicoDocumentoService}.
 */
final class DocumentTextExtractorService
{
    private const MAX_BYTES = 15_728_640; // 15 MB
    private const MAX_CHARS = 40_000;
    private const ALLOWED_EXT = ['pdf', 'docx', 'doc', 'txt'];

    /**
     * @return array{text: string, truncated: bool}
     */
    public function extract(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Arquivo inválido ou corrompido.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \RuntimeException('Arquivo muito grande. Máximo 15 MB.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');

        return $this->extractFromPath($file->getPathname(), $ext);
    }

    /**
     * Extrai texto de um arquivo já salvo em disco (não um upload em andamento) —
     * usado para reindexar documentos já existentes na biblioteca do escritório
     * (ver {@see \App\Service\Juridico\JuridicoDocumentoRagSyncService}).
     *
     * @return array{text: string, truncated: bool}
     */
    public function extractFromPath(string $absolutePath, string $extension): array
    {
        $ext = strtolower(ltrim($extension, '.'));
        if (!\in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \RuntimeException('Formato não suportado. Envie PDF, DOCX ou TXT.');
        }

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new \RuntimeException('Arquivo não encontrado ou sem permissão de leitura.');
        }

        if (filesize($absolutePath) > self::MAX_BYTES) {
            throw new \RuntimeException('Arquivo muito grande. Máximo 15 MB.');
        }

        $texto = match ($ext) {
            'pdf' => $this->extractPdf($absolutePath),
            'docx', 'doc' => $this->extractWord($absolutePath),
            default => $this->extractPlainText($absolutePath),
        };

        $texto = trim($texto);
        if ($texto === '') {
            throw new \RuntimeException('Não consegui extrair texto desse arquivo. Ele pode ser uma imagem escaneada sem texto pesquisável.');
        }

        $truncated = mb_strlen($texto) > self::MAX_CHARS;
        if ($truncated) {
            $texto = mb_substr($texto, 0, self::MAX_CHARS);
        }

        return ['text' => $texto, 'truncated' => $truncated];
    }

    private function extractPdf(string $path): string
    {
        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);

            return $pdf->getText();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Não consegui ler esse PDF. Ele pode estar protegido ou corrompido.', 0, $e);
        }
    }

    private function extractWord(string $path): string
    {
        try {
            $phpWord = WordIOFactory::load($path);
            $texto = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    $texto .= $this->extractElementText($element) . "\n";
                }
            }

            return $texto;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Não consegui ler esse documento Word.', 0, $e);
        }
    }

    private function extractElementText(mixed $element): string
    {
        if (method_exists($element, 'getText')) {
            $texto = $element->getText();

            return \is_string($texto) ? $texto : '';
        }

        if (method_exists($element, 'getElements')) {
            $texto = '';
            foreach ($element->getElements() as $child) {
                $texto .= $this->extractElementText($child) . ' ';
            }

            return $texto;
        }

        return '';
    }

    private function extractPlainText(string $path): string
    {
        $conteudo = @file_get_contents($path);
        if ($conteudo === false) {
            throw new \RuntimeException('Não consegui ler esse arquivo de texto.');
        }

        // Remove BOM UTF-8, se presente.
        return preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;
    }
}
