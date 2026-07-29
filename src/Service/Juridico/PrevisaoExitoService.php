<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoProcesso;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\JuridicoProcessoTarefaRepository;

/**
 * Previsão de êxito com dois modos, sempre explicáveis:
 *
 * 1) Heurística (padrão, sempre disponível): combina histórico real da carteira (taxa
 *    de êxito por área), fase processual, saúde de execução (tarefas em dia), tempo de
 *    tramitação e valor da causa — pesos fixos definidos por regra de negócio.
 * 2) Modelo treinado (ativa automaticamente quando há histórico suficiente): regressão
 *    logística calibrada com os casos encerrados do próprio escritório (ou do grupo
 *    econômico, quando o escritório tem filiais vinculadas — "calibração cruzada entre
 *    escritórios"), aprendendo o peso real de cada fator a partir do resultado histórico
 *    real da carteira em vez de valores fixos. Continua 100% explicável: cada fator
 *    listado mostra o coeficiente aprendido e quantos casos embasaram o treino.
 *
 * @phpstan-type FatorPrevisao array{label: string, peso: int, favoravel: bool, detalhe: string}
 * @phpstan-type ModeloTreinado array{
 *     pesos: list<float>, intercepto: float, means: list<float>, stds: list<float>,
 *     acuracia: float, amostras: int, escritorios: int, grupo: bool,
 *     historico_area: array<string, array{total: int, favoraveis: int, taxa: float}>
 * }
 */
final class PrevisaoExitoService
{
    private const BASE = 50;

    /** Mínimo de casos encerrados com resultado conhecido para ativar o modelo treinado. */
    public const MIN_AMOSTRAS_TREINO = 12;

    private const FEATURE_LABELS = ['Histórico da área (aprendido)', 'Fase processual (aprendida)', 'Tempo de tramitação (aprendido)', 'Valor da causa (aprendido)'];

    public function __construct(
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoProcessoTarefaRepository $tarefaRepo,
    ) {
    }

    /**
     * Visão de carteira: score de todos os processos não encerrados da empresa,
     * com filtro por área/nível e ordenação — base do painel "Previsão de Êxito".
     *
     * @return array{
     *     itens: list<array{processo: JuridicoProcesso, previsao: array}>,
     *     total: int,
     *     media: float,
     *     distribuicao: array{alto: int, medio: int, baixo: int},
     *     top_risco: list<array{processo: JuridicoProcesso, previsao: array}>,
     *     areas: list<string>,
     *     modelo_treinado: null|array{amostras: int, acuracia: float, grupo: bool, escritorios: int}
     * }
     */
    public function overview(Empresa $empresa, ?string $area = null, ?string $nivel = null, string $ordenar = 'score_asc'): array
    {
        $modelo = $this->treinar($empresa);

        $processos = array_values(array_filter(
            $this->processoRepo->findForEmpresa($empresa),
            static fn (JuridicoProcesso $p) => $p->getStatus() !== JuridicoProcesso::STATUS_ENCERRADO,
        ));

        $areas = [];
        foreach ($processos as $p) {
            $a = $p->getArea() !== null && $p->getArea() !== '' ? $p->getArea() : 'Não informada';
            if (!\in_array($a, $areas, true)) {
                $areas[] = $a;
            }
        }
        sort($areas);

        $itens = [];
        foreach ($processos as $processo) {
            $processoArea = $processo->getArea() !== null && $processo->getArea() !== '' ? $processo->getArea() : 'Não informada';
            if ($area !== null && $area !== '' && $processoArea !== $area) {
                continue;
            }

            $previsao = $this->prever($processo, $modelo);
            if ($nivel !== null && $nivel !== '' && $previsao['nivel'] !== $nivel) {
                continue;
            }

            $itens[] = ['processo' => $processo, 'previsao' => $previsao];
        }

        $distribuicao = ['alto' => 0, 'medio' => 0, 'baixo' => 0];
        $soma = 0;
        foreach ($itens as $item) {
            ++$distribuicao[$item['previsao']['nivel']];
            $soma += $item['previsao']['score'];
        }
        $total = \count($itens);
        $media = $total > 0 ? round($soma / $total, 1) : 0.0;

        usort($itens, static function (array $a, array $b) use ($ordenar): int {
            return match ($ordenar) {
                'score_desc' => $b['previsao']['score'] <=> $a['previsao']['score'],
                'valor_desc' => (float) ($b['processo']->getValor() ?? 0) <=> (float) ($a['processo']->getValor() ?? 0),
                default => $a['previsao']['score'] <=> $b['previsao']['score'],
            };
        });

        $topRisco = array_slice(
            (static function (array $itens) {
                usort($itens, static fn (array $a, array $b) => $a['previsao']['score'] <=> $b['previsao']['score']);

                return $itens;
            })($itens),
            0,
            5,
        );

        return [
            'itens' => $itens,
            'total' => $total,
            'media' => $media,
            'distribuicao' => $distribuicao,
            'top_risco' => $topRisco,
            'areas' => $areas,
            'modelo_treinado' => $modelo === null ? null : [
                'amostras' => $modelo['amostras'],
                'acuracia' => $modelo['acuracia'],
                'grupo' => $modelo['grupo'],
                'escritorios' => $modelo['escritorios'],
            ],
        ];
    }

