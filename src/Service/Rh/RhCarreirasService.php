<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhVaga;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\EmpresaRepository;
use App\Repository\RhVagaRepository;
use App\Rh\RhCandidatoOrigem;
use App\Rh\RhSlugger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class RhCarreirasService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmpresaRepository $empresaRepo,
        private RhVagaRepository $vagaRepo,
        private RhRecrutamentoService $recrutamento,
        private RhCandidatoAttachmentService $attachments,
        private RhRecrutamentoEmailService $emails,
        private RhTalentoPoolService $talentos,
    ) {}

    public function resolveEmpresa(string $slug): Empresa
    {
        $empresa = $this->empresaRepo->findBySlug($slug);
        if ($empresa === null || !$empresa->isCarreirasAtivo()) {
            throw new RhProcessException('Página de carreiras não disponível.');
        }

        return $empresa;
    }

    /** @return list<RhVaga> */
    public function listVagasPublicas(Empresa $empresa): array
    {
        return $this->vagaRepo->findPublicadasForEmpresa($empresa);
    }

    public function resolveVaga(Empresa $empresa, string $vagaSlug): RhVaga
    {
        $vaga = $this->vagaRepo->findPublicadaBySlug($empresa, $vagaSlug);
        if ($vaga === null) {
            throw new RhProcessException('Vaga não encontrada ou indisponível.');
        }

        return $vaga;
    }

    public function apply(
        RhVaga $vaga,
        string $nome,
        string $email,
        ?string $telefone,
        ?string $linkedin,
        ?UploadedFile $curriculo,
    ): RhCandidato {
        $candidato = $this->recrutamento->addCandidato(
            $vaga,
            $nome,
            $email,
            $telefone,
            null,
            RhCandidatoOrigem::SITE,
            $linkedin,
        );

        if ($curriculo instanceof UploadedFile && $curriculo->isValid()) {
            $this->attachments->uploadCurriculo($candidato, $curriculo);
        }

        $this->talentos->upsertFromCandidato($candidato);
        $this->emails->queueCandidaturaConfirmacao($candidato);

        return $candidato;
    }

    public function ensureEmpresaSlug(Empresa $empresa): void
    {
        if ($empresa->getSlug() !== null && $empresa->getSlug() !== '') {
            return;
        }

        $slug = RhSlugger::unique(
            $empresa->getNome(),
            fn (string $s) => $this->empresaRepo->slugExists($s, $empresa->getId()),
        );
        $empresa->setSlug($slug);
        $this->em->flush();
    }

    public function publishVaga(RhVaga $vaga): RhVaga
    {
        if (!$vaga->isPublicavel()) {
            throw new RhProcessException('Apenas vagas abertas podem ser publicadas.');
        }

        if ($vaga->getSlug() === null || $vaga->getSlug() === '') {
            $empresa = $vaga->getEmpresa();
            $slug = RhSlugger::unique(
                $vaga->getTitulo(),
                fn (string $s) => $this->vagaRepo->vagaSlugExists($empresa, $s, $vaga->getId()),
            );
            $vaga->setSlug($slug);
        }

        if ($vaga->getPublicadaEm() === null) {
            $vaga->setPublicadaEm(new \DateTimeImmutable());
        }

        $this->em->flush();

        return $vaga;
    }

    public function unpublishVaga(RhVaga $vaga): RhVaga
    {
        $vaga->setPublicadaEm(null);
        $this->em->flush();

        return $vaga;
    }

    public function updateCarreirasConfig(
        Empresa $empresa,
        bool $ativo,
        ?string $titulo,
        ?string $descricao,
        ?string $slug = null,
    ): Empresa {
        if ($slug !== null && trim($slug) !== '') {
            $normalized = RhSlugger::slugify(trim($slug));
            if ($this->empresaRepo->slugExists($normalized, $empresa->getId())) {
                throw new RhProcessException('Este slug já está em uso.');
            }
            $empresa->setSlug($normalized);
        } elseif ($ativo) {
            $this->ensureEmpresaSlug($empresa);
        }

        $empresa->setCarreirasAtivo($ativo);
        $empresa->setCarreirasTitulo($titulo !== '' ? trim((string) $titulo) : null);
        $empresa->setCarreirasDescricao($descricao !== '' ? trim((string) $descricao) : null);
        $this->em->flush();

        return $empresa;
    }

    public function publicUrl(Empresa $empresa): ?string
    {
        if (!$empresa->isCarreirasAtivo() || $empresa->getSlug() === null) {
            return null;
        }

        return '/carreiras/' . $empresa->getSlug();
    }
}
