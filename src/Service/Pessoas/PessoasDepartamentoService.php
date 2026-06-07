<?php

namespace App\Service\Pessoas;

use App\Entity\Departamento;
use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Exception\RhProcessException;
use App\Repository\DepartamentoRepository;
use App\Repository\FuncionarioRepository;
use Doctrine\ORM\EntityManagerInterface;

class PessoasDepartamentoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DepartamentoRepository $repo,
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    /** @return list<Departamento> */
    public function list(Empresa $empresa, ?string $q = null, ?string $area = null): array
    {
        return $this->repo->findByEmpresaWithFilters($empresa, $q, $area);
    }

    public function load(Empresa $empresa, int $id): Departamento
    {
        $dept = $this->repo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if (!$dept) {
            throw new RhProcessException('Equipe não encontrada.');
        }

        return $dept;
    }

    /** @return list<Funcionario> */
    public function listMembros(Departamento $dept): array
    {
        $empresa = $dept->getEmpresa();
        if (!$empresa) {
            return [];
        }

        return $this->funcionarioRepo->findByDepartamento($empresa, (int) $dept->getId());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): Departamento
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw new RhProcessException('Nome da equipe é obrigatório.');
        }

        $dept = new Departamento();
        $dept->setEmpresa($empresa);
        $dept->setNome($nome);
        $this->applyData($dept, $data);

        $this->em->persist($dept);
        $this->em->flush();

        return $dept;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Departamento $dept, array $data): void
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw new RhProcessException('Nome da equipe é obrigatório.');
        }

        $dept->setNome($nome);
        $this->applyData($dept, $data);
        $this->em->flush();
    }

    /** @return list<string> */
    public function listAreas(Empresa $empresa): array
    {
        return $this->repo->findDistinctAreas($empresa);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyData(Departamento $dept, array $data): void
    {
        $dept->setCodigo($this->nullIfEmpty($data['codigo'] ?? null));
        $dept->setDescricao($this->nullIfEmpty($data['descricao'] ?? null));
        $dept->setArea($this->nullIfEmpty($data['area'] ?? null));

        $liderId = (int) ($data['lider_id'] ?? 0);
        $empresa = $dept->getEmpresa();
        if ($liderId > 0 && $empresa) {
            $lider = $this->funcionarioRepo->findOneBy(['id' => $liderId, 'empresa' => $empresa]);
            $dept->setLider($lider);
        } else {
            $dept->setLider(null);
        }
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
