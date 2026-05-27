<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Repository\FuncionarioRepository;

class RhOrganogramaService
{
    public function __construct(
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    /**
     * @return list<array{id: int, nome: string, cargo: ?string, gestor_id: ?int, children: list<mixed>}>
     */
    public function buildTree(Empresa $empresa): array
    {
        $funcionarios = $this->funcionarioRepo->findAtivosForOrganograma($empresa);
        $nodes = [];

        foreach ($funcionarios as $f) {
            $nodes[$f->getId()] = [
                'id' => $f->getId(),
                'nome' => $f->getNome(),
                'cargo' => $f->getCargo(),
                'gestor_id' => $f->getGestor()?->getId(),
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($funcionarios as $f) {
            $id = $f->getId();
            $gestorId = $f->getGestor()?->getId();
            if ($gestorId !== null && isset($nodes[$gestorId]) && $gestorId !== $id) {
                $nodes[$gestorId]['children'][] = &$nodes[$id];
            } else {
                $roots[] = &$nodes[$id];
            }
        }

        return $roots;
    }

    public function countNodes(Empresa $empresa): int
    {
        return \count($this->funcionarioRepo->findAtivosForOrganograma($empresa));
    }
}
