<?php

namespace App\Service\Marketing;

/**
 * Conteúdo da landing Unio Jurídico (uniojuridico.uniowork.com.br).
 */
final class JuridicoLandingService
{
    /** @return list<array<string, mixed>> */
    public function features(): array
    {
        return [
            [
                'icon' => 'fa-gavel',
                'title' => 'Gestão de processos',
                'text' => 'Acompanhe fases, partes, varas e andamentos em um painel único do escritório.',
            ],
            [
                'icon' => 'fa-clock',
                'title' => 'Prazos inteligentes',
                'text' => 'Alertas de vencimento, audiências e diligências com visão de prioridade.',
            ],
            [
                'icon' => 'fa-users',
                'title' => 'Clientes e casos',
                'text' => 'Histórico, documentos e comunicação centralizados por cliente.',
            ],
            [
                'icon' => 'fa-robot',
                'title' => 'IA jurídica Sasha',
                'text' => 'Agente autônomo que trabalha 24/7 gerenciando prazos, pesquisando jurisprudência e analisando contratos.',
            ],
            [
                'icon' => 'fa-file-contract',
                'title' => 'Documentos e minutas',
                'text' => 'Modelos, petições e análise assistida para ganhar velocidade na produção.',
            ],
            [
                'icon' => 'fa-chart-line',
                'title' => 'Financeiro do escritório',
                'text' => 'Honorários, repasses e indicadores executivos no Pulso jurídico.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function autonomousFeatures(): array
    {
        return [
            [
                'icon' => 'fa-brain',
                'title' => 'Agente Autônomo 24/7',
                'text' => 'Sasha monitora prazos críticos, identifica publicações relevantes e envia alertas automáticos mesmo fora do horário comercial.',
                'status' => 'active',
            ],
            [
                'icon' => 'fa-magnifying-glass',
                'title' => 'Pesquisa Inteligente de Jurisprudência',
                'text' => 'Busca automática em STF, STJ e TRTs com resumos contextualizados e citações ABNT prontas.',
                'status' => 'active',
            ],
            [
                'icon' => 'fa-calculator',
                'title' => 'Cálculo Automático de Prazos CPC',
                'text' => 'Motor processual que considera feriados forenses, suspensões e prazos em dobro automaticamente.',
                'status' => 'active',
            ],
            [
                'icon' => 'fa-file-lines',
                'title' => 'Análise de Contratos',
                'text' => 'Identifica cláusulas críticas, riscos e pontos de atenção em contratos complexos.',
                'status' => 'active',
            ],
            [
                'icon' => 'fa-envelope',
                'title' => 'Triagem de Publicações DJe',
                'text' => 'Classifica automaticamente intimações, despachos e sentenças por ordem de urgência.',
                'status' => 'development',
            ],
            [
                'icon' => 'fa-wand-magic-sparkles',
                'title' => 'Geração Assistida de Petições',
                'text' => 'Cria minutas baseadas em templates do escritório com fundamentos sugeridos pela IA.',
                'status' => 'development',
            ],
            [
                'icon' => 'fa-building-columns',
                'title' => 'Integração com Tribunais',
                'text' => 'Consulta oficial de andamentos via DataJud (CNJ), base que agrega PJe, e-SAJ e Projudi.',
                'status' => 'development',
            ],
            [
                'icon' => 'fa-scale-unbalanced',
                'title' => 'Previsão de Êxito',
                'text' => 'Score heurístico e explicável de probabilidade de sucesso, baseado no histórico real da carteira.',
                'status' => 'active',
            ],
        ];
    }

    /** @return list<array{time: string, title: string, desc: string, icon: string}> */
    public function dailyRoutine(): array
    {
        return [
            [
                'time' => '08:00',
                'title' => 'Resumo Matinal Automático',
                'desc' => 'Sasha envia relatório com prazos do dia, novos andamentos e publicações relevantes.',
                'icon' => 'fa-sun',
            ],
            [
                'time' => '14:30',
                'title' => 'Alertas Contextualizados',
                'desc' => 'Notificações inteligentes sobre audiências, vencimentos e tarefas urgentes com um clique para ação.',
                'icon' => 'fa-bell',
            ],
            [
                'time' => '18:00',
                'title' => 'Consolidação Noturna',
                'desc' => 'Enquanto você descansa, Sasha organiza documentos, atualiza dashboards e prepara o dia seguinte.',
                'icon' => 'fa-moon',
            ],
        ];
    }

    /** @return list<array{before: string, after: string, category: string}> */
    public function beforeAfter(): array
    {
        return [
            [
                'before' => '3 horas/dia em planilhas de prazos',
                'after' => '10 minutos revisando alertas automáticos',
                'category' => 'Gestão de Prazos',
            ],
            [
                'before' => '45 minutos pesquisando jurisprudência',
                'after' => '5 minutos com resumos contextualizados',
                'category' => 'Pesquisa Jurídica',
            ],
            [
                'before' => '2 horas analisando contratos manualmente',
                'after' => '20 minutos revisando análise da IA',
                'category' => 'Due Diligence',
            ],
            [
                'before' => 'Risco de perder prazo por feriado esquecido',
                'after' => 'Motor CPC calcula automaticamente',
                'category' => 'Compliance Processual',
            ],
        ];
    }

    /** @return list<array{question: string, answer: string, demo: string}> */
    public function interactiveUseCases(): array
    {
        return [
            [
                'question' => 'Calcule o prazo para apelação considerando a data de publicação em 15/03/2026',
                'answer' => 'Prazo de 15 dias úteis. Vencimento: 07/04/2026 (considerando feriados e suspensões forenses da comarca).',
                'demo' => 'prazos',
            ],
            [
                'question' => 'Pesquise jurisprudência sobre dano moral por negativação indevida no STJ',
                'answer' => 'Encontrados 143 julgados relevantes. Tendência: R$ 8.000 a R$ 15.000. Súmula 385/STJ aplicável.',
                'demo' => 'jurisprudencia',
            ],
            [
                'question' => 'Analise as cláusulas críticas deste contrato de prestação de serviços',
                'answer' => 'Identificadas 3 cláusulas de risco alto: limitação de responsabilidade genérica, foro não negociado, multa desproporcional.',
                'demo' => 'contratos',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function modules(): array
    {
        return [
            ['label' => 'Pulso', 'desc' => 'Visão executiva do escritório', 'icon' => 'fa-gauge-high', 'live' => true],
            ['label' => 'Processos', 'desc' => 'Contencioso e andamentos', 'icon' => 'fa-scale-balanced', 'live' => true],
            ['label' => 'Prazos', 'desc' => 'Motor CPC e agenda fatal', 'icon' => 'fa-hourglass-half', 'live' => true],
            ['label' => 'CRM Jurídico', 'desc' => 'Clientes e carteira', 'icon' => 'fa-user-tie', 'live' => true],
            ['label' => 'GED & Documentos', 'desc' => 'Upload e organização', 'icon' => 'fa-folder-open', 'live' => true],
            ['label' => 'Honorários', 'desc' => 'Timesheet e OAB', 'icon' => 'fa-coins', 'live' => true],
            ['label' => 'Jurisprudência IA', 'desc' => 'Pesquisa com Sasha', 'icon' => 'fa-book-open', 'live' => true],
            ['label' => 'Contratos', 'desc' => 'Consultivo e riscos', 'icon' => 'fa-handshake'],
            ['label' => 'PJe & Tribunais', 'desc' => 'Consulta oficial via DataJud', 'icon' => 'fa-building-columns', 'live' => true],
            ['label' => 'Portal do Cliente', 'desc' => 'Transparência e self-service', 'icon' => 'fa-globe'],
            ['label' => 'Due Diligence', 'desc' => 'M&A e data room', 'icon' => 'fa-magnifying-glass-chart'],
            ['label' => 'Analytics', 'desc' => 'BI de carteira em produção', 'icon' => 'fa-chart-line', 'live' => true],
            ['label' => 'API Pública', 'desc' => 'Tokens e integrações externas', 'icon' => 'fa-plug-circle-bolt', 'live' => true],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public function stats(): array
    {
        return [
            ['value' => '24/7', 'label' => 'Copiloto IA disponível'],
            ['value' => '1', 'label' => 'Painel unificado'],
            ['value' => '100%', 'label' => 'Foco jurídico'],
        ];
    }

    /**
     * Métricas com contagem animada na landing (número-alvo + sufixo + rótulo + ícone).
     *
     * @return list<array{icon: string, target: float, suffix: string, label: string}>
     */
    public function metrics(): array
    {
        return [
            ['icon' => 'fa-hourglass-half', 'target' => 1200, 'suffix' => '+', 'label' => 'Prazos calculados pela IA'],
            ['icon' => 'fa-face-smile', 'target' => 98, 'suffix' => '%', 'label' => 'Satisfação da equipe interna'],
            ['icon' => 'fa-bolt', 'target' => 40, 'suffix' => '%', 'label' => 'Menos tempo em tarefas repetitivas'],
            ['icon' => 'fa-shield-halved', 'target' => 100, 'suffix' => '%', 'label' => 'Dados sob sua infraestrutura'],
        ];
    }

    /** @return array{quote: string, name: string, role: string} */
    public function testimonial(): array
    {
        return [
            'quote' => 'Antes eu perdia horas cruzando prazos em três planilhas diferentes. Hoje a Sasha me avisa antes, e o escritório inteiro trabalha no mesmo painel, sem mais surpresa em audiência.',
            'name' => 'Camila Andrade',
            'role' => 'Sócia-fundadora, escritório de contencioso cível',
        ];
    }

    /**
     * Passos do fluxo (como funciona).
     *
     * @return list<array{step: string, title: string, text: string}>
     */
    public function steps(): array
    {
        return [
            [
                'step' => '01',
                'title' => 'Organize o escritório',
                'text' => 'Cadastre clientes, processos e documentos no painel unificado.',
            ],
            [
                'step' => '02',
                'title' => 'Acompanhe prazos',
                'text' => 'Alertas e prioridades aparecem antes do vencimento crítico.',
            ],
            [
                'step' => '03',
                'title' => 'Conte com a Sasha',
                'text' => 'IA jurídica calcula prazos, resume peças e apoia a produção.',
            ],
        ];
    }

    /**
     * Pontos de confiança / segurança.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function trust(): array
    {
        return [
            [
                'icon' => 'fa-lock',
                'title' => 'Dados sob seu controle',
                'text' => 'Informações do escritório na sua infraestrutura, com acesso por perfil.',
            ],
            [
                'icon' => 'fa-users-gear',
                'title' => 'Equipe sincronizada',
                'text' => 'Advogados e equipe administrativa no mesmo fluxo operacional.',
            ],
            [
                'icon' => 'fa-headset',
                'title' => 'Copiloto sempre disponível',
                'text' => 'Sasha responde dúvidas jurídicas e operacionais dentro da plataforma.',
            ],
        ];
    }

    /**
     * Públicos / perfis de escritório.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function audiences(): array
    {
        return [
            [
                'icon' => 'fa-gavel',
                'title' => 'Contencioso',
                'text' => 'Prazos, audiências e andamentos com prioridade clara para a equipe.',
            ],
            [
                'icon' => 'fa-building',
                'title' => 'Consultivo e societário',
                'text' => 'Documentos, contratos e histórico do cliente sem pastas paralelas.',
            ],
            [
                'icon' => 'fa-briefcase',
                'title' => 'Escritórios em crescimento',
                'text' => 'Do sócio ao assistente: um painel, permissões e portal do cliente.',
            ],
        ];
    }

    /**
     * Rotina diária (prova de valor operacional).
     *
     * @return list<array{time: string, title: string, text: string}>
     */
    public function routine(): array
    {
        return [
            [
                'time' => 'Manhã',
                'title' => 'Pulso do escritório',
                'text' => 'Veja prazos do dia, audiências e o que está atrasado antes da primeira reunião.',
            ],
            [
                'time' => 'Tarde',
                'title' => 'Produção com a Sasha',
                'text' => 'Calcule prazo, peça um resumo de peça ou valide honorários sem abrir outra ferramenta.',
            ],
            [
                'time' => 'Fim do dia',
                'title' => 'Fechamento limpo',
                'text' => 'Atualize andamentos, anexe documentos e deixe o próximo dia já priorizado.',
            ],
        ];
    }

    /**
     * FAQ de negócio.
     *
     * @return list<array{q: string, a: string}>
     */
    public function faq(): array
    {
        return [
            [
                'q' => 'A Sasha substitui o advogado?',
                'a' => 'Não. Ela acelera tarefas repetitivas (prazos, resumos, consultas) e a decisão continua com o profissional.',
            ],
            [
                'q' => 'Preciso migrar tudo de uma vez?',
                'a' => 'Não. Dá para começar por clientes e prazos e ir expandindo processos, documentos e financeiro.',
            ],
            [
                'q' => 'Como fica o acesso da equipe?',
                'a' => 'Perfis e permissões controlam o que cada pessoa vê: sócio, advogado, assistente e financeiro.',
            ],
            [
                'q' => 'Os dados ficam onde?',
                'a' => 'Na infraestrutura da sua instalação Unio, com isolamento por escritório no copiloto de IA.',
            ],
        ];
    }
}
