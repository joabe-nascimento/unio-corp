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
    /** Registro sentinela: usuário teve matriz editada (inclui "sem acesso"). */
    public const MATRIX_MARKER_SCOPE = '_matrix';
    public const MATRIX_MARKER_PRODUCT = '_configured';

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
            if (!$row instanceof UserProductGrant || self::isMatrixMarker($row)) {
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
            if (!$row instanceof UserProductGrant || self::isMatrixMarker($row)) {
                continue;
            }
            $map[$row->getGrantKey()] = $row->getPerfilGrant();
        }

        return $map;
    }

    public function findOneForUserScopeProduct(User $user, string $scope, string $productId): ?UserProductGrant
    {
        if ($scope === self::MATRIX_MARKER_SCOPE || $productId === self::MATRIX_MARKER_PRODUCT) {
            return null;
        }

        return $this->findOneBy([
            'user' => $user,
            'scope' => $scope,
            'productId' => $productId,
        ]);
    }

    public function userHasConfiguredMatrix(User $user): bool
    {
        return null !== $this->findOneBy([
            'user' => $user,
            'scope' => self::MATRIX_MARKER_SCOPE,
            'productId' => self::MATRIX_MARKER_PRODUCT,
        ]);
    }

    public function ensureConfiguredMarker(User $user): void
    {
        if ($this->userHasConfiguredMatrix($user)) {
            return;
        }

        $marker = (new UserProductGrant())
            ->setScope(self::MATRIX_MARKER_SCOPE)
            ->setProductId(self::MATRIX_MARKER_PRODUCT)
            ->setPerfilGrant('CONFIGURED');
        $user->addProductGrant($marker);
        $this->getEntityManager()->persist($marker);
        $this->getEntityManager()->flush();
    }

    public function userHasAnyGrant(User $user): bool
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.user = :user')
            ->andWhere('NOT (g.scope = :mScope AND g.productId = :mProduct)')
            ->setParameter('user', $user)
            ->setParameter('mScope', self::MATRIX_MARKER_SCOPE)
            ->setParameter('mProduct', self::MATRIX_MARKER_PRODUCT)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function deleteAllForUser(User $user): void
    {
        $this->createQueryBuilder('g')
            ->delete()
            ->andWhere('g.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * @return list<UserProductGrant>
     */
    public function findByScope(string $scope): array
    {
        return $this->findBy(['scope' => $scope], ['id' => 'ASC']);
    }

    public static function isMatrixMarker(UserProductGrant $grant): bool
    {
        return $grant->getScope() === self::MATRIX_MARKER_SCOPE
            && $grant->getProductId() === self::MATRIX_MARKER_PRODUCT;
    }
}
