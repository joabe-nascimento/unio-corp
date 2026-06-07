<?php

namespace App\Service\Pessoas;

use App\Entity\Empresa;
use App\Repository\DepartamentoRepository;
use App\Repository\FuncionarioRepository;
use App\Repository\PessoasAvaliacaoRepository;
use App\Repository\PessoasCargoRepository;

class PessoasDashboardService
{
    public function __construct(
        private FuncionarioRepository $funcionarioRepo,
        private DepartamentoRepository $departamentoRepo,
        private PessoasCargoRepository $cargoRepo,
        private PessoasAvaliacaoRepository $avaliacaoRepo,
    ) {}

    /**
     * @return array{
     *   total_membros: int,
     *   equipes: int,
     *   cargos: int,
     *   avaliacoes: int,
     *   membros_ativos: int,
     *   membros_ferias: int
     * }
     */
    public function getStats(Empresa $empresa): array
    {
        $byStatus = $this->funcionarioRepo->countByStatusGrouped($empresa);

        return [
            'total_membros' => array_sum($byStatus),
            'equipes' => $this->departamentoRepo->countByEmpresa($empresa),
            'cargos' => $this->cargoRepo->countByEmpresa($empresa),
            'avaliacoes' => $this->avaliacaoRepo->countByEmpresa($empresa),
            'membros_ativos' => $byStatus['ATIVO'] ?? 0,
            'membros_ferias' => $byStatus['FERIAS'] ?? 0,
        ];
    }

    /**
     * @return array{
     *   total: int,
     *   ativos: int,
     *   ferias: int,
     *   equipes: int,
     *   sem_equipe: int
     * }
     */
    public function getMembrosStats(Empresa $empresa): array
    {
        $byStatus = $this->funcionarioRepo->countByStatusGrouped($empresa);

        return [
            'total' => array_sum($byStatus),
            'ativos' => $byStatus['ATIVO'] ?? 0,
            'ferias' => $byStatus['FERIAS'] ?? 0,
            'equipes' => $this->departamentoRepo->countByEmpresa($empresa),
            'sem_equipe' => $this->funcionarioRepo->countWithoutDepartamento($empresa),
        ];
    }

    /**
     * @return array{
     *   equipes: int,
     *   total_membros: int,
     *   gestores: int,
     *   sem_equipe: int
     * }
     */
    public function getEquipesStats(Empresa $empresa): array
    {
        return [
            'equipes' => $this->departamentoRepo->countByEmpresa($empresa),
            'total_membros' => $this->funcionarioRepo->countByEmpresa($empresa),
            'gestores' => $this->departamentoRepo->countWithLider($empresa),
            'sem_equipe' => $this->funcionarioRepo->countWithoutDepartamento($empresa),
        ];
    }
}
