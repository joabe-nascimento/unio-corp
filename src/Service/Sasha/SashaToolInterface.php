<?php

namespace App\Service\Sasha;

use App\Entity\User;

interface SashaToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /** @return list<string> */
    public function getRequiredScopes(): array;

    public function supports(User $user): bool;

    /**
     * @param array<string, mixed> $params
     *
     * @return array{summary: string, results: list<array<string, mixed>>}
     */
    public function execute(User $user, array $params): array;
}
