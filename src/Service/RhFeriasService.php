<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhFerias;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhFeriasRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhFeriasService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhFeriasRepository $repo,
    ) {}

    public function solicitar(
        Empresa $empresa,
        Funcionario $funcionario,
        \DateTimeImmutable $inicio,
        \DateTimeImmutable $fim,
        ?string $observacoes,
        ?User $solicitante,
    ): RhFerias {
        if ($funcionario->getEmpresa()?->getId() !== $empresa->getId()) {
            throw new RhProcessException('Funcionário inválido para esta empresa.');
        }
        if ($funcionario->getStatus() !== 'ATIVO') {
            throw new RhProcessException('Somente funcionários ativos podem solicitar férias.');
        }
        if ($fim < $inicio) {
            throw new RhProcessException('A data final deve ser igual ou posterior à data inicial.');
        }
        if ($this->repo->hasOverlap($funcionario, $inicio, $fim)) {
            throw new RhProcessException('Já existe férias aprovada ou em andamento neste período para este colaborador.');
        }

        $dias = (int) $inicio->diff($fim)->days + 1;

        $ferias = new RhFerias();
        $ferias->setEmpresa($empresa);
        $ferias->setFuncionario($funcionario);
        $ferias->setSolicitante($solicitante);
        $ferias->setDataInicio($inicio);
        $ferias->setDataFim($fim);
        $ferias->setDias($dias);
        $ferias->setObservacoes($observacoes);
        $ferias->setStatus(RhFerias::STATUS_SOLICITADA);

        $this->em->persist($ferias);
        $this->em->flush();

        return $ferias;
    }

    public function aprovar(RhFerias $ferias, User $aprovador): void
    {
        if ($ferias->getStatus() !== RhFerias::STATUS_SOLICITADA) {
            throw new RhProcessException('Somente solicitações pendentes podem ser aprovadas.');
        }

        if ($this->repo->hasOverlap($ferias->getFuncionario(), $ferias->getDataInicio(), $ferias->getDataFim(), $ferias->getId())) {
            throw new RhProcessException('Conflito de datas com outro período de férias.');
        }

        $ferias->setStatus(RhFerias::STATUS_APROVADA);
        $ferias->setAprovador($aprovador);
        $ferias->setAprovadoEm(new \DateTimeImmutable());
        $ferias->touch();

        $hoje = new \DateTimeImmutable('today');
        if ($ferias->getDataInicio() <= $hoje && $ferias->getDataFim() >= $hoje) {
            $ferias->setStatus(RhFerias::STATUS_EM_GOZO);
            $ferias->getFuncionario()->setStatus('FERIAS');
        }

        $this->em->flush();
    }

    public function rejeitar(RhFerias $ferias, User $aprovador, string $motivo): void
    {
        if ($ferias->getStatus() !== RhFerias::STATUS_SOLICITADA) {
            throw new RhProcessException('Somente solicitações pendentes podem ser rejeitadas.');
        }

        $ferias->setStatus(RhFerias::STATUS_REJEITADA);
        $ferias->setAprovador($aprovador);
        $ferias->setMotivoRejeicao(trim($motivo) ?: 'Sem motivo informado.');
        $ferias->setAprovadoEm(new \DateTimeImmutable());
        $ferias->touch();
        $this->em->flush();
    }

    public function iniciarGozo(RhFerias $ferias): void
    {
        if (!\in_array($ferias->getStatus(), [RhFerias::STATUS_APROVADA, RhFerias::STATUS_EM_GOZO], true)) {
            throw new RhProcessException('Férias não estão aprovadas para início.');
        }

        $ferias->setStatus(RhFerias::STATUS_EM_GOZO);
        $ferias->getFuncionario()->setStatus('FERIAS');
        $ferias->touch();
        $this->em->flush();
    }

    public function concluir(RhFerias $ferias): void
    {
        if (!\in_array($ferias->getStatus(), [RhFerias::STATUS_EM_GOZO, RhFerias::STATUS_APROVADA], true)) {
            throw new RhProcessException('Não é possível concluir este período de férias.');
        }

        $ferias->setStatus(RhFerias::STATUS_CONCLUIDA);
        $func = $ferias->getFuncionario();
        if ($func->getStatus() === 'FERIAS') {
            $func->setStatus('ATIVO');
        }
        $ferias->touch();
        $this->em->flush();
    }

    /** @return list<RhFerias> */
    public function listForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        return $this->repo->findByEmpresa($empresa, $status, $q);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): RhFerias
    {
        $f = $this->repo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if (!$f) {
            throw new RhProcessException('Solicitação de férias não encontrada.');
        }

        return $f;
    }

    public function countEmGozo(Empresa $empresa): int
    {
        return $this->repo->countByStatus($empresa, RhFerias::STATUS_EM_GOZO)
            + $this->repo->countByStatus($empresa, RhFerias::STATUS_APROVADA);
    }

    public function syncStatusByDate(Empresa $empresa): void
    {
        $hoje = new \DateTimeImmutable('today');
        foreach ($this->repo->findByEmpresa($empresa, RhFerias::STATUS_APROVADA) as $f) {
            if ($f->getDataInicio() <= $hoje && $f->getDataFim() >= $hoje) {
                $f->setStatus(RhFerias::STATUS_EM_GOZO);
                $f->getFuncionario()->setStatus('FERIAS');
            }
        }
        foreach ($this->repo->findByEmpresa($empresa, RhFerias::STATUS_EM_GOZO) as $f) {
            if ($f->getDataFim() < $hoje) {
                $f->setStatus(RhFerias::STATUS_CONCLUIDA);
                if ($f->getFuncionario()->getStatus() === 'FERIAS') {
                    $f->getFuncionario()->setStatus('ATIVO');
                }
            }
        }
        $this->em->flush();
    }
}
