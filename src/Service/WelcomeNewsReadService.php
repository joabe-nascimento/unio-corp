<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\WelcomeNewsReadRepository;

/**
 * Persistência de leituras de artigos da boas-vindas.
 */
final class WelcomeNewsReadService
{
    public function __construct(
        private WelcomeNewsReadRepository $readRepo,
    ) {}

  /** @return array<string, true> */
    public function getReadKeyMap(User $user): array
    {
        return $this->readRepo->findReadKeyMapForUser($user);
    }

    public function markRead(User $user, string $articleKey, ?Empresa $empresa): void
    {
        $this->readRepo->markRead($user, $articleKey, $empresa);
    }

    public function countRecentReads(User $user, int $days = 7): int
    {
        $since = new \DateTimeImmutable(sprintf('-%d days', $days));

        return $this->readRepo->countReadsSince($user, $since);
    }
}
