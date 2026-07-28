<?php

namespace App\Service\Juridico;

/**
 * Camada determinística de "intenção" entre a mensagem do usuário e a Bruna.
 *
 * A Bruna (LLM via JurisFlow) responde em linguagem natural, mas nem toda pergunta
 * precisa de IA generativa — cálculo de prazo, honorários e leitura da carteira são
 * determinísticos e mais confiáveis feitos por código puro. Este serviço lê a mensagem
 * do usuário, decide se alguma ferramenta do escritório resolve o pedido e devolve uma
 * sugestão de ação (`tool` + `params`) para o front-end oferecer um botão "Executar" —
 * sem esperar (ou depender) do serviço de IA externo.
 *
 * @phpstan-type SuggestedAction array{tool: string, label: string, params: array<string, mixed>}
 */
final class LegalIntentDetector
{
    private const GATILHOS_PRAZO = [
        'prazo', 'contestação', 'contestacao', 'contestar', 'recurso', 'apelação', 'apelacao', 'apelar',
        'embargos', 'agravo', 'réplica', 'replica', 'contrarrazões', 'contrarrazoes', 'quando vence',
        'contar prazo', 'mandado de segurança', 'mandado de seguranca', 'impugnação', 'impugnacao',
    ];

    private const GATILHOS_HONORARIOS = [
        'honorário', 'honorario', 'honorários', 'honorarios', 'quanto cobrar', 'quanto cobro', 'orçamento de honorários',
    ];

    private const GATILHOS_CARTEIRA = [
        'carteira', 'saúde da carteira', 'saude da carteira', 'prioridades', 'processos críticos', 'processos criticos',
        'o que precisa de atenção', 'o que precisa de atencao', 'analise a carteira', 'analisar a carteira',
    ];

    private const GATILHOS_TAREFAS = [
        'tarefas', 'pendências', 'pendencias', 'atrasad', 'o que vence', 'o que está vencendo', 'o que esta vencendo',
        'diligências', 'diligencias',
    ];

    private const GATILHOS_PROCESSO = [
        'buscar processo', 'encontrar processo', 'localizar processo', 'processo do cliente', 'processo número',
        'processo numero', 'abrir processo',
    ];

    private const GATILHOS_JURISPRUDENCIA = [
        'jurisprudência', 'jurisprudencia', 'súmula', 'sumula', 'precedente', 'acórdão', 'acordao', 'julgado',
        'entendimento do stj', 'entendimento do stf', 'tese firmada',
    ];

    private const GATILHOS_CRIAR_TAREFA = [
        'criar tarefa', 'nova tarefa', 'cadastrar tarefa', 'adicionar tarefa', 'crie uma tarefa', 'crie a tarefa',
        'abra uma tarefa', 'anota uma tarefa', 'anote uma tarefa',
    ];

    private const GATILHOS_REGISTRAR_PRAZO = [
        'registrar prazo', 'registre esse prazo', 'registre um prazo', 'novo prazo', 'agendar prazo', 'agende um prazo',
        'anotar prazo', 'anote esse prazo', 'cadastrar prazo', 'marcar prazo', 'salvar prazo', 'coloca na agenda',
        'colocar na agenda', 'adiciona na agenda',
    ];

    private const TRIBUNAIS_CONHECIDOS = ['STF', 'STJ', 'TST', 'TSE', 'STM', 'TRF1', 'TRF2', 'TRF3', 'TRF4', 'TRF5', 'TRT', 'TJSP', 'TJRJ', 'TJMG'];

    /**
     * @return list<SuggestedAction>
     */
    public function detect(string $mensagem): array
    {
        $texto = mb_strtolower(trim($mensagem));
        if ($texto === '') {
            return [];
        }

        $acoes = [];

        if ($this->contemAlgum($texto, self::GATILHOS_CRIAR_TAREFA)) {
            $acoes[] = $this->detectarCriarTarefa($mensagem, $texto);
        } elseif ($this->contemAlgum($texto, self::GATILHOS_REGISTRAR_PRAZO)) {
            $acoes[] = $this->detectarRegistrarPrazo($mensagem, $texto);
        } elseif ($this->contemAlgum($texto, self::GATILHOS_PRAZO)) {
            $acao = $this->detectarPrazo($texto);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        }

        if ($this->contemAlgum($texto, self::GATILHOS_HONORARIOS)) {
            $acao = $this->detectarHonorarios($texto);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        }

        if ($this->contemAlgum($texto, self::GATILHOS_CARTEIRA)) {
            $acoes[] = ['tool' => 'analisar_carteira', 'label' => 'Analisar carteira agora', 'params' => []];
        }

        if ($this->contemAlgum($texto, self::GATILHOS_TAREFAS)) {
            $acoes[] = ['tool' => 'tarefas_urgentes', 'label' => 'Ver tarefas urgentes', 'params' => []];
        }

        if ($this->contemAlgum($texto, self::GATILHOS_PROCESSO)) {
            $acao = $this->detectarBuscaProcesso($mensagem, $texto);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        }

        if ($this->contemAlgum($texto, self::GATILHOS_JURISPRUDENCIA)) {
            $acoes[] = $this->detectarJurisprudencia($mensagem, $texto);
        }

        // Fallback independente de palavras-gatilho: um número de processo (CNJ) na
        // mensagem já é intenção suficiente para buscar, mesmo em frases não previstas
        // (ex.: "e o processo 1234567-89.2024.8.26.0100, como está?").
        if ($acoes === [] && preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagem)) {
            $acoes[] = $this->detectarBuscaProcesso($mensagem, $texto);
        }

