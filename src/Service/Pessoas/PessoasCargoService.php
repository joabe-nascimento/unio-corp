<?php

namespace App\Service\Pessoas;

use App\Entity\Empresa;
use App\Entity\PessoasCargo;
use App\Exception\RhProcessException;
use App\Repository\DepartamentoRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\PessoasCargoRepository;
use Doctrine\ORM\EntityManagerInterface;

class PessoasCargoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PessoasCargoRepository $repo,
        private FuncionarioRepository $funcionarioRepo,
        private DepartamentoRepository $departamentoRepo,
    ) {}

    /** @return list<PessoasCargo> */
    public function list(Empresa $empresa, ?string $q = null): array
    {
        return $this->repo->findByEmpresa($empresa, $q, true);
    }

    public function load(Empresa $empresa, int $id): PessoasCargo
    {
        $cargo = $this->repo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if (!$cargo) {
            throw new RhProcessException('Cargo não encontrado.');
        }

        return $cargo;
    }

    public function countMembros(Empresa $empresa, string $titulo): int
    {
        return \count($this->funcionarioRepo->findForEmpresaFiltered($empresa, null, null, null, $titulo));
    }

    /** @return list<string> */
    public function listAreaSuggestions(Empresa $empresa): array
    {
        $areas = array_merge(
            $this->departamentoRepo->findDistinctAreas($empresa),
            $this->repo->findDistinctAreas($empresa),
        );

        $areas = array_values(array_unique(array_filter(array_map('trim', $areas))));
        sort($areas, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $areas;
    }

    /**
     * @return array{total: int, membros: int, areas: int}
     */
    public function getListStats(Empresa $empresa, array $cargos, array $membrosPorCargo): array
    {
        $areas = [];
        foreach ($cargos as $cargo) {
            if ($cargo instanceof PessoasCargo && $cargo->getArea()) {
                $areas[$cargo->getArea()] = true;
            }
        }

        return [
            'total' => \count($cargos),
            'membros' => array_sum($membrosPorCargo),
            'areas' => \count($areas),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): PessoasCargo
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            throw new RhProcessException('Título do cargo é obrigatório.');
        }
        if ($this->repo->existsTitulo($empresa, $titulo)) {
            throw new RhProcessException('Já existe um cargo com este título.');
        }

        $cargo = new PessoasCargo();
        $cargo->setEmpresa($empresa);
        $this->applyData($cargo, $data, $titulo);

        $this->em->persist($cargo);
        $this->em->flush();

        return $cargo;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(PessoasCargo $cargo, array $data): void
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            throw new RhProcessException('Título do cargo é obrigatório.');
        }
        $empresa = $cargo->getEmpresa();
        if ($empresa && $this->repo->existsTitulo($empresa, $titulo, $cargo->getId())) {
            throw new RhProcessException('Já existe outro cargo com este título.');
        }

        $this->applyData($cargo, $data, $titulo);
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyData(PessoasCargo $cargo, array $data, string $titulo): void
    {
        $cargo->setTitulo($titulo);
        $cargo->setDescricao($this->nullIfEmpty($data['descricao'] ?? null));
        $cargo->setArea($this->nullIfEmpty($data['area'] ?? null));
        $cargo->setNivel($this->nullIfEmpty($data['nivel'] ?? null));
        $cargo->setAtivo(true);
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
