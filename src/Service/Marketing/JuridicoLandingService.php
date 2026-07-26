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
                'title' => 'IA jurídica Bruna',
                'text' => 'Prazos processuais, jurisprudência, contratos e honorários com copiloto dedicado.',
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
    public function modules(): array
    {
        return [
            ['label' => 'Pulso', 'desc' => 'Visão executiva do escritório', 'icon' => 'fa-gauge-high'],
            ['label' => 'Processos', 'desc' => 'Contencioso e andamentos', 'icon' => 'fa-scale-balanced'],
            ['label' => 'Prazos', 'desc' => 'Motor CPC e agenda fatal', 'icon' => 'fa-hourglass-half'],
            ['label' => 'CRM Jurídico', 'desc' => 'Clientes e carteira', 'icon' => 'fa-user-tie'],
            ['label' => 'GED & Petições', 'desc' => 'Documentos e minutas', 'icon' => 'fa-folder-open'],
            ['label' => 'Contratos', 'desc' => 'Consultivo e riscos', 'icon' => 'fa-handshake'],
            ['label' => 'Honorários', 'desc' => 'Timesheet e OAB', 'icon' => 'fa-coins'],
            ['label' => 'Jurisprudência IA', 'desc' => 'Pesquisa com Bruna', 'icon' => 'fa-book-open'],
            ['label' => 'PJe & Tribunais', 'desc' => 'Integração oficial', 'icon' => 'fa-building-columns'],
            ['label' => 'Portal do Cliente', 'desc' => 'Transparência e self-service', 'icon' => 'fa-globe'],
            ['label' => 'Due Diligence', 'desc' => 'M&A e data room', 'icon' => 'fa-magnifying-glass-chart'],
            ['label' => 'Analytics', 'desc' => 'BI e produtividade', 'icon' => 'fa-chart-line'],
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
            'quote' => 'Antes eu perdia horas cruzando prazos em três planilhas diferentes. Hoje a Bruna me avisa antes, e o escritório inteiro trabalha no mesmo painel, sem mais surpresa em audiência.',
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
                'title' => 'Conte com a Bruna',
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
                'text' => 'Bruna responde dúvidas jurídicas e operacionais dentro da plataforma.',
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
                'title' => 'Produção com a Bruna',
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
                'q' => 'A Bruna substitui o advogado?',
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
