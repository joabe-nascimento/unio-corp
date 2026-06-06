<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhTalentoPool;
use App\Entity\RhVaga;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhCandidatoRepository;
use App\Repository\RhTalentoPoolRepository;
use App\Rh\RhCandidatoOrigem;
use Doctrine\ORM\EntityManagerInterface;

final class RhTalentoPoolService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhTalentoPoolRepository $poolRepo,
        private RhCandidatoRepository $candidatoRepo,
        private RhRecrutamentoService $recrutamento,
        private RhAuditService $audit,
    ) {}

    /** @return list<RhTalentoPool> */
    public function listForEmpresa(Empresa $empresa, ?string $q = null): array
    {
        return $this->poolRepo->findForEmpresa($empresa, $q);
    }

    public function upsertFromCandidato(RhCandidato $candidato): RhTalentoPool
    {
        $empresa = $candidato->getVaga()->getEmpresa();
        $existing = $this->poolRepo->findOneByEmail($empresa, $candidato->getEmail());

        if ($existing === null) {
            $existing = new RhTalentoPool();
            $existing->setEmpresa($empresa);
            $existing->setEmail(mb_strtolower(trim($candidato->getEmail())));
        }

        $existing->setNome($candidato->getNome());
        $existing->setTelefone($candidato->getTelefone());
        $existing->setLinkedin($candidato->getLinkedin());
        $tags = $existing->getTags() ?? [];
        $vagaTag = 'vaga:' . $candidato->getVaga()->getTitulo();
        if (!\in_array($vagaTag, $tags, true)) {
            $tags[] = $vagaTag;
        }
        $existing->setTags($tags);
        $existing->touch();

        $this->em->persist($existing);
        $candidato->setNoBancoTalentos(true);
        $this->em->flush();

        return $existing;
    }

    public function addToPoolFromCandidato(RhCandidato $candidato, ?User $actor): RhTalentoPool
    {
        $pool = $this->upsertFromCandidato($candidato);
        $this->audit->log(
            $candidato->getVaga()->getEmpresa(),
            $actor,
            'recrutamento',
            'adicionar_banco_talentos',
            'rh_candidato',
            $candidato->getId(),
        );

        return $pool;
    }

    public function inscreverEmVaga(RhTalentoPool $talento, RhVaga $vaga, ?User $actor): RhCandidato
    {
        if ($this->candidatoRepo->existsByEmailAndVaga($talento->getEmail(), $vaga)) {
            throw new RhProcessException('Este talento já está inscrito nesta vaga.');
        }

        $candidato = $this->recrutamento->addCandidato(
            $vaga,
            $talento->getNome(),
            $talento->getEmail(),
            $talento->getTelefone(),
            $actor,
            RhCandidatoOrigem::BANCO_TALENTOS,
            $talento->getLinkedin(),
        );

        $talento->touch();
        $this->em->flush();

        return $candidato;
    }
}
