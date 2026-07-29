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

    private const GATILHOS_LISTAR_PRAZOS = [
        'liste todos os prazos', 'listar todos os prazos', 'liste os prazos', 'listar os prazos',
        'todos os prazos pendentes', 'quais são todos os prazos', 'quais sao todos os prazos',
        'prazos em lote', 'prazos de todos os processos', 'liste meus prazos', 'listar meus prazos',
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

    private const GATILHOS_PREVISAO = [
        'previsão de êxito', 'previsao de exito', 'chance de êxito', 'chance de exito', 'probabilidade de êxito',
        'probabilidade de exito', 'vou ganhar', 'chance de ganhar', 'vale a pena recorrer', 'score de êxito', 'score de exito',
    ];

    private const GATILHOS_DATAJUD = [
        'andamento oficial', 'consultar no datajud', 'buscar no datajud', 'andamento no tribunal',
        'movimentação oficial', 'movimentacao oficial', 'consultar tribunal', 'andamento do pje', 'andamento do e-saj',
        'andamento do esaj', 'andamento do projudi', 'consultar pje', 'consultar e-saj', 'consultar projudi',
    ];

    private const GATILHOS_RESUMIR = [
        'resuma', 'resumir', 'resumo do documento', 'resumo desse documento', 'resumo deste documento',
        'faça um resumo', 'faca um resumo', 'me dê um resumo', 'me de um resumo',
    ];

    private const GATILHOS_ANALISAR_CONTRATO = [
        'analise este contrato', 'analise esse contrato', 'analisar contrato', 'analisar este contrato',
        'analisar esse contrato', 'análise de risco do contrato', 'analise de risco do contrato',
        'analise as cláusulas', 'analise as clausulas', 'revise este contrato', 'revise esse contrato',
    ];

    private const GATILHOS_PECAS_SIMILARES = [
        'peças parecidas', 'pecas parecidas', 'peças similares', 'pecas similares',
        'sugerir peças', 'sugerir pecas', 'sugira peças', 'sugira pecas',
        'algo parecido na biblioteca', 'já tem peça parecida', 'ja tem peca parecida',
        'modelo parecido', 'peça modelo para', 'peca modelo para', 'busque na biblioteca',
    ];

    private const GATILHOS_COMPARAR_DOCUMENTOS = [
        'compare estes documentos', 'compare esses documentos', 'compare estes dois documentos',
        'compare esses dois documentos', 'comparar documentos', 'compare este documento com',
        'compare esse documento com', 'diferenças entre os documentos', 'diferencas entre os documentos',
        'diferenças entre as duas versões', 'diferencas entre as duas versoes', 'compare as duas versões',
        'compare as duas versoes', 'compare a petição com', 'compare a peticao com', 'compare o contrato com',
    ];

    private const GATILHOS_ANALISAR_SENTENCA = [
        'analise esta sentença', 'analise essa sentença', 'analise esta sentenca', 'analise essa sentenca',
        'analisar sentença', 'analisar sentenca', 'chances de recurso', 'chance de recurso',
        'vale a pena recorrer desta sentença', 'vale a pena recorrer dessa sentença',
        'pontos fracos da sentença', 'pontos fracos da sentenca', 'teses recursais',
    ];

    private const GATILHOS_GERAR_MINUTA = [
        'gere uma minuta', 'gerar minuta', 'gere uma petição', 'gere uma peticao', 'gerar petição', 'gerar peticao',
        'gere uma contestação', 'gere uma contestacao', 'gerar contestação', 'gerar contestacao',
        'redija uma petição', 'redija uma peticao', 'redija uma contestação', 'redija uma contestacao',
        'elabore uma procuração', 'elabore uma procuracao', 'gerar procuração', 'gerar procuracao',
        'escreva uma petição', 'escreva uma peticao', 'escreva uma minuta',
    ];

    /** Tamanho mínimo do texto restante para considerar que há conteúdo real colado (evita falso positivo). */
    private const MIN_LEN_TEXTO_COLADO = 25;

    /**
     * @param string|null $numeroProcessoAtual Número do processo aberto na tela atual
     *                                          (contexto injetado pelo front-end). Usado
     *                                          como fallback quando a ação detectada
     *                                          precisa de um número e a mensagem não traz
     *                                          um explícito (ex.: "como está esse processo?").
     *
     * @return list<SuggestedAction>
     */
    public function detect(string $mensagem, ?string $numeroProcessoAtual = null): array
    {
        $texto = mb_strtolower(trim($mensagem));
        if ($texto === '') {
            return [];
        }

        $numeroProcessoAtual = $numeroProcessoAtual !== null && trim($numeroProcessoAtual) !== '' ? trim($numeroProcessoAtual) : null;

        $acoes = [];

        if ($this->contemAlgum($texto, self::GATILHOS_CRIAR_TAREFA)) {
            $acoes[] = $this->detectarCriarTarefa($mensagem, $texto);
        } elseif ($this->contemAlgum($texto, self::GATILHOS_REGISTRAR_PRAZO)) {
            $acoes[] = $this->detectarRegistrarPrazo($mensagem, $texto);
        } elseif ($this->contemAlgum($texto, self::GATILHOS_LISTAR_PRAZOS)) {
            // Checado antes de GATILHOS_PRAZO: frases como "liste todos os prazos"
            // contêm a substring "prazo" e cairiam erradamente no cálculo de prazo
            // processual (calcular_prazo) se checadas depois.
            $acoes[] = $this->detectarListarPrazos($mensagem, $texto);
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
            $acao = $this->detectarBuscaProcesso($mensagem, $texto, $numeroProcessoAtual);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        }

        if ($this->contemAlgum($texto, self::GATILHOS_JURISPRUDENCIA)) {
            $acoes[] = $this->detectarJurisprudencia($mensagem, $texto);
        }

        if ($this->contemAlgum($texto, self::GATILHOS_PREVISAO)) {
            $acao = $this->detectarPrevisaoExito($mensagem, $texto, $numeroProcessoAtual);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        }

        if ($this->contemAlgum($texto, self::GATILHOS_DATAJUD)) {
            $acao = $this->detectarConsultaDatajud($mensagem, $numeroProcessoAtual);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        }

        $anexos = $this->extrairAnexos($mensagem);
        if ($anexos !== []) {
            if ($this->contemAlgum($texto, self::GATILHOS_COMPARAR_DOCUMENTOS) && \count($anexos) >= 2) {
                $acao = $this->detectarCompararDocumentos($mensagem);
                if ($acao !== null) {
                    $acoes[] = $acao;
                }
            } elseif ($this->contemAlgum($texto, self::GATILHOS_ANALISAR_SENTENCA)) {
                $acao = $this->detectarAnalisarSentenca($mensagem, $texto);
                if ($acao !== null) {
                    $acoes[] = $acao;
                }
            } elseif ($this->contemAlgum($texto, self::GATILHOS_ANALISAR_CONTRATO)) {
                $acao = $this->detectarAnalisarContrato($mensagem, $texto);
                if ($acao !== null) {
                    $acoes[] = $acao;
                }
            } elseif ($this->contemAlgum($texto, self::GATILHOS_RESUMIR) || $this->contemAlgum($texto, ['anexe', 'anexado', 'anexados', 'documento anexado', 'documentos anexados'])) {
                $acao = $this->detectarResumir($mensagem, $texto);
                if ($acao !== null) {
                    $acoes[] = $acao;
                }
            }
        }

        if ($this->contemAlgum($texto, self::GATILHOS_PECAS_SIMILARES)) {
            $acao = $this->detectarPecasSimilares($mensagem, $texto);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        }

        if ($this->contemAlgum($texto, self::GATILHOS_GERAR_MINUTA)) {
            $acao = $this->detectarGerarMinuta($mensagem, $texto);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        } elseif ($this->contemAlgum($texto, self::GATILHOS_COMPARAR_DOCUMENTOS)) {
            $acao = $this->detectarCompararDocumentos($mensagem);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        } elseif ($this->contemAlgum($texto, self::GATILHOS_ANALISAR_SENTENCA)) {
            $acao = $this->detectarAnalisarSentenca($mensagem, $texto);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        } elseif ($this->contemAlgum($texto, self::GATILHOS_ANALISAR_CONTRATO)) {
            $acao = $this->detectarAnalisarContrato($mensagem, $texto);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
        } elseif ($this->contemAlgum($texto, self::GATILHOS_RESUMIR)) {
            $acao = $this->detectarResumir($mensagem, $texto);
            if ($acao !== null) {
                $acoes[] = $acao;
            }
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

    /** @return SuggestedAction */
    private function detectarListarPrazos(string $mensagemOriginal, string $texto): array
    {
        $params = [];

        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagemOriginal, $m)) {
            $params['numero_processo'] = $m[0];
        } elseif (preg_match('/(?:do cliente|da cliente|de|para)\s+([a-zà-ú][\wà-ú\s]{2,60}?)(?:\s*[,.?!]|$)/ui', $texto, $m)) {
            $candidato = trim($m[1]);
            // Evita capturar ruído comum tipo "de todos os processos", "para hoje".
            if (!\in_array($candidato, ['todos os processos', 'todos', 'hoje', 'amanhã', 'amanha'], true) && mb_strlen($candidato) >= 3) {
                $params['cliente'] = $candidato;
            }
        }

        $label = 'Listar prazos pendentes' . (isset($params['cliente']) ? ' de ' . $params['cliente'] : (isset($params['numero_processo']) ? ' do processo' : ' (toda a carteira)'));

        return ['tool' => 'listar_prazos', 'label' => $label, 'params' => $params];
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
    private function detectarBuscaProcesso(string $mensagemOriginal, string $texto, ?string $numeroProcessoAtual = null): array
    {
        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagemOriginal, $m)) {
            return ['tool' => 'buscar_processo', 'label' => 'Buscar processo pelo número', 'params' => ['query' => $m[0]]];
        }

        $query = trim(preg_replace('/buscar processo|encontrar processo|localizar processo|processo do cliente|processo n[uú]mero|abrir processo/u', '', $texto) ?? '');

        if ($query === '' && $numeroProcessoAtual !== null) {
            return ['tool' => 'buscar_processo', 'label' => 'Buscar processo desta tela', 'params' => ['query' => $numeroProcessoAtual]];
        }

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

    /** @return SuggestedAction|null */
    private function detectarPrevisaoExito(string $mensagemOriginal, string $texto, ?string $numeroProcessoAtual = null): ?array
    {
        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagemOriginal, $m)) {
            return ['tool' => 'prever_exito', 'label' => 'Ver previsão de êxito', 'params' => ['query' => $m[0]]];
        }

        $query = trim($this->extrairRestante($texto, self::GATILHOS_PREVISAO));
        if ($query !== '') {
            return ['tool' => 'prever_exito', 'label' => 'Ver previsão de êxito', 'params' => ['query' => $query]];
        }

        if ($numeroProcessoAtual !== null) {
            return ['tool' => 'prever_exito', 'label' => 'Ver previsão de êxito desta tela', 'params' => ['query' => $numeroProcessoAtual]];
        }

        return null;
    }

    public function isConsultaDatajudSemNumero(string $mensagem, ?string $numeroProcessoAtual = null): bool
    {
        $texto = mb_strtolower(trim($mensagem));
        if ($texto === '' || !$this->contemAlgum($texto, self::GATILHOS_DATAJUD)) {
            return false;
        }

        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagem)) {
            return false;
        }

        return $numeroProcessoAtual === null || trim($numeroProcessoAtual) === '';
    }

    /** @return SuggestedAction|null */
    private function detectarConsultaDatajud(string $mensagemOriginal, ?string $numeroProcessoAtual = null): ?array
    {
        if (preg_match('/\d{7}-?\d{2}\.?\d{4}\.?\d\.?\d{2}\.?\d{4}/', $mensagemOriginal, $m)) {
            return ['tool' => 'consultar_datajud', 'label' => 'Consultar andamento oficial (DataJud)', 'params' => ['numero' => $m[0]]];
        }

        if ($numeroProcessoAtual !== null) {
            return ['tool' => 'consultar_datajud', 'label' => 'Consultar andamento oficial (DataJud) desta tela', 'params' => ['numero' => $numeroProcessoAtual]];
        }

        return null;
    }

    /**
     * Resumir/analisar/gerar minuta exigem texto colado pelo usuário — só disparam
     * quando sobra conteúdo substancial após remover o gatilho, senão a mensagem cai
     * no chat normal (que pede pra colar o texto).
     */
    /** @return SuggestedAction|null */
    private function detectarResumir(string $mensagemOriginal, string $texto): ?array
    {
        $anexos = $this->extrairAnexos($mensagemOriginal);
        if ($anexos !== []) {
            return ['tool' => 'resumir_documento', 'label' => 'Resumir documento', 'params' => ['texto' => $anexos[0]['texto']]];
        }

        $conteudo = trim($this->removerGatilhos($mensagemOriginal, self::GATILHOS_RESUMIR));
        if (mb_strlen($conteudo) < self::MIN_LEN_TEXTO_COLADO) {
            return null;
        }

        return ['tool' => 'resumir_documento', 'label' => 'Resumir documento', 'params' => ['texto' => $conteudo]];
    }

    /** @return SuggestedAction|null */
    private function detectarAnalisarContrato(string $mensagemOriginal, string $texto): ?array
    {
        $anexos = $this->extrairAnexos($mensagemOriginal);
        if ($anexos !== []) {
            return ['tool' => 'analisar_contrato', 'label' => 'Analisar contrato', 'params' => ['texto' => $anexos[0]['texto']]];
        }

        $conteudo = trim($this->removerGatilhos($mensagemOriginal, self::GATILHOS_ANALISAR_CONTRATO));
        if (mb_strlen($conteudo) < self::MIN_LEN_TEXTO_COLADO) {
            return null;
        }

        return ['tool' => 'analisar_contrato', 'label' => 'Analisar contrato', 'params' => ['texto' => $conteudo]];
    }

    /**
     * "Comparar documentos" depende de exatamente 2 anexos na mensagem (formato
     * `[Anexo: nome]\n<texto>` gerado pelo upload do chat). Se não houver pelo
     * menos 1 anexo reconhecível, não dispara — vira mensagem normal no chat.
     *
     * @return SuggestedAction|null
     */
    private function detectarCompararDocumentos(string $mensagemOriginal): ?array
    {
        $anexos = $this->extrairAnexos($mensagemOriginal);
        if ($anexos === []) {
            return null;
        }

        $params = [
            'documento_a' => $anexos[0]['texto'] ?? '',
            'nome_a' => $anexos[0]['nome'] ?? 'Documento A',
            'documento_b' => $anexos[1]['texto'] ?? '',
            'nome_b' => $anexos[1]['nome'] ?? 'Documento B',
        ];

        return ['tool' => 'comparar_documentos', 'label' => 'Comparar documentos', 'params' => $params];
    }

    /**
     * Extrai os blocos `[Anexo: nome.ext]\n<texto>` anexados pelo upload do chat.
     *
     * @return list<array{nome: string, texto: string}>
     */
    private function extrairAnexos(string $mensagemOriginal): array
    {
        if (!preg_match_all('/\[Anexo:\s*([^\]]+?)\]\r?\n(.*?)(?=(?:\r?\n\r?\n\[Anexo:)|\z)/su', $mensagemOriginal, $matches, \PREG_SET_ORDER)) {
            return [];
        }

        return array_map(
            static fn (array $m) => ['nome' => trim($m[1]), 'texto' => trim($m[2])],
            $matches,
        );
    }

    /** @return SuggestedAction|null */
    private function detectarPecasSimilares(string $mensagemOriginal, string $texto): ?array
    {
        $descricao = trim($this->removerGatilhos($mensagemOriginal, self::GATILHOS_PECAS_SIMILARES));
        if (mb_strlen($descricao) < 5) {
            return null;
        }

        return ['tool' => 'sugerir_pecas_similares', 'label' => 'Sugerir peças similares', 'params' => ['descricao' => $descricao]];
    }

    /** @return SuggestedAction|null */
    private function detectarAnalisarSentenca(string $mensagemOriginal, string $texto): ?array
    {
        $anexos = $this->extrairAnexos($mensagemOriginal);
        if ($anexos !== []) {
            return ['tool' => 'analisar_sentenca', 'label' => 'Analisar sentença', 'params' => ['texto' => $anexos[0]['texto']]];
        }

        $conteudo = trim($this->removerGatilhos($mensagemOriginal, self::GATILHOS_ANALISAR_SENTENCA));
        if (mb_strlen($conteudo) < self::MIN_LEN_TEXTO_COLADO) {
            return null;
        }

        return ['tool' => 'analisar_sentenca', 'label' => 'Analisar sentença', 'params' => ['texto' => $conteudo]];
    }

    /** @return SuggestedAction|null */
    private function detectarGerarMinuta(string $mensagemOriginal, string $texto): ?array
    {
        $tipo = 'petição';
        foreach (['contestação', 'contestacao', 'procuração', 'procuracao', 'petição', 'peticao', 'contrato', 'minuta'] as $candidato) {
            if (str_contains($texto, $candidato)) {
                $tipo = str_starts_with($candidato, 'peticao') || $candidato === 'petição' ? 'petição' : $candidato;
                break;
            }
        }

        $descricao = trim($this->removerGatilhos($mensagemOriginal, self::GATILHOS_GERAR_MINUTA));
        if (mb_strlen($descricao) < self::MIN_LEN_TEXTO_COLADO) {
            return null;
        }

        return [
            'tool' => 'gerar_minuta',
            'label' => sprintf('Gerar minuta (%s)', $tipo),
            'params' => ['tipo' => $tipo, 'descricao' => $descricao],
        ];
    }

    /** @param list<string> $gatilhos */
    private function removerGatilhos(string $mensagemOriginal, array $gatilhos): string
    {
        $padroes = array_map(static fn (string $g) => preg_quote($g, '/'), $gatilhos);
        $limpo = preg_replace('/' . implode('|', $padroes) . '/ui', ' ', $mensagemOriginal) ?? $mensagemOriginal;

        return trim(preg_replace('/\s+/', ' ', $limpo) ?? '', " ,.:-");
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