        return \array_slice($this->deduplicar($acoes), 0, 2);
    }

    /** @return SuggestedAction|null */
    private function detectarPrazo(string $texto): ?array
    {
        $dataBase = $this->extrairData($texto) ?? (new \DateTimeImmutable('today'))->format('Y-m-d');
        $dias = $this->extrairDias($texto);

        if ($dias === null) {
            foreach (PrazoProcessualCalculator::prazosComuns() as $peca => $diasPeca) {
                if (str_contains($texto, $peca)) {
                    $dias = $diasPeca;
                    break;
                }
            }
        }

        $dias ??= 15;
        $tipo = str_contains($texto, 'corrido') ? PrazoProcessualCalculator::TIPO_CORRIDO : PrazoProcessualCalculator::TIPO_UTIL;
        $dobro = (bool) preg_match('/\bdobro\b|litiscons[oó]rcio|procuradores? (distintos|diferentes)/u', $texto);

        return [
            'tool' => 'calcular_prazo',
            'label' => sprintf('Calcular prazo (%d dias)', $dias),
            'params' => [
                'data_base' => $dataBase,
                'dias' => $dias,
                'tipo' => $tipo,
                'dobro' => $dobro,
            ],
        ];
    }

    /** @return SuggestedAction|null */
    private function detectarHonorarios(string $texto): ?array
    {
        $valor = $this->extrairValorMonetario($texto);
        if ($valor === null) {
            return null;
        }

        $percentual = 0.0;
        if (preg_match('/(\d{1,3}(?:[.,]\d+)?)\s*%\s*(?:de\s*)?[eê]xito/u', $texto, $m)) {
            $percentual = (float) str_replace(',', '.', $m[1]);
        }

        return [
            'tool' => 'calcular_honorarios',
            'label' => 'Calcular honorários estimados',
            'params' => ['valor_causa' => $valor, 'percentual_exito' => $percentual],
        ];
    }

    /** @return SuggestedAction */
    private function detectarBuscaProcesso(string $mensagemOriginal, string $texto): array
    {
        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagemOriginal, $m)) {
            return ['tool' => 'buscar_processo', 'label' => 'Buscar processo pelo número', 'params' => ['query' => $m[0]]];
        }

        $query = trim(preg_replace('/buscar processo|encontrar processo|localizar processo|processo do cliente|processo n[uú]mero|abrir processo/u', '', $texto) ?? '');

        return ['tool' => 'buscar_processo', 'label' => 'Buscar processo', 'params' => ['query' => $query]];
    }

    /** @return SuggestedAction */
    private function detectarJurisprudencia(string $mensagemOriginal, string $texto): array
    {
        $tribunal = 'Todos';
        foreach (self::TRIBUNAIS_CONHECIDOS as $t) {
            if (str_contains(mb_strtoupper($mensagemOriginal), $t)) {
                $tribunal = $t;
                break;
            }
        }

        $tema = preg_replace('/jurisprud[êe]ncia( sobre| do| da)?|s[uú]mula( sobre| do| da)?|precedente( sobre)?|ac[oó]rd[aã]o( sobre)?|julgado( sobre)?|pesquise|pesquisar|busque|buscar/u', '', $texto) ?? $texto;
        $tema = trim(preg_replace('/\s+/', ' ', $tema) ?? '');
        $tema = $tema !== '' ? $tema : 'tema relevante para o caso';

        return [
            'tool' => 'pesquisar_jurisprudencia',
            'label' => sprintf('Pesquisar jurisprudência%s', $tribunal !== 'Todos' ? ' no ' . $tribunal : ''),
            'params' => ['tema' => $tema, 'tribunal' => $tribunal],
        ];
    }

    /** @return SuggestedAction */
    private function detectarCriarTarefa(string $mensagemOriginal, string $texto): array
    {
        $params = [];

        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagemOriginal, $m)) {
            $params['numero_processo'] = $m[0];
        }

        $data = $this->extrairData($texto);
        if ($data !== null) {
            $params['prazo'] = $data;
        }

        $titulo = $this->extrairRestante($texto, [
            'criar tarefa', 'nova tarefa', 'cadastrar tarefa', 'adicionar tarefa', 'crie uma tarefa', 'crie a tarefa',
            'abra uma tarefa', 'anota uma tarefa', 'anote uma tarefa', 'no processo[^,]*', 'para o processo[^,]*',
            'vence(ndo)?\s*(em|dia)?', 'para\s*(hoje|amanh[ãa])?', 'no dia\s*',
            '\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}',
            '\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}',
        ]);
        if ($titulo !== '') {
            $params['titulo'] = $titulo;
        }

        $label = 'Criar tarefa' . (isset($params['titulo']) ? ': ' . $params['titulo'] : '');

        return ['tool' => 'criar_tarefa', 'label' => $label, 'params' => $params];
    }

    /** @return SuggestedAction */
    private function detectarRegistrarPrazo(string $mensagemOriginal, string $texto): array
    {
        $params = [];

        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagemOriginal, $m)) {
            $params['numero_processo'] = $m[0];
        }

        $data = $this->extrairData($texto);
        if ($data !== null) {
            $params['data_limite'] = $data;
        }

        $tipo = $this->extrairRestante($texto, [
            'registrar prazo', 'registre esse prazo', 'registre um prazo', 'novo prazo', 'agendar prazo', 'agende um prazo',
            'anotar prazo', 'anote esse prazo', 'cadastrar prazo', 'marcar prazo', 'salvar prazo', 'coloca(r)? na agenda',
            'adiciona(r)? na agenda', 'no processo[^,]*', 'para\s*(hoje|amanh[ãa])?', 'no dia\s*', 'em\s*$',
            '\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}',
            '\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}',
        ]);
        if ($tipo !== '') {
            $params['tipo'] = $tipo;
        }

        $label = 'Registrar prazo' . (isset($params['tipo']) ? ': ' . $params['tipo'] : '');

        return ['tool' => 'registrar_prazo', 'label' => $label, 'params' => $params];
    }

    /**
     * Remove os gatilhos/ruído reconhecidos do texto e devolve o que sobra, com a
     * primeira letra de cada palavra em maiúscula — útil pra virar título/tipo.
     *
     * @param list<string> $padroes
     */
    private function extrairRestante(string $texto, array $padroes): string
    {
        $limpo = preg_replace('/' . implode('|', $padroes) . '/u', ' ', $texto) ?? $texto;
        $limpo = trim(preg_replace('/\s+/', ' ', $limpo) ?? '');
        $limpo = trim($limpo, " ,.:-");

        return $limpo !== '' ? mb_convert_case($limpo, \MB_CASE_TITLE, 'UTF-8') : '';
    }

    private function extrairData(string $texto): ?string
    {
        // Só reconhece o formato DD/MM/AAAA (com barra) de propósito: números de processo
        // CNJ usam "-" e "." como separadores e colidiriam com um padrão mais permissivo.
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4}|\d{2})/', $texto, $m)) {
            $ano = \strlen($m[3]) === 2 ? '20' . $m[3] : $m[3];
            $data = \DateTimeImmutable::createFromFormat('Y-n-j', sprintf('%s-%d-%d', $ano, (int) $m[2], (int) $m[1]));

            return $data ? $data->format('Y-m-d') : null;
        }

        if (str_contains($texto, 'hoje')) {
            return (new \DateTimeImmutable('today'))->format('Y-m-d');
        }

        if (str_contains($texto, 'amanhã') || str_contains($texto, 'amanha')) {
            return (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        }

        return null;
    }

    private function extrairDias(string $texto): ?int
    {
        if (preg_match('/(\d{1,3})\s*dias?/u', $texto, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function extrairValorMonetario(string $texto): ?float
    {
        if (preg_match('/r\$\s*([\d.,]+)/u', $texto, $m)) {
            return $this->normalizarMoeda($m[1]);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*mil/u', $texto, $m)) {
            return ((float) str_replace(',', '.', $m[1])) * 1000;
        }

        if (preg_match('/valor da causa[^\d]*(\d[\d.,]*)/u', $texto, $m)) {
            return $this->normalizarMoeda($m[1]);
        }

        return null;
    }

    private function normalizarMoeda(string $valor): float
    {
        $valor = trim($valor);
        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } elseif (str_contains($valor, ',')) {
            $valor = str_replace(',', '.', $valor);
        }

        return (float) $valor;
    }

    /** @param list<string> $gatilhos */
    private function contemAlgum(string $texto, array $gatilhos): bool
    {
        foreach ($gatilhos as $g) {
            if (str_contains($texto, $g)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SuggestedAction> $acoes
     * @return list<SuggestedAction>
     */
    private function deduplicar(array $acoes): array
    {
        $vistos = [];
        $out = [];
        foreach ($acoes as $acao) {
            if (isset($vistos[$acao['tool']])) {
                continue;
            }
            $vistos[$acao['tool']] = true;
            $out[] = $acao;
        }

        return $out;
    }
}
