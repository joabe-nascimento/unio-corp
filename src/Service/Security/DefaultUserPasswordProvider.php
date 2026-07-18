<?php

namespace App\Service\Security;

/**
 * Senha padrão de contas demo/seed — configurável via APP_DEFAULT_USER_PASSWORD.
 */
final class DefaultUserPasswordProvider
{
    public const FALLBACK = 'unio123';

    public function __construct(
        private string $defaultPassword = self::FALLBACK,
    ) {
    }

    public function get(): string
    {
        return $this->defaultPassword !== '' ? $this->defaultPassword : self::FALLBACK;
    }
}
