<?php

namespace App\Service\Juridico;

use App\Entity\ApiToken;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Emissão e validação de tokens da API Pública do Unio Jurídico. O token bruto é
 * mostrado uma única vez no momento da criação — só o hash SHA-256 é persistido,
 * seguindo o mesmo padrão de segurança de provedores como GitHub e Stripe.
 */
final class ApiTokenService
{
    private const PREFIX = 'ujr_live_';

    public function __construct(
        private EntityManagerInterface $em,
        private ApiTokenRepository $tokenRepo,
    ) {
    }

    /**
     * @param list<string> $scopes
     *
     * @return array{token: ApiToken, raw: string}
     */
    public function gerar(Empresa $empresa, string $nome, array $scopes, ?User $criadoPor): array
    {
        $raw = self::PREFIX . bin2hex(random_bytes(24));
        $hash = hash('sha256', $raw);

        $token = new ApiToken();
        $token->setEmpresa($empresa);
        $token->setNome($nome !== '' ? $nome : 'Token de integração');
        $token->setTokenHash($hash);
        $token->setTokenPrefix(substr($raw, 0, 16));
        $token->setScopes($scopes === [] ? [ApiToken::SCOPE_LEITURA] : $scopes);
        $token->setCriadoPor($criadoPor);

        $this->em->persist($token);
        $this->em->flush();

        return ['token' => $token, 'raw' => $raw];
    }

    public function validar(string $raw): ?ApiToken
    {
        $raw = trim($raw);
        if (!str_starts_with($raw, self::PREFIX)) {
            return null;
        }

        $token = $this->tokenRepo->findActiveByHash(hash('sha256', $raw));
        if ($token === null || !$token->isAtivo()) {
            return null;
        }

        $token->registrarUso();
        $this->em->flush();

        return $token;
    }

    public function revogar(ApiToken $token): void
    {
        $token->revogar();
        $this->em->flush();
    }
}
