<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoAtendimentoTicket;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoAtendimentoTicket> */
class JuridicoAtendimentoTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoAtendimentoTicket::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoAtendimentoTicket
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.cliente', 'cli')->addSelect('cli')
            ->leftJoin('t.processo', 'proc')->addSelect('proc')
            ->leftJoin('t.responsavel', 'resp')->addSelect('resp')
            ->leftJoin('t.mensagens', 'msg')->addSelect('msg')
            ->leftJoin('msg.autor', 'autor')->addSelect('autor')
            ->andWhere('t.id = :id')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('id', $id)
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<JuridicoAtendimentoTicket> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $canal = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.cliente', 'cli')->addSelect('cli')
            ->leftJoin('t.processo', 'proc')->addSelect('proc')
            ->andWhere('t.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('t.criadoEm', 'DESC');

        if ($status !== null && $status !== '') {
            if ($status === 'abertos') {
                $qb->andWhere('t.status != :resolvido')->setParameter('resolvido', JuridicoAtendimentoTicket::STATUS_RESOLVIDO);
            } else {
                $qb->andWhere('t.status = :status')->setParameter('status', $status);
            }
        }

        if ($canal !== null && $canal !== '') {
            $qb->andWhere('t.canal = :canal')->setParameter('canal', $canal);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('t.assunto LIKE :q OR cli.nome LIKE :q OR proc.numero LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countAbertos(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.status != :resolvido')
            ->setParameter('empresa', $empresa)
            ->setParameter('resolvido', JuridicoAtendimentoTicket::STATUS_RESOLVIDO)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSlaEstourado(Empresa $empresa): int
    {
        $agora = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.status != :resolvido')
            ->andWhere('t.primeiraRespostaEm IS NULL')
            ->andWhere('t.slaLimiteEm < :agora')
            ->setParameter('empresa', $empresa)
            ->setParameter('resolvido', JuridicoAtendimentoTicket::STATUS_RESOLVIDO)
            ->setParameter('agora', $agora)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Tempo médio de primeira resposta em minutos (últimos 30 dias). */
    public function slaMedioMinutos(Empresa $empresa): ?float
    {
        $desde = new \DateTimeImmutable('-30 days');

        $tickets = $this->createQueryBuilder('t')
            ->andWhere('t.empresa = :empresa')
            ->andWhere('t.primeiraRespostaEm IS NOT NULL')
            ->andWhere('t.criadoEm >= :desde')
            ->setParameter('empresa', $empresa)
            ->setParameter('desde', $desde)
            ->getQuery()
            ->getResult();

        if ($tickets === []) {
            return null;
        }

        $total = 0.0;
        foreach ($tickets as $ticket) {
            $diff = $ticket->getPrimeiraRespostaEm()->getTimestamp() - $ticket->getCriadoEm()->getTimestamp();
            $total += max(0, $diff) / 60;
        }

        return $total / \count($tickets);
    }
}
