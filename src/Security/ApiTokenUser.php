<?php

namespace App\Security;

use App\Entity\ApiToken;
use App\Entity\Empresa;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Identidade "de máquina" usada pelo firewall stateless da API Pública — representa
 * um token de integração, não uma pessoa. Carrega a empresa dona do token para que os
 * controllers da API pública nunca precisem confiar em parâmetros da requisição.
 */
final class ApiTokenUser implements UserInterface
{
    public function __construct(
        private ApiToken $token,
    ) {
    }

    public function getToken(): ApiToken
    {
        return $this->token;
    }

    public function getEmpresa(): Empresa
    {
        return $this->token->getEmpresa();
    }

    public function hasScope(string $scope): bool
    {
        return $this->token->hasScope($scope);
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_API_CLIENT'];
        foreach ($this->token->getScopes() as $scope) {
            $roles[] = 'ROLE_API_SCOPE_' . strtoupper($scope);
        }

        return $roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'api-token:' . $this->token->getId();
    }
}
