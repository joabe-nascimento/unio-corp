<?php

namespace App\Service\Integracoes;

use App\Entity\Empresa;
use App\Entity\IntegMapeamento;
use App\Repository\IntegConectorRepository;
use App\Repository\IntegMapeamentoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class IntegracaoMapeamentoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private IntegMapeamentoRepository $repo,
        private IntegConectorRepository $conectorRepo,
    ) {}

    public function loadForEmpresa(Empresa $empresa, int $id): IntegMapeamento
    {
        $item = $this->repo->findOneForEmpresa($empresa, $id);
        if ($item === null) {
            throw new \InvalidArgumentException('Mapeamento não encontrado.');
        }

        return $item;
    }

    /** @return list<array<string, mixed>> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return array_map(static fn (IntegMapeamento $m) => $m->toArray(), $this->repo->findForEmpresa($empresa));
    }

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): IntegMapeamento
    {
        $conectorId = (int) ($data['conector_id'] ?? 0);
        $conector = $this->conectorRepo->findOneForEmpresa($empresa, $conectorId);
        if ($conector === null) {
            throw new \InvalidArgumentException('Conector é obrigatório.');
        }

        $map = (new IntegMapeamento())
            ->setEmpresa($empresa)
            ->setConector($conector);

        $this->applyForm($map, $data);
        $this->em->persist($map);
        $this->em->flush();

        return $map;
    }

    /** @param array<string, mixed> $data */
    public function update(IntegMapeamento $map, array $data): void
    {
        $this->applyForm($map, $data);
        $this->em->flush();
    }

    public function delete(IntegMapeamento $map): void
    {
        $this->em->remove($map);
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    private function applyForm(IntegMapeamento $map, array $data): void
    {
        $map
            ->setNome($this->requireString($data, 'nome', 'Nome'))
            ->setCampoOrigem($this->requireString($data, 'campo_origem', 'Campo origem'))
            ->setCampoDestino($this->requireString($data, 'campo_destino', 'Campo destino'));

        $transform = trim((string) ($data['transformacao'] ?? ''));
        $map->setTransformacao($transform !== '' ? $transform : null);

        if (isset($data['ativo'])) {
            $map->setAtivo((bool) $data['ativo']);
        }
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
