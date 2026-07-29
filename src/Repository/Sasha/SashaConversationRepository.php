<?php

namespace App\Repository\Sasha;

use App\Entity\Empresa;
use App\Entity\Sasha\SashaConversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SashaConversation>
 */
class SashaConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SashaConversation::class);
    }

    /**
     * @return SashaConversation[]
     */
    public function findByUser(User $user, ?Empresa $empresa = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.pinned', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit);

        if ($empresa !== null) {
            $qb->andWhere('c.empresa = :empresa')
                ->setParameter('empresa', $empresa);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByContext(User $user, string $context, string $contextId): ?SashaConversation
    {
        return $this->createQueryBuilder('c')
            ->where('c.user = :user')
            ->andWhere('c.context = :context')
            ->andWhere('c.contextId = :contextId')
            ->setParameter('user', $user)
            ->setParameter('context', $context)
            ->setParameter('contextId', $contextId)
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(SashaConversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SashaConversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