    /**
     * @param ModeloTreinado|null $modelo Passe o resultado de {@see self::treinar()} para usar o
     *                                    modelo estatístico calibrado; null usa sempre a heurística.
     *
     * @return array{score: int, nivel: string, label: string, cor: string, fatores: list<FatorPrevisao>, modelo: string}
     */
    public function prever(JuridicoProcesso $processo, ?array $modelo = null): array
    {
        if ($modelo !== null) {
            return $this->preverComModeloTreinado($processo, $modelo);
        }

        $empresa = $processo->getEmpresa();
        $fatores = [];
        $score = self::BASE;

        $score += $this->fatorHistoricoArea($processo, $empresa, $fatores);
        $score += $this->fatorFase($processo, $fatores);
        $score += $this->fatorTarefas($processo, $fatores);
        $score += $this->fatorTempoTramitacao($processo, $fatores);
        $score += $this->fatorValorCausa($processo, $fatores);

        $score = max(5, min(95, $score));

        return [
            'score' => $score,
            'nivel' => $this->nivel($score),
            'label' => $this->labelNivel($score),
            'cor' => $this->corNivel($score),
            'fatores' => $fatores,
            'modelo' => 'heuristica',
        ];
    }

    /**
     * Prevê o êxito usando o modelo automaticamente mais adequado: o treinado quando há
     * histórico suficiente (própria empresa ou grupo econômico), heurística caso contrário.
     * Use este método fora de {@see self::overview()} (que já treina uma única vez).
     *
     * @return array{score: int, nivel: string, label: string, cor: string, fatores: list<FatorPrevisao>, modelo: string}
     */
    public function preverAuto(JuridicoProcesso $processo): array
    {
        return $this->prever($processo, $this->treinar($processo->getEmpresa()));
    }

    /**
     * Treina a regressão logística calibrada com os casos encerrados do escritório — e do
     * grupo econômico (matriz + filiais), quando houver, para dar mais amostras ao modelo
     * ("calibração cruzada entre escritórios"). Retorna null quando não há histórico
     * suficiente ({@see self::MIN_AMOSTRAS_TREINO}) ou quando todos os casos históricos
     * tiveram o mesmo resultado (sem variação para aprender); nesses casos a heurística
     * padrão continua sendo usada.
     *
     * @return ModeloTreinado|null
     */
    public function treinar(Empresa $empresa): ?array
    {
        $empresasGrupo = $empresa->grupoEconomico();
        $processos = $this->processoRepo->findComResultado($empresasGrupo);

        if (\count($processos) < self::MIN_AMOSTRAS_TREINO) {
            return null;
        }

        $historicoArea = $this->processoRepo->taxaExitoPorAreaGrupo($empresasGrupo);

        $x = [];
        $y = [];
        foreach ($processos as $processo) {
            $fim = $processo->getAtualizadoEm() ?? $processo->getCriadoEm();
            $x[] = $this->extrairFeatures($processo, $historicoArea, $fim);
            $y[] = \in_array($processo->getResultado(), [JuridicoProcesso::RESULTADO_PROCEDENTE, JuridicoProcesso::RESULTADO_ACORDO], true) ? 1 : 0;
        }

        if (\count(array_unique($y)) < 2) {
            // Todos os casos históricos tiveram o mesmo desfecho — não há o que aprender ainda.
            return null;
        }

        $treino = $this->treinarRegressaoLogistica($x, $y);

        return $treino + [
            'amostras' => \count($processos),
            'escritorios' => \count($empresasGrupo),
            'grupo' => \count($empresasGrupo) > 1,
            'historico_area' => $historicoArea,
        ];
    }

