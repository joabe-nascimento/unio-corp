<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\PosOperatorio\ClinicProductCatalog;

/**
 * Produtos clínicos ativados por clínica (arquivo local, sem migration).
 */
final class ClinicProductConfigService
{
    public function __construct(
        private ClinicConfigStore $store,
    ) {}

    /** @return array<string, bool> */
    public function get(Empresa $empresa): array
    {
        $enabled = ClinicProductCatalog::defaultEnabledMap();
        $stored = $this->read($empresa);
        $products = $stored['products'] ?? null;
        if (!\is_array($products)) {
            return $enabled;
        }

        foreach ($enabled as $id => $_) {
            if (\array_key_exists($id, $products)) {
                $enabled[$id] = (bool) $products[$id];
            }
        }

        return $enabled;
    }

    /** @return array<string, bool> */
    public function enabledMap(Empresa $empresa): array
    {
        return $this->get($empresa);
    }

    /** @param array<string, mixed> $data */
    public function save(Empresa $empresa, array $data): void
    {
        $current = $this->read($empresa);
        $products = $current['products'] ?? [];
        if (!\is_array($products)) {
            $products = [];
        }

        foreach (ClinicProductCatalog::defaultEnabledMap() as $id => $_) {
            if (\array_key_exists($id, $data)) {
                $products[$id] = (bool) $data[$id];
            }
        }

        $current['products'] = $products;
        $this->write($empresa, $current);
    }

    public function isEnabled(Empresa $empresa, string $productId): bool
    {
        return $this->get($empresa)[$productId] ?? true;
    }

    /** @return list<array<string, mixed>> */
    public function productsForEmpresa(Empresa $empresa): array
    {
        $enabled = $this->get($empresa);
        $items = [];
        foreach (ClinicProductCatalog::all() as $product) {
            $id = (string) $product['id'];
            $items[] = array_merge($product, [
                'enabled' => $enabled[$id] ?? true,
            ]);
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function read(Empresa $empresa): array
    {
        return $this->store->read($empresa, 'produtos');
    }

    /** @param array<string, mixed> $data */
    private function write(Empresa $empresa, array $data): void
    {
        $this->store->write($empresa, 'produtos', $data);
    }
}
