<?php

namespace App\Service\Sasha;

use App\Entity\User;

final class SashaToolRegistry
{
    /** @param iterable<SashaToolInterface> $tools */
    public function __construct(
        private iterable $tools,
    ) {
    }

    /** @return list<array{name: string, description: string}> */
    public function listFor(User $user): array
    {
        $list = [];
        foreach ($this->tools as $tool) {
            if (!$tool->supports($user)) {
                continue;
            }
            $list[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
            ];
        }

        return $list;
    }

    public function get(string $name): ?SashaToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }

        return null;
    }

    public function supports(User $user, string $name): bool
    {
        $tool = $this->get($name);

        return $tool !== null && $tool->supports($user);
    }
}
