<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoProcessoTarefa;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\JuridicoProcessoTarefaRepository;

/**
 * Motor de alertas de risco da carteira de processos: agrega, em tempo real,
 * situações que pedem atenção do escritório — sem precisar de uma tabela própria
 * de alertas (evita estado duplicado/desatualizado).
 *
 * @phpstan-type RiscoAlerta array{processo: JuridicoProcesso, tipo: string, nivel: 'alto'|'medio'|'baixo', icone: string, mensagem: string}
 */
class JuridicoRiscoAlertaService
{
    private const DIAS_SEM_MOVIMENTACAO = 45;
    private const DIAS_TAREFA_PROXIMA = 3;
    private const VALOR_ALTO = 300000.0;

    public function __construct(
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoProcessoTarefaRepository $tarefaRepo,
    ) {}

    /** @return list<RiscoAlerta> */
    public function gerarAlertas(Empresa $empresa): array
    {
        $processos = $this->processoRepo->findForEmpresa($empresa);
        $tarefasPendentes = $this->tarefaRepo->findPendentesForEmpresa($empresa);

        $tarefasPorProcesso = [];
        foreach ($tarefasPendentes as $tarefa) {
            $tarefasPorProcesso[$tarefa->getProcesso()->getId()][] = $tarefa;
        }

        $alertas = [];
        foreach ($processos as $processo) {
            $alertas = array_merge($alertas, $this->avaliarProcesso($processo, $tarefasPorProcesso[$processo->getId()] ?? []));
        }

        usort($alertas, fn (array $a, array $b) => $this->pesoNivel($b['nivel']) <=> $this->pesoNivel($a['nivel']));

        return $alertas;
    }

    /**
     * @param list<JuridicoProcessoTarefa> $tarefasPendentes
     * @return list<RiscoAlerta>
     */
    public function avaliarProcesso(JuridicoProcesso $processo, array $tarefasPendentes): array
    {
        if ($processo->getStatus() === JuridicoProcesso::STATUS_ENCERRADO) {
            return [];
        }

        $alertas = [];

        if ($processo->getStatus() === JuridicoProcesso::STATUS_CRITICO) {
            $alertas[] = $this->montar($processo, 'critico', 'alto', 'fa-triangle-exclamation', 'Processo marcado como crítico — priorize a análise.');
        }

        $agora = new \DateTimeImmutable();
        $limiteProximo = $agora->modify('+' . self::DIAS_TAREFA_PROXIMA . ' days');
        foreach ($tarefasPendentes as $tarefa) {
            if ($tarefa->getPrazo() === null) {
                continue;
            }
            if ($tarefa->getPrazo() < $agora) {
                $alertas[] = $this->montar($processo, 'tarefa_atrasada', 'alto', 'fa-hourglass-end', 'Tarefa "' . $tarefa->getTitulo() . '" está atrasada.');
            } elseif ($tarefa->getPrazo() <= $limiteProximo) {
                $alertas[] = $this->montar($processo, 'tarefa_proxima', 'medio', 'fa-clock', 'Tarefa "' . $tarefa->getTitulo() . '" vence em breve.');
            }
        }

        $referencia = $processo->getAtualizadoEm() ?? $processo->getCriadoEm();
        $diasSemMovimentacao = $referencia->diff($agora)->days ?? 0;
        if ($diasSemMovimentacao >= self::DIAS_SEM_MOVIMENTACAO) {
            $alertas[] = $this->montar($processo, 'sem_movimentacao', 'medio', 'fa-clock-rotate-left', 'Sem atualização há ' . $diasSemMovimentacao . ' dias.');
        }

        $valor = $processo->getValor() !== null ? (float) $processo->getValor() : 0.0;
        if ($valor >= self::VALOR_ALTO && $tarefasPendentes === [] && $processo->getStatus() !== JuridicoProcesso::STATUS_CRITICO) {
            $alertas[] = $this->montar($processo, 'valor_alto_sem_acao', 'baixo', 'fa-sack-dollar', 'Valor elevado em carteira sem tarefas planejadas.');
        }

        return $alertas;
    }

    /** @return RiscoAlerta */
    private function montar(JuridicoProcesso $processo, string $tipo, string $nivel, string $icone, string $mensagem): array
    {
        return ['processo' => $processo, 'tipo' => $tipo, 'nivel' => $nivel, 'icone' => $icone, 'mensagem' => $mensagem];
    }

    private function pesoNivel(string $nivel): int
    {
        return match ($nivel) {
            'alto' => 3,
            'medio' => 2,
            default => 1,
        };
    }
}
