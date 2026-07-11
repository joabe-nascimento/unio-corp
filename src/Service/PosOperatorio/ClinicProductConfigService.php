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
        private string $projectDir,
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
        $path = $this->path($empresa);
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $data */
    private function write(Empresa $empresa, array $data): void
    {
        $dir = dirname($this->path($empresa));
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar diretório de produtos clínicos.');
        }
        file_put_contents(
            $this->path($empresa),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    private function path(Empresa $empresa): string
    {
        return sprintf('%s/var/clinic/produtos-%d.json', rtrim($this->projectDir, '/\\'), $empresa->getId());
    }
}
