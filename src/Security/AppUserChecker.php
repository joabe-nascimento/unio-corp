<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AppUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isAtivo()) {
            throw new CustomUserMessageAccountStatusException(
                'Sua conta está desativada. Entre em contato com o administrador da plataforma.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
