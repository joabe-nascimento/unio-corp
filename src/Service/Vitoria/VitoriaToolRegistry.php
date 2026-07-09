<?php

namespace App\Service\Vitoria;

use App\Entity\User;

final class VitoriaToolRegistry
{
    /** @param iterable<VitoriaToolInterface> $tools */
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

    public function get(string $name): ?VitoriaToolInterface
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
