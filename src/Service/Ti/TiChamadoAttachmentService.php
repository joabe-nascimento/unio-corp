<?php

namespace App\Service\Ti;

use App\Entity\TiChamado;
use App\Entity\TiChamadoAnexo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TiChamadoAttachmentService
{
    private const UPLOAD_DIR = 'public/uploads/ti/chamados';
    private const MAX_BYTES = 10_485_760;
    private const MAX_FILES = 5;

    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'text/plain',
        'text/x-log',
        'application/octet-stream',
    ];

    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'txt', 'log'];

    public function __construct(
        private EntityManagerInterface $em,
        private string $projectDir,
    ) {}

    /**
     * @param list<UploadedFile> $files
     * @return list<TiChamadoAnexo>
     */
    public function uploadForChamado(TiChamado $chamado, array $files, User $user): array
    {
        $files = array_values(array_filter(
            $files,
            static fn ($f) => $f instanceof UploadedFile && $f->isValid(),
        ));

        if ($files === []) {
            return [];
        }

        $existing = $chamado->getAnexos()->count();
        if ($existing + \count($files) > self::MAX_FILES) {
            throw new \InvalidArgumentException('Máximo de ' . self::MAX_FILES . ' anexos por chamado.');
        }

        $saved = [];
        foreach ($files as $file) {
            $saved[] = $this->persist($chamado, $file, $user);
        }

        return $saved;
    }

    /** @return list<array<string, mixed>> */
    public function listAsArray(TiChamado $chamado): array
    {
        return array_map(
            static fn (TiChamadoAnexo $a) => $a->toArray(),
            $chamado->getAnexos()->toArray(),
        );
    }

    public function removeAllForChamado(TiChamado $chamado): void
    {
        foreach ($chamado->getAnexos()->toArray() as $anexo) {
            $path = $this->projectDir . '/public' . $anexo->getCaminho();
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function persist(TiChamado $chamado, UploadedFile $file, User $user): TiChamadoAnexo
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Arquivo muito grande: ' . $file->getClientOriginalName() . ' (máx. 10 MB).');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (!\in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \InvalidArgumentException('Tipo não permitido: ' . $file->getClientOriginalName() . '. Use JPG, PNG, PDF, TXT ou LOG.');
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME, true) && $ext !== 'log') {
            throw new \InvalidArgumentException('Tipo não permitido: ' . $file->getClientOriginalName() . '.');
        }

        $dir = $this->projectDir . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar pasta de uploads.');
        }

        $safeName = uniqid('ti_', true) . '.' . $ext;
        $originalName = $file->getClientOriginalName();
        $size = (int) $file->getSize();
        $file->move($dir, $safeName);

        $anexo = (new TiChamadoAnexo())
            ->setChamado($chamado)
            ->setEmpresa($chamado->getEmpresa())
            ->setEnviadoPor($user)
            ->setNomeOriginal($originalName)
            ->setCaminho('/uploads/ti/chamados/' . $safeName)
            ->setMimeType($mime !== '' ? $mime : 'application/octet-stream')
            ->setTamanho($size);

        $chamado->addAnexo($anexo);
        $this->em->persist($anexo);

        return $anexo;
    }
}
