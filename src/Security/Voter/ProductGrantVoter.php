<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Repository\UserProductGrantRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Enforce grants granulares quando existem no banco.
 * Sem registro → abstém (rotas continuam protegidas por role global).
 */
final class ProductGrantVoter extends Voter
{
    public const VIEW = 'product_grant.view';

    public function __construct(
        private UserProductGrantRepository $grantRepo,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if ($attribute !== self::VIEW) {
            return false;
        }

        return \is_array($subject)
            && isset($subject['scope'], $subject['product'])
            && \is_string($subject['scope'])
            && \is_string($subject['product']);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !\is_array($subject)) {
            return false;
        }

        if ($user->isTenant()) {
            return true;
        }

        $grant = $this->grantRepo->findOneForUserScopeProduct(
            $user,
            (string) $subject['scope'],
            (string) $subject['product'],
        );

        if (!$grant) {
            if ($this->grantRepo->userHasConfiguredMatrix($user) || $this->grantRepo->userHasAnyGrant($user)) {
                return false;
            }

            return true;
        }

        return $grant->getPerfilGrant() !== '';
    }
}
