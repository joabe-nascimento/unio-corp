<?php

namespace App\Repository;

use App\Entity\Empresa;
use App\Entity\User;
use App\Entity\WelcomeNewsRead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WelcomeNewsRead>
 */
class WelcomeNewsReadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WelcomeNewsRead::class);
    }

    /** @return array<string, true> */
    public function findReadKeyMapForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.articleKey')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        $map = [];
        foreach ($rows as $key) {
            $map[(string) $key] = true;
        }

        return $map;
    }

    public function markRead(User $user, string $articleKey, ?Empresa $empresa): void
    {
        $existing = $this->findOneBy([
            'user' => $user,
            'articleKey' => $articleKey,
        ]);

        if ($existing !== null) {
            $existing->setReadAt(new \DateTimeImmutable());
            if ($empresa !== null) {
                $existing->setEmpresa($empresa);
            }
            $this->getEntityManager()->flush();

            return;
        }

        $read = new WelcomeNewsRead();
        $read->setUser($user);
        $read->setArticleKey($articleKey);
        $read->setEmpresa($empresa);

        $em = $this->getEntityManager();
        $em->persist($read);
        $em->flush();
    }

    public function countReadsSince(User $user, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.readAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
