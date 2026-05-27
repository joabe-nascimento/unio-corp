<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhVaga;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhCandidatoRepository;
use App\Repository\RhVagaRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhRecrutamentoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhVagaRepository $vagaRepo,
        private RhCandidatoRepository $candidatoRepo,
        private RhAuditService $audit,
    ) {}

    /** @return list<RhVaga> */
    public function listVagas(Empresa $empresa, ?string $status = null): array
    {
        return $this->vagaRepo->findForEmpresa($empresa, $status);
    }

    public function createVaga(Empresa $empresa, string $titulo, ?string $departamento, ?string $descricao, ?User $actor): RhVaga
    {
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new RhProcessException('Informe o título da vaga.');
        }

        $vaga = new RhVaga();
        $vaga->setEmpresa($empresa);
        $vaga->setTitulo($titulo);
        $vaga->setDepartamento($departamento !== '' ? $departamento : null);
        $vaga->setDescricao($descricao !== '' ? $descricao : null);
        $vaga->setStatus(RhVaga::STATUS_ABERTA);

        $this->em->persist($vaga);
        $this->em->flush();

        $this->audit->log($empresa, $actor, 'recrutamento', 'criar_vaga', 'rh_vaga', $vaga->getId());

        return $vaga;
    }

    public function updateVaga(RhVaga $vaga, string $titulo, ?string $departamento, ?string $descricao, string $status, ?User $actor): RhVaga
    {
        $vaga->setTitulo(trim($titulo));
        $vaga->setDepartamento($departamento !== '' ? $departamento : null);
        $vaga->setDescricao($descricao !== '' ? $descricao : null);
        $vaga->setStatus($status);

        $this->em->flush();
        $this->audit->log($vaga->getEmpresa(), $actor, 'recrutamento', 'atualizar_vaga', 'rh_vaga', $vaga->getId());

        return $vaga;
    }

    public function addCandidato(RhVaga $vaga, string $nome, string $email, ?string $telefone, ?User $actor): RhCandidato
    {
        $nome = trim($nome);
        $email = trim($email);
        if ($nome === '' || $email === '') {
            throw new RhProcessException('Informe nome e e-mail do candidato.');
        }

        $candidato = new RhCandidato();
        $candidato->setVaga($vaga);
        $candidato->setNome($nome);
        $candidato->setEmail($email);
        $candidato->setTelefone($telefone !== '' ? $telefone : null);
        $candidato->setEtapa('TRIAGEM');

        $this->em->persist($candidato);
        $this->em->flush();

        $this->audit->log($vaga->getEmpresa(), $actor, 'recrutamento', 'adicionar_candidato', 'rh_candidato', $candidato->getId());

        return $candidato;
    }

    /** @return list<RhCandidato> */
    public function listCandidatos(RhVaga $vaga): array
    {
        return $this->candidatoRepo->findByVaga($vaga);
    }

    /**
     * Stub: vincula candidato a processo de onboarding quando implementado end-to-end.
     */
    public function convertToOnboarding(RhCandidato $candidato, ?User $actor): ?\App\Entity\RhOnboardingProcess
    {
        if ($candidato->getOnboardingProcess()) {
            return $candidato->getOnboardingProcess();
        }

        $empresa = $candidato->getVaga()->getEmpresa();
        $this->audit->log(
            $empresa,
            $actor,
            'recrutamento',
            'converter_onboarding_stub',
            'rh_candidato',
            $candidato->getId(),
            ['message' => 'Conversão automática para onboarding — implementação futura.'],
        );

        return null;
    }
}
