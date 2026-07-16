<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicEstoqueItem;
use App\Entity\ClinicUnidade;
use App\Entity\Empresa;
use App\Repository\ClinicEstoqueItemRepository;
use App\Repository\ClinicUnidadeRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicEstoqueItemService
{
    public function __construct(
        private ClinicEstoqueItemRepository $itens,
        private ClinicUnidadeRepository $unidades,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicEstoqueItem> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->itens->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicEstoqueItem
    {
        return $this->itens->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{item: ClinicEstoqueItem, abaixo_minimo: bool}
     */
    public function create(Empresa $empresa, array $data): array
    {
        $item = new ClinicEstoqueItem();
        $item->setEmpresa($empresa);
        $this->apply($item, $empresa, $data, true);
        $this->em->persist($item);
        $this->em->flush();

        return ['item' => $item, 'abaixo_minimo' => $item->isAbaixoMinimo()];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{item: ClinicEstoqueItem, abaixo_minimo: bool}
     */
    public function update(ClinicEstoqueItem $item, Empresa $empresa, array $data): array
    {
        $this->assertScope($item, $empresa);
        $this->apply($item, $empresa, $data, false);
        $this->em->flush();

        return ['item' => $item, 'abaixo_minimo' => $item->isAbaixoMinimo()];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicEstoqueItem $item, Empresa $empresa, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('nome', $data)) {
            $item->setNome(ClinicCadastroRules::requireNome((string) ($data['nome'] ?? ''), 160));
        }
        if ($creating || \array_key_exists('sku', $data)) {
            $sku = trim((string) ($data['sku'] ?? ''));
            $sku = $sku === '' ? null : mb_substr($sku, 0, 32);
            if ($sku !== null) {
                $this->assertSkuUnique($empresa, $sku, $item->getId());
            }
            $item->setSku($sku);
        }
        if ($creating || \array_key_exists('unidade_medida', $data)) {
            $um = trim((string) ($data['unidade_medida'] ?? 'un'));
            $item->setUnidadeMedida($um === '' ? 'un' : mb_substr($um, 0, 16));
        }
        if ($creating || \array_key_exists('quantidade', $data)) {
            $item->setQuantidade(ClinicCadastroRules::assertQuantidadeNaoNegativa((int) ($data['quantidade'] ?? 0)));
        }
        if ($creating || \array_key_exists('minimo', $data)) {
            $item->setMinimo(ClinicCadastroRules::assertQuantidadeNaoNegativa((int) ($data['minimo'] ?? 0)));
        }
        if ($creating || \array_key_exists('unidade_id', $data)) {
            $item->setUnidade($this->resolveUnidade($empresa, $data['unidade_id'] ?? null));
        }
        if ($creating || \array_key_exists('ativo', $data)) {
            $item->setAtivo(($data['ativo'] ?? true) !== false);
        }
    }

    private function resolveUnidade(Empresa $empresa, mixed $unidadeId): ?ClinicUnidade
    {
        if ($unidadeId === null || $unidadeId === '' || (int) $unidadeId <= 0) {
            return null;
        }
        $unidade = $this->unidades->findOneByEmpresa($empresa, (int) $unidadeId);
        if ($unidade === null) {
            throw new \InvalidArgumentException('Unidade inválida.');
        }

        return $unidade;
    }

    private function assertSkuUnique(Empresa $empresa, string $sku, ?int $ignoreId): void
    {
        $existing = $this->itens->findOneBy(['empresa' => $empresa, 'sku' => $sku]);
        if ($existing !== null && $existing->getId() !== $ignoreId) {
            throw new \InvalidArgumentException('Já existe item de estoque com este SKU.');
        }
    }

    private function assertScope(ClinicEstoqueItem $item, Empresa $empresa): void
    {
        if ($item->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Item de estoque fora do escopo.');
        }
    }
}
