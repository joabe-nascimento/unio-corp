<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoCliente;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoCliente> */
class JuridicoClienteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoCliente::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoCliente
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoCliente> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.nome', 'ASC');

        if ($status !== null && $status !== '') {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('c.nome LIKE :q OR c.email LIKE :q OR c.documento LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByEmpresaAndStatus(Empresa $empresa, string $status): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.status = :status')
            ->setParameter('empresa', $empresa)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countComPortalAtivo(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.portalUser IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countConvitesPendentes(Empresa $empresa): int
    {
        $agora = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.empresa = :empresa')
            ->andWhere('c.portalUser IS NULL')
            ->andWhere('c.portalInviteToken IS NOT NULL')
            ->andWhere('c.portalInviteExpiresAt IS NULL OR c.portalInviteExpiresAt >= :agora')
            ->setParameter('empresa', $empresa)
            ->setParameter('agora', $agora)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<JuridicoCliente> */
    public function findAllForSelect(Empresa $empresa): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('c.nome', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
