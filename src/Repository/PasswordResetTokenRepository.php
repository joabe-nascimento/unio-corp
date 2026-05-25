<?php

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function invalidateActiveForUser(User $user): void
    {
        /** @var list<PasswordResetToken> $tokens */
        $tokens = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.usadoEm IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        foreach ($tokens as $token) {
            $token->markUsed();
        }
    }

    public function findValidByHash(string $hash): ?PasswordResetToken
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.tokenHash = :hash')
            ->andWhere('t.usadoEm IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('hash', $hash)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
