<?php

namespace App\Repository;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicAtendimento;
use App\Entity\ClinicConta;
use App\Entity\Empresa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ClinicConta> */
class ClinicContaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClinicConta::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?ClinicConta
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    public function findOneByAgendamento(Empresa $empresa, ClinicAgendamento $agendamento): ?ClinicConta
    {
        return $this->findOneBy(['empresa' => $empresa, 'agendamento' => $agendamento]);
    }

    public function findOneByAtendimento(Empresa $empresa, ClinicAtendimento $atendimento): ?ClinicConta
    {
        return $this->findOneBy(['empresa' => $empresa, 'atendimento' => $atendimento]);
    }

    /**
     * @return list<ClinicConta>
     */
    public function findByEmpresaAndStatus(Empresa $empresa, ?string $status = null, int $limit = 80): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.criadoEm', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '' && $status !== 'todos') {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
