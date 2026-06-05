<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiCatalogoItem;
use App\Repository\TiCatalogoItemRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TiCatalogoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiCatalogoItemRepository $repository,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(Empresa $empresa): array
    {
        $custom = array_map(fn ($i) => $i->toArray(), $this->repository->findBy(['empresa' => $empresa, 'ativo' => true]));
        if (!empty($custom)) {
            return $custom;
        }

        return TiReferenceData::catalog();
    }

    public function create(Empresa $empresa, array $data): TiCatalogoItem
    {
        $item = new TiCatalogoItem();
        $item->setEmpresa($empresa)
            ->setItemId(preg_replace('/[^a-z0-9_]/', '_', mb_strtolower($data['titulo'] ?? 'item')) . '_' . time())
            ->setTitulo($data['titulo'] ?? '')
            ->setDescricao($data['descricao'] ?? null)
            ->setCategoria($data['categoria'] ?? 'sistema')
            ->setPrioridadePadrao($data['prioridade_padrao'] ?? 'P3')
            ->setSlaHoras((int) ($data['sla_horas'] ?? 8));

        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    public function update(TiCatalogoItem $item, array $data): void
    {
        $item->setTitulo($data['titulo'] ?? $item->getTitulo())
             ->setDescricao($data['descricao'] ?? $item->getDescricao())
             ->setCategoria($data['categoria'] ?? $item->getCategoria())
             ->setPrioridadePadrao($data['prioridade_padrao'] ?? $item->getPrioridadePadrao())
             ->setSlaHoras((int) ($data['sla_horas'] ?? $item->getSlaHoras()))
             ->setAtivo(isset($data['ativo']) ? (bool) $data['ativo'] : true);

        $this->em->flush();
    }

    public function delete(TiCatalogoItem $item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }

    public function load(Empresa $empresa, int $id): TiCatalogoItem
    {
        $item = $this->repository->find($id);
        if (!$item || $item->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Item não encontrado.');
        }

        return $item;
    }
}