    /**
     * @param ModeloTreinado $modelo
     *
     * @return array{score: int, nivel: string, label: string, cor: string, fatores: list<FatorPrevisao>, modelo: string}
     */
    private function preverComModeloTreinado(JuridicoProcesso $processo, array $modelo): array
    {
        $features = $this->extrairFeatures($processo, $modelo['historico_area'], new \DateTimeImmutable());

        $z = $modelo['intercepto'];
        $fatores = [];
        foreach ($features as $j => $valor) {
            $desvio = $modelo['stds'][$j] > 0 ? $modelo['stds'][$j] : 1.0;
            $normalizado = ($valor - $modelo['means'][$j]) / $desvio;
            $contribuicao = $modelo['pesos'][$j] * $normalizado;
            $z += $contribuicao;

            $fatores[] = [
                'label' => self::FEATURE_LABELS[$j],
                'peso' => (int) round($contribuicao * 25),
                'favoravel' => $contribuicao >= 0,
                'detalhe' => sprintf('Coeficiente aprendido: %.2f (calibrado sobre %d caso(s) encerrado(s)%s).', $modelo['pesos'][$j], $modelo['amostras'], $modelo['grupo'] ? ' do grupo de escritórios' : ''),
            ];
        }

        $probabilidade = 1 / (1 + exp(-$z));
        $score = max(5, min(95, (int) round($probabilidade * 100)));

        array_unshift($fatores, [
            'label' => 'Modelo estatístico treinado',
            'peso' => 0,
            'favoravel' => true,
            'detalhe' => sprintf(
                'Regressão logística calibrada com %d caso(s) encerrado(s)%s — acurácia de %.1f%% sobre o histórico usado no treino.',
                $modelo['amostras'],
                $modelo['grupo'] ? (' de ' . $modelo['escritorios'] . ' escritório(s) do grupo') : '',
                $modelo['acuracia'],
            ),
        ]);

        return [
            'score' => $score,
            'nivel' => $this->nivel($score),
            'label' => $this->labelNivel($score),
            'cor' => $this->corNivel($score),
            'fatores' => $fatores,
            'modelo' => 'treinado',
        ];
    }

    /**
     * @param array<string, array{total: int, favoraveis: int, taxa: float}> $historicoArea
     *
     * @return list<float> [taxaHistoricaArea, faseOrdinal, tempoTramitacaoAnos, log10(valor)]
     */
    private function extrairFeatures(JuridicoProcesso $processo, array $historicoArea, \DateTimeImmutable $referenciaFim): array
    {
        $area = $processo->getArea() !== null && $processo->getArea() !== '' ? $processo->getArea() : 'geral';
        $dadosArea = $historicoArea[$area] ?? null;
        $taxaArea = $dadosArea !== null && $dadosArea['total'] >= 2 ? $dadosArea['taxa'] : 50.0;

        $faseMap = [
            JuridicoProcesso::FASE_CONHECIMENTO => 0.0,
            JuridicoProcesso::FASE_INSTRUCAO => 1.0,
            JuridicoProcesso::FASE_SENTENCA => 2.0,
            JuridicoProcesso::FASE_RECURSAL => -1.0,
            JuridicoProcesso::FASE_EXECUCAO => 3.0,
            JuridicoProcesso::FASE_ENCERRADO => 0.5,
        ];
        $faseNum = $faseMap[$processo->getFase()] ?? 0.0;

        $dias = $processo->getCriadoEm()->diff($referenciaFim)->days ?? 0;
        $tempoAnos = min(10.0, $dias / 365);

        $valor = $processo->getValor() !== null ? (float) $processo->getValor() : 0.0;
        $valorLog = $valor > 0 ? log10($valor + 1) : 0.0;

        return [$taxaArea, $faseNum, $tempoAnos, $valorLog];
    }

