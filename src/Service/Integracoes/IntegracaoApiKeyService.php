<?php

namespace App\Service\Integracoes;

use App\Entity\Empresa;
use App\Entity\IntegApiKey;
use App\Repository\IntegApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoApiKeyService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegApiKeyRepository $repo,
        private IntegracaoLogService $logs,
    ) {}

    public function loadForEmpresa(Empresa $empresa, int $id): IntegApiKey
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if ($item === null) {
            throw new \InvalidArgumentException('Chave de API não encontrada.');
        }

        return $item;
    }

    /** @return list<array<string, mixed>> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return array_map(static fn (IntegApiKey $k) => $k->toArray(), $this->repo->findForEmpresa($empresa));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{key: IntegApiKey, plain: string}
     */
    public function create(Empresa $empresa, array $data): array
    {
        $nome = $this->requireString($data, 'nome', 'Nome');
        $ambiente = (string) ($data['ambiente'] ?? IntegApiKey::AMB_PROD);
        if (!\in_array($ambiente, [IntegApiKey::AMB_DEV, IntegApiKey::AMB_PROD], true)) {
            throw new \InvalidArgumentException('Ambiente inválido.');
        }

        $scopesRaw = $data['scopes'] ?? [];
        $scopes = \is_array($scopesRaw)
            ? array_values(array_filter(array_map('trim', $scopesRaw)))
            : array_values(array_filter(array_map('trim', explode(',', (string) $scopesRaw))));

        $plain = 'unio_' . ($ambiente === IntegApiKey::AMB_DEV ? 'dev_' : 'live_') . bin2hex(random_bytes(16));
        $prefix = substr($plain, 0, 12);

        $apiKey = (new IntegApiKey())
            ->setEmpresa($empresa)
            ->setNome($nome)
            ->setPrefix($prefix)
            ->setHash(hash('sha256', $plain))
            ->setScopes($scopes !== [] ? $scopes : ['read:hub'])
            ->setAmbiente($ambiente);

        $this->em->persist($apiKey);
        $this->logs->info($empresa, 'Nova chave de API gerada', $nome);
        $this->em->flush();

        return ['key' => $apiKey, 'plain' => $plain];
    }

    public function revoke(IntegApiKey $apiKey): void
    {
        $apiKey->setRevogadaEm(new \DateTimeImmutable());
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    private function requireString(array $data, string $key, string $label): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException($label . ' é obrigatório.');
        }

        return $value;
    }
}
