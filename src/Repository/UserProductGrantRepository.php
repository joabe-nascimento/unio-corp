<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserProductGrant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserProductGrant>
 */
class UserProductGrantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserProductGrant::class);
    }

    /**
     * @return array<string, string> productId => perfilGrant
     */
    public function findGrantMapForUserAndScope(User $user, string $scope): array
    {
        $rows = $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.scope = :scope')
            ->setParameter('user', $user)
            ->setParameter('scope', $scope)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            if (!$row instanceof UserProductGrant) {
                continue;
            }
            $map[$row->getProductId()] = $row->getPerfilGrant();
        }

        return $map;
    }

    /**
     * @return array<string, string> "scope:productId" => perfilGrant
     */
    public function findAllGrantKeysForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            if (!$row instanceof UserProductGrant) {
                continue;
            }
            $map[$row->getGrantKey()] = $row->getPerfilGrant();
        }

        return $map;
    }

    public function findOneForUserScopeProduct(User $user, string $scope, string $productId): ?UserProductGrant
    {
        return $this->findOneBy([
            'user' => $user,
            'scope' => $scope,
            'productId' => $productId,
        ]);
    }

    public function userHasAnyGrant(User $user): bool
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return list<UserProductGrant>
     */
    public function findByScope(string $scope): array
    {
        return $this->findBy(['scope' => $scope], ['id' => 'ASC']);
    }
}
