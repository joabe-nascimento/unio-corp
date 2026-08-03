<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\JuridicoCliente;
use App\Entity\JuridicoDocumento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JuridicoDocumento> */
class JuridicoDocumentoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JuridicoDocumento::class);
    }

    public function findOneByEmpresa(Empresa $empresa, int $id): ?JuridicoDocumento
    {
        return $this->findOneBy(['id' => $id, 'empresa' => $empresa]);
    }

    /** @return list<JuridicoDocumento> */
    public function findForEmpresa(Empresa $empresa, ?string $categoria = null, ?string $q = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.processo', 'p')
            ->addSelect('p')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->orderBy('d.criadoEm', 'DESC');

        if ($categoria !== null && $categoria !== '') {
            $qb->andWhere('d.categoria = :categoria')->setParameter('categoria', $categoria);
        }

        if ($q !== null && trim($q) !== '') {
            $qb->andWhere('d.nome LIKE :q OR p.numero LIKE :q')
                ->setParameter('q', '%' . trim($q) . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<JuridicoDocumento> */
    public function findVisiveisPortal(Empresa $empresa, JuridicoCliente $cliente): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.processo', 'p')
            ->addSelect('p')
            ->andWhere('d.empresa = :empresa')
            ->andWhere('d.visivelPortal = true')
            ->andWhere('d.confidencial = false')
            ->andWhere('p.cliente = :cliente')
            ->setParameter('empresa', $empresa)
            ->setParameter('cliente', $cliente)
            ->orderBy('d.criadoEm', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countRagSincronizados(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.empresa = :empresa')
            ->andWhere('d.ragSincronizadoEm IS NOT NULL')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByEmpresa(Empresa $empresa): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.empresa = :empresa')
            ->setParameter('empresa', $empresa)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