    /**
     * Regressão logística simples (gradiente descendente em lote, com regularização L2)
     * treinada em memória — suficiente para dezenas/centenas de casos por escritório,
     * sem depender de infraestrutura externa de ML.
     *
     * @param list<list<float>> $x
     * @param list<int>         $y
     *
     * @return array{pesos: list<float>, intercepto: float, means: list<float>, stds: list<float>, acuracia: float}
     */
    private function treinarRegressaoLogistica(array $x, array $y): array
    {
        $n = \count($x);
        $nFeatures = \count($x[0]);

        $means = array_fill(0, $nFeatures, 0.0);
        $stds = array_fill(0, $nFeatures, 1.0);
        for ($j = 0; $j < $nFeatures; ++$j) {
            $soma = 0.0;
            foreach ($x as $linha) {
                $soma += $linha[$j];
            }
            $means[$j] = $soma / $n;
        }
        for ($j = 0; $j < $nFeatures; ++$j) {
            $somaQuad = 0.0;
            foreach ($x as $linha) {
                $somaQuad += ($linha[$j] - $means[$j]) ** 2;
            }
            $variancia = $somaQuad / $n;
            $stds[$j] = $variancia > 1e-6 ? sqrt($variancia) : 1.0;
        }

        $xNorm = [];
        foreach ($x as $linha) {
            $normalizada = [];
            for ($j = 0; $j < $nFeatures; ++$j) {
                $normalizada[$j] = ($linha[$j] - $means[$j]) / $stds[$j];
            }
            $xNorm[] = $normalizada;
        }

        $pesos = array_fill(0, $nFeatures, 0.0);
        $intercepto = 0.0;
        $taxaAprendizado = 0.2;
        $lambda = 0.05;

        for ($iteracao = 0; $iteracao < 400; ++$iteracao) {
            $gradPesos = array_fill(0, $nFeatures, 0.0);
            $gradIntercepto = 0.0;

            for ($i = 0; $i < $n; ++$i) {
                $z = $intercepto;
                for ($j = 0; $j < $nFeatures; ++$j) {
                    $z += $pesos[$j] * $xNorm[$i][$j];
                }
                $predito = 1 / (1 + exp(-$z));
                $erro = $predito - $y[$i];

                for ($j = 0; $j < $nFeatures; ++$j) {
                    $gradPesos[$j] += $erro * $xNorm[$i][$j];
                }
                $gradIntercepto += $erro;
            }

            for ($j = 0; $j < $nFeatures; ++$j) {
                $pesos[$j] -= $taxaAprendizado * (($gradPesos[$j] / $n) + $lambda * $pesos[$j]);
            }
            $intercepto -= $taxaAprendizado * ($gradIntercepto / $n);
        }

        $acertos = 0;
        for ($i = 0; $i < $n; ++$i) {
            $z = $intercepto;
            for ($j = 0; $j < $nFeatures; ++$j) {
                $z += $pesos[$j] * $xNorm[$i][$j];
            }
            $predito = (1 / (1 + exp(-$z))) >= 0.5 ? 1 : 0;
            if ($predito === $y[$i]) {
                ++$acertos;
            }
        }

        return [
            'pesos' => $pesos,
            'intercepto' => $intercepto,
            'means' => $means,
            'stds' => $stds,
            'acuracia' => round(($acertos / $n) * 100, 1),
        ];
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorHistoricoArea(JuridicoProcesso $processo, Empresa $empresa, array &$fatores): int
    {
        $historico = $this->processoRepo->taxaExitoPorArea($empresa);
        $area = $processo->getArea() !== null && $processo->getArea() !== '' ? $processo->getArea() : 'geral';
        $dados = $historico[$area] ?? null;

        if ($dados === null || $dados['total'] < 3) {
            $fatores[] = [
                'label' => 'Histórico da área',
                'peso' => 0,
                'favoravel' => true,
                'detalhe' => 'Ainda não há histórico suficiente (mín. 3 casos encerrados) nesta área para influenciar o score.',
            ];

            return 0;
        }

        $delta = (int) round(($dados['taxa'] - 50) * 0.4);
        $fatores[] = [
            'label' => 'Histórico da área "' . $area . '"',
            'peso' => $delta,
            'favoravel' => $delta >= 0,
            'detalhe' => sprintf('%.1f%% de êxito em %d caso(s) encerrado(s) nesta área no escritório.', $dados['taxa'], $dados['total']),
        ];

        return $delta;
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorFase(JuridicoProcesso $processo, array &$fatores): int
    {
        $pesos = [
            JuridicoProcesso::FASE_CONHECIMENTO => 0,
            JuridicoProcesso::FASE_INSTRUCAO => 3,
            JuridicoProcesso::FASE_SENTENCA => 6,
            JuridicoProcesso::FASE_RECURSAL => -4,
            JuridicoProcesso::FASE_EXECUCAO => 12,
            JuridicoProcesso::FASE_ENCERRADO => 0,
        ];
        $labels = [
            JuridicoProcesso::FASE_CONHECIMENTO => 'fase de conhecimento (neutro)',
            JuridicoProcesso::FASE_INSTRUCAO => 'em instrução — provas favorecem avanço',
            JuridicoProcesso::FASE_SENTENCA => 'aguardando sentença — tese já consolidada nos autos',
            JuridicoProcesso::FASE_RECURSAL => 'em fase recursal — risco de reversão',
            JuridicoProcesso::FASE_EXECUCAO => 'em execução — mérito já decidido a favor',
            JuridicoProcesso::FASE_ENCERRADO => 'processo encerrado',
        ];
        $peso = $pesos[$processo->getFase()] ?? 0;

        $fatores[] = [
            'label' => 'Fase processual',
            'peso' => $peso,
            'favoravel' => $peso >= 0,
            'detalhe' => ucfirst($labels[$processo->getFase()] ?? $processo->getFase()) . '.',
        ];

        return $peso;
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorTarefas(JuridicoProcesso $processo, array &$fatores): int
    {
        $tarefas = $this->tarefaRepo->findForProcesso($processo);
        $atrasadas = 0;
        $agora = new \DateTimeImmutable();
        foreach ($tarefas as $tarefa) {
            if (!$tarefa->isConcluida() && $tarefa->getPrazo() !== null && $tarefa->getPrazo() < $agora) {
                ++$atrasadas;
            }
        }

        if ($atrasadas === 0) {
            $fatores[] = [
                'label' => 'Execução do caso',
                'peso' => 2,
                'favoravel' => true,
                'detalhe' => 'Nenhuma tarefa atrasada — acompanhamento em dia.',
            ];

            return 2;
        }

        $peso = -min(15, $atrasadas * 5);
        $fatores[] = [
            'label' => 'Execução do caso',
            'peso' => $peso,
            'favoravel' => false,
            'detalhe' => sprintf('%d tarefa(s) atrasada(s) — risco de perda de prazo ou prova.', $atrasadas),
        ];

        return $peso;
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorTempoTramitacao(JuridicoProcesso $processo, array &$fatores): int
    {
        $dias = $processo->getCriadoEm()->diff(new \DateTimeImmutable())->days ?? 0;
        $anos = $dias / 365;

        if ($anos <= 2) {
            $fatores[] = [
                'label' => 'Tempo de tramitação',
                'peso' => 1,
                'favoravel' => true,
                'detalhe' => sprintf('%.1f ano(s) de tramitação — dentro da média.', $anos),
            ];

            return 1;
        }

        $peso = (int) -min(8, round(($anos - 2) * 2));
        $fatores[] = [
            'label' => 'Tempo de tramitação',
            'peso' => $peso,
            'favoravel' => false,
            'detalhe' => sprintf('%.1f ano(s) de tramitação — processos muito longos tendem a perder força probatória.', $anos),
        ];

        return $peso;
    }

    /** @param list<FatorPrevisao> $fatores */
    private function fatorValorCausa(JuridicoProcesso $processo, array &$fatores): int
    {
        $valor = $processo->getValor() !== null ? (float) $processo->getValor() : 0.0;
        if ($valor <= 0) {
            return 0;
        }

        if ($valor >= 300000.0) {
            $fatores[] = [
                'label' => 'Complexidade pelo valor da causa',
                'peso' => -2,
                'favoravel' => false,
                'detalhe' => 'Valor elevado costuma atrair mais recursos da parte contrária.',
            ];

            return -2;
        }

        return 0;
    }

    private function nivel(int $score): string
    {
        return match (true) {
            $score >= 70 => 'alto',
            $score >= 45 => 'medio',
            default => 'baixo',
        };
    }

    private function labelNivel(int $score): string
    {
        return match ($this->nivel($score)) {
            'alto' => 'Probabilidade alta',
            'medio' => 'Probabilidade moderada',
            default => 'Probabilidade baixa',
        };
    }

    private function corNivel(int $score): string
    {
        return match ($this->nivel($score)) {
            'alto' => '#2fbf71',
            'medio' => '#e8a33d',
            default => '#e05260',
        };
    }
}
