<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhCandidatoAnexo;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhCandidatoAnexoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class RhCandidatoAttachmentService
{
    private const UPLOAD_DIR = 'public/uploads/rh/candidatos';
    private const MAX_BYTES = 5_242_880;
    private const MAX_FILES = 3;
    private const ALLOWED_MIME = ['application/pdf'];
    private const ALLOWED_EXT = ['pdf'];

    public function __construct(
        private EntityManagerInterface $em,
        private RhCandidatoAnexoRepository $anexoRepo,
        private string $projectDir,
    ) {}

    /** @return list<RhCandidatoAnexo> */
    public function listForCandidato(RhCandidato $candidato): array
    {
        return $this->anexoRepo->findByCandidato($candidato);
    }

    public function uploadCurriculo(RhCandidato $candidato, UploadedFile $file, ?User $actor = null): RhCandidatoAnexo
    {
        if (!$file->isValid()) {
            throw new RhProcessException('Arquivo inválido.');
        }

        $existing = $this->anexoRepo->findByCandidato($candidato);
        if (\count($existing) >= self::MAX_FILES) {
            throw new RhProcessException('Máximo de ' . self::MAX_FILES . ' currículos por candidato.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new RhProcessException('PDF muito grande (máx. 5 MB).');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (!\in_array($ext, self::ALLOWED_EXT, true)) {
            throw new RhProcessException('Envie apenas arquivos PDF.');
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RhProcessException('Tipo de arquivo não permitido. Use PDF.');
        }

        $empresa = $candidato->getVaga()->getEmpresa();
        $dir = $this->projectDir . '/' . self::UPLOAD_DIR . '/' . $empresa->getId();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RhProcessException('Não foi possível salvar o arquivo.');
        }

        $safeName = uniqid('cv_', true) . '.pdf';
        $originalName = $file->getClientOriginalName();
        $size = (int) $file->getSize();
        $file->move($dir, $safeName);

        $anexo = new RhCandidatoAnexo();
        $anexo->setCandidato($candidato);
        $anexo->setEmpresa($empresa);
        $anexo->setNomeOriginal($originalName);
        $anexo->setCaminho('/uploads/rh/candidatos/' . $empresa->getId() . '/' . $safeName);
        $anexo->setMimeType($mime);
        $anexo->setTamanho($size);

        $this->em->persist($anexo);
        $this->em->flush();

        return $anexo;
    }

    public function resolveAbsolutePath(RhCandidatoAnexo $anexo): string
    {
        return $this->projectDir . '/public' . $anexo->getCaminho();
    }
}
