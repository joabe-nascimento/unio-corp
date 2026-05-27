<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\RhOffboardingProcess;
use App\Entity\RhOnboardingProcess;
use App\Entity\RhProcessDocument;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhProcessDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RhDocumentService
{
    private const UPLOAD_DIR = 'public/uploads/rh';
    private const MAX_BYTES = 10_485_760;
    private const ALLOWED_MIME = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private RhProcessDocumentRepository $repo,
        private string $projectDir,
    ) {}

    public function uploadForOnboarding(
        RhOnboardingProcess $process,
        UploadedFile $file,
        string $categoria,
        ?User $uploadedBy,
    ): RhProcessDocument {
        return $this->persist($process->getEmpresa(), $file, $categoria, $uploadedBy, $process, null);
    }

    public function uploadForOffboarding(
        RhOffboardingProcess $process,
        UploadedFile $file,
        string $categoria,
        ?User $uploadedBy,
    ): RhProcessDocument {
        return $this->persist($process->getEmpresa(), $file, $categoria, $uploadedBy, null, $process);
    }

    /** @return list<RhProcessDocument> */
    public function listOnboarding(RhOnboardingProcess $process): array
    {
        return $this->repo->findByOnboarding($process);
    }

    /** @return list<RhProcessDocument> */
    public function listOffboarding(RhOffboardingProcess $process): array
    {
        return $this->repo->findByOffboarding($process);
    }

    public function delete(RhProcessDocument $doc): void
    {
        $full = $this->projectDir . '/public' . $doc->getCaminho();
        if (is_file($full)) {
            @unlink($full);
        }
        $this->em->remove($doc);
        $this->em->flush();
    }

    private function persist(
        Empresa $empresa,
        UploadedFile $file,
        string $categoria,
        ?User $uploadedBy,
        ?RhOnboardingProcess $onboarding,
        ?RhOffboardingProcess $offboarding,
    ): RhProcessDocument {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RhProcessException('Arquivo muito grande. Máximo 10 MB.');
        }
        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RhProcessException('Tipo de arquivo não permitido. Use PDF, JPG, PNG ou DOC/DOCX.');
        }

        $dir = $this->projectDir . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RhProcessException('Não foi possível criar pasta de uploads.');
        }

        $size = (int) $file->getSize();
        $originalName = $file->getClientOriginalName();
        $ext = $file->guessExtension() ?: 'bin';
        $safeName = uniqid('rh_', true) . '.' . $ext;
        $file->move($dir, $safeName);

        $doc = new RhProcessDocument();
        $doc->setEmpresa($empresa);
        $doc->setOnboarding($onboarding);
        $doc->setOffboarding($offboarding);
        $doc->setUploadedBy($uploadedBy);
        $doc->setNomeOriginal($originalName);
        $doc->setCaminho('/uploads/rh/' . $safeName);
        $doc->setMimeType($mime);
        $doc->setTamanho($size);
        $doc->setCategoria($categoria);

        $this->em->persist($doc);
        $this->em->flush();

        return $doc;
    }
}
