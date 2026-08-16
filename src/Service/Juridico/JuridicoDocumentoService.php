<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoDocumento;
use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoDocumentoRepository;
use App\Repository\JuridicoProcessoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class JuridicoDocumentoService
{
    private const UPLOAD_DIR = 'public/uploads/juridico-documentos';
    private const MAX_BYTES = 20_971_520;
    private const ALLOWED_EXT = ['pdf', 'doc', 'docx', 'odt', 'jpg', 'jpeg', 'png', 'xls', 'xlsx'];

    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoDocumentoRepository $repo,
        private JuridicoProcessoRepository $processoRepo,
        private string $projectDir,
        private JuridicoDocumentoRagSyncService $ragSync,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data, UploadedFile $file, ?User $uploadedBy): JuridicoDocumento
    {
        if (!$file->isValid()) {
            throw new JuridicoProcessException('Arquivo inválido.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new JuridicoProcessException('Arquivo muito grande. Máximo 20 MB.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: '');
        if (!\in_array($ext, self::ALLOWED_EXT, true)) {
            throw new JuridicoProcessException('Formato de arquivo não suportado.');
        }

        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            $nome = pathinfo($file->getClientOriginalName(), \PATHINFO_FILENAME);
        }

        $categoria = trim((string) ($data['categoria'] ?? JuridicoDocumento::CATEGORIA_OUTRO));
        if (!\in_array($categoria, JuridicoDocumento::CATEGORIAS, true)) {
            $categoria = JuridicoDocumento::CATEGORIA_OUTRO;
        }

        $dir = $this->projectDir . '/' . self::UPLOAD_DIR . '/' . $empresa->getId();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new JuridicoProcessException('Não foi possível salvar o arquivo.');
        }

        $safeName = uniqid('doc_', true) . '.' . $ext;
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        $file->move($dir, $safeName);

        $documento = new JuridicoDocumento();
        $documento->setEmpresa($empresa);
        $documento->setNome($nome);
        $documento->setCategoria($categoria);
        $documento->setArquivoPath('/uploads/juridico-documentos/' . $empresa->getId() . '/' . $safeName);
        $documento->setMimeType($mime);
        $documento->setTamanhoBytes($size);
        $documento->setConfidencial((bool) ($data['confidencial'] ?? false));
        $documento->setVisivelPortal((bool) ($data['visivel_portal'] ?? false));
        $documento->setUploadedBy($uploadedBy);

        $processoId = (int) ($data['processo_id'] ?? 0);
        if ($processoId > 0) {
            $processo = $this->processoRepo->findOneByEmpresa($empresa, $processoId);
            $documento->setProcesso($processo);
        }

        $this->em->persist($documento);
        $this->em->flush();

        // Best-effort: indexa no RAG do JurisFlow para "sugerir peças similares".
        // Nunca lança exceção nem bloqueia o upload em caso de falha.
        $this->ragSync->sync($documento);

        return $documento;
    }

    public function persistOnly(JuridicoDocumento $documento): void
    {
        $this->em->flush();
    }

    public function marcarPrecedente(JuridicoDocumento $documento, bool $precedente, ?string $resultado = null): void
    {
        $documento->setPrecedente($precedente);
        $documento->setResultadoPrecedente($resultado);
        $this->em->flush();
    }

    public function solicitarAssinatura(JuridicoDocumento $documento, string $provider = 'clicksign'): void
    {
        $documento->setAssinaturaStatus('pendente');
        $documento->setAssinaturaProvider($provider);
        $documento->setAssinaturaRef('sandbox-'.bin2hex(random_bytes(6)));
        $this->em->flush();
    }

    public function delete(JuridicoDocumento $documento): void
    {
        $full = $this->resolveAbsolutePath($documento);
        $this->em->remove($documento);
        $this->em->flush();

        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function resolveAbsolutePath(JuridicoDocumento $documento): string
    {
        return $this->projectDir . '/public' . $documento->getArquivoPath();
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoDocumento
    {
        $documento = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$documento) {
            throw new JuridicoProcessException('Documento não encontrado.');
        }

        return $documento;
    }

    /** @return list<JuridicoDocumento> */
    public function findForEmpresa(Empresa $empresa, ?string $categoria = null, ?string $q = null): array
    {
        return $this->repo->findForEmpresa($empresa, $categoria, $q);
    }
}
