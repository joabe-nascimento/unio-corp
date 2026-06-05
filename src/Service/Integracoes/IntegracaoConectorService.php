<?php

namespace App\Service\Integracoes;

use App\Config\IntegracaoCatalogRegistry;
use App\Entity\Empresa;
use App\Entity\IntegConector;
use App\Repository\IntegConectorRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoConectorService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegConectorRepository $repo,
        private IntegracaoLogService $logs,
    ) {}

    public function loadForEmpresa(Empresa $empresa, int $id): IntegConector
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if ($item === null) {
            throw new \InvalidArgumentException('Conector não encontrado.');
        }

        return $item;
    }

    /** @return list<array<string, mixed>> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return array_map(static fn (IntegConector $c) => $c->toArray(), $this->repo->findForEmpresa($empresa));
    }

    public function activateFromCatalog(Empresa $empresa, string $catalogoId, array $data = []): IntegConector
    {
        $catalog = IntegracaoCatalogRegistry::find($catalogoId);
        if ($catalog === null) {
            throw new \InvalidArgumentException('Conector não encontrado no catálogo.');
        }

        $existing = $this->repo->findByCatalogoId($empresa, $catalogoId);
        if ($existing !== null) {
            throw new \InvalidArgumentException('Este conector já está ativo na sua empresa.');
        }

        $conector = (new IntegConector())
            ->setEmpresa($empresa)
            ->setCatalogoId($catalogoId)
            ->setNome($catalog['nome'])
            ->setCategoria($catalog['categoria'])
            ->setHubsAlvo($catalog['hubs'])
            ->setEndpointUrl(trim((string) ($data['endpoint_url'] ?? '')) ?: null)
            ->setConfigNotas(trim((string) ($data['config_notas'] ?? '')) ?: null);

        $this->em->persist($conector);
        $this->logs->info($empresa, 'Conector ativado a partir do catálogo', $catalog['nome'], $conector);
        $this->em->flush();

        return $conector;
    }

    /** @param array<string, mixed> $data */
    public function createManual(Empresa $empresa, array $data): IntegConector
    {
        $nome = $this->requireString($data, 'nome', 'Nome');
        $categoria = (string) ($data['categoria'] ?? 'dados');

        $conector = (new IntegConector())
            ->setEmpresa($empresa)
            ->setCatalogoId('custom_' . bin2hex(random_bytes(4)))
            ->setNome($nome)
            ->setCategoria($categoria)
            ->setHubsAlvo([]);

        $this->applyForm($conector, $data);
        $this->em->persist($conector);
        $this->logs->info($empresa, 'Conector customizado cadastrado', $nome, $conector);
        $this->em->flush();

        return $conector;
    }

    /** @param array<string, mixed> $data */
    public function update(IntegConector $conector, array $data): void
    {
        $this->applyForm($conector, $data);
        $conector->touch();
        $this->em->flush();
    }

    public function delete(IntegConector $conector): void
    {
        $this->em->remove($conector);
        $this->em->flush();
    }

    public function togglePause(IntegConector $conector): void
    {
        $conector->setStatus(
            $conector->getStatus() === IntegConector::STATUS_PAUSED
                ? IntegConector::STATUS_ACTIVE
                : IntegConector::STATUS_PAUSED,
        );
        $conector->touch();
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    private function applyForm(IntegConector $conector, array $data): void
    {
        if (isset($data['nome']) && trim((string) $data['nome']) !== '') {
            $conector->setNome(trim((string) $data['nome']));
        }

        $status = (string) ($data['operational_status'] ?? $data['status'] ?? $conector->getStatus());
        if (\in_array($status, [IntegConector::STATUS_ACTIVE, IntegConector::STATUS_PAUSED, IntegConector::STATUS_ERROR], true)) {
            $conector->setStatus($status);
        }

        $health = (string) ($data['health'] ?? $conector->getHealth());
        if (\in_array($health, [IntegConector::HEALTH_HEALTHY, IntegConector::HEALTH_DEGRADED, IntegConector::HEALTH_DOWN], true)) {
            $conector->setHealth($health);
        }

        $endpoint = trim((string) ($data['endpoint_url'] ?? ''));
        $conector->setEndpointUrl($endpoint !== '' ? $endpoint : null);

        $latencia = trim((string) ($data['latencia'] ?? $conector->getLatencia()));
        $conector->setLatencia($latencia !== '' ? $latencia : '—');

        if (isset($data['uptime'])) {
            $uptime = max(0, min(100, $this->parseDecimal($data['uptime'])));
            $conector->setUptime(number_format($uptime, 2, '.', ''));
        }

        if (isset($data['eventos_24h'])) {
            $conector->setEventos24h(max(0, (int) $data['eventos_24h']));
        }

        $notas = trim((string) ($data['config_notas'] ?? ''));
        $conector->setConfigNotas($notas !== '' ? $notas : null);
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

    private function parseDecimal(mixed $value): float
    {
        $raw = trim(str_replace(',', '.', (string) $value));

        return is_numeric($raw) ? (float) $raw : 0.0;
    }
}
