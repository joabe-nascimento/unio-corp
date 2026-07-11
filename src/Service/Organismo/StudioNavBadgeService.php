<?php

namespace App\Service\Organismo;

use App\Entity\DevProjeto;
use App\Entity\Empresa;
use App\Repository\DevProjetoRepository;
use App\Repository\DevTarefaRepository;

/** Indicadores da plataforma base (clientes, projetos, balanceamento, módulos). */
final class StudioNavBadgeService
{
    public function __construct(
        private DevProjetoRepository $projetos,
        private DevTarefaRepository $tarefas,
    ) {
    }

    /**
     * @return array{
     *     clientes: int,
     *     projetos_ativos: int,
     *     projetos_total: int,
     *     projetos_concluidos: int,
     *     prioridade_alta: int,
     *     alertas: int,
     *     balanceamento: int
     * }
     */
    public function forEmpresa(?Empresa $empresa, int $empresasCount): array
    {
        if ($empresa === null) {
            return $this->empty();
        }

        $ativos = $this->projetos->countByStatus($empresa, DevProjeto::STATUS_EM_ANDAMENTO);
        $concluidos = $this->projetos->countByStatus($empresa, DevProjeto::STATUS_FEITO);

        return [
            'clientes' => max(1, $empresasCount),
            'projetos_ativos' => $ativos,
            'projetos_total' => $this->projetos->countByEmpresa($empresa),
            'projetos_concluidos' => $concluidos,
            'prioridade_alta' => $this->tarefas->countPrioridadeAlta($empresa),
            'alertas' => $this->tarefas->countAbertas($empresa),
            'balanceamento' => $this->projetos->averageProgressoAtivos($empresa),
        ];
    }

    /** @return array<string, int> */
    private function empty(): array
    {
        return [
            'clientes' => 0,
            'projetos_ativos' => 0,
            'projetos_total' => 0,
            'projetos_concluidos' => 0,
            'prioridade_alta' => 0,
            'alertas' => 0,
            'balanceamento' => 0,
        ];
    }
}
