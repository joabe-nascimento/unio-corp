<?php

namespace App\Config;

/**
 * Catálogo de módulos Unio Jurídico (em desenvolvimento — roadmap de produto).
 *
 * @phpstan-type JuridicoModule array{
 *     slug: string,
 *     section: string,
 *     label: string,
 *     icon: string,
 *     subtitle: string,
 *     tagline: string,
 *     status: 'planned'|'alpha'|'beta',
 *     empty_title: string,
 *     empty_text: string,
 *     features: list<string>,
 *     capabilities: list<array{icon: string, title: string, text: string}>,
 *     roadmap: list<array{phase: string, title: string, items: list<string>}>,
 *     integrations: list<string>,
 *     bruna_prompt: string,
 *     metrics: list<array{label: string, value: string}>
 * }
 */
final class JuridicoModuleRegistry
{
  public const SECTIONS = [
        'contencioso' => 'Contencioso & Processual',
        'relacionamento' => 'Clientes & Relacionamento',
        'producao' => 'Produção & Documentos',
        'consultivo' => 'Consultivo & Contratos',
        'financeiro' => 'Financeiro do Escritório',
        'inteligencia' => 'Inteligência Jurídica',
        'governanca' => 'Governança & Ética',
    ];

    /** @var list<JuridicoModule> */
    public const MODULES = [
        [
            'slug' => 'processos',
            'section' => 'contencioso',
            'label' => 'Processos',
            'icon' => 'fa-scale-balanced',
            'subtitle' => 'Carteira, KPIs e gestão',
            'tagline' => 'Painel executivo de processos com 4 KPIs dinâmicos, gestão por status/fase e copiloto Sasha integrado.',
            'status' => 'beta',
            'empty_title' => 'Gerencie sua carteira de processos',
            'empty_text' => 'Painel com KPIs de carteira (ativos, críticos, valor e taxa de êxito), cadastro estruturado por área/fase/tribunal e filtros inteligentes — com a Sasha para análise da saúde da carteira.',
            'features' => [
                'Banner dinâmico com saudação e alerta de processos críticos',
                '4 KPIs clicáveis: ativos, críticos (com alerta pulsante), valor em carteira e taxa de êxito',
                'Cadastro completo: número CNJ, tribunal, área, fase, cliente, responsável e valor',
                'Filtros por status (ativo, crítico, encerrado) e busca por número/cliente/área',
                'Kanban de fases com drag-and-drop para mover processos entre etapas',
                'Tarefas por processo com prazo, responsável e alerta de atraso',
                'Cadastro de partes e representação (autor, réu, terceiros, advogados e OAB)',
                'Central de alertas de risco calculada em tempo real (críticos, tarefas atrasadas, sem movimentação)',
                'Botão "Perguntar à Sasha" para análise de carteira e sugestão de prioridades',
            ],
            'capabilities' => [
                ['icon' => 'fa-gauge-high', 'title' => 'KPIs executivos', 'text' => 'Painel com 4 indicadores-chave: processos ativos, críticos, valor em carteira e taxa de êxito real.'],
                ['icon' => 'fa-table-columns', 'title' => 'Kanban de fases', 'text' => 'Arraste processos entre conhecimento, instrução, sentença, recursal e execução.'],
                ['icon' => 'fa-list-check', 'title' => 'Tarefas e partes', 'text' => 'Checklist por processo com prazos e responsáveis, além do cadastro de partes e representação.'],
                ['icon' => 'fa-shield-halved', 'title' => 'Alertas de risco', 'text' => 'Motor que cruza status, tarefas e tempo sem atualização para apontar o que precisa de atenção.'],
                ['icon' => 'fa-robot', 'title' => 'Sasha integrada', 'text' => 'Botão dedicado para análise de carteira e sugestão de prioridades com base em prazos e riscos.'],
                ['icon' => 'fa-filter', 'title' => 'Filtros inteligentes', 'text' => 'Busca por número/cliente/área + filtro de status, com contagem dinâmica de críticos na tabela.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'Fundação ✅', 'items' => ['CRUD processos ✅', 'Dashboard 4 KPIs ✅', 'Filtros e busca ✅', 'Integração Sasha ✅']],
                ['phase' => 'Q4', 'title' => 'Operação avançada ✅', 'items' => ['Kanban de fases ✅', 'Tarefas por processo ✅', 'Alertas de risco ✅', 'Partes e representação ✅']],
                ['phase' => '2027', 'title' => 'Enterprise ✅', 'items' => ['Multi-escritório (grupo matriz/filial) ✅', 'API pública com tokens ✅', 'BI de carteira ✅', 'DataJud — PJe/e-SAJ/Projudi ✅']],
            ],
            'integrations' => ['JurisFlow / Sasha', 'DataJud (CNJ)', 'API Pública'],
            'bruna_prompt' => 'Analise a saúde da minha carteira de processos e sugira prioridades para esta semana.',
            'metrics' => [
                ['label' => 'Processos ativos', 'value' => '—'],
                ['label' => 'Críticos', 'value' => '—'],
                ['label' => 'Valor em carteira', 'value' => '—'],
                ['label' => 'Taxa de êxito', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'prazos',
            'section' => 'contencioso',
            'label' => 'Prazos & Diligências',
            'icon' => 'fa-hourglass-half',
            'subtitle' => 'Agenda fatal e prioridades',
            'tagline' => 'Motor de prazos processuais com contagem em dias úteis, feriados forenses e alertas em cascata.',
            'status' => 'alpha',
            'empty_title' => 'Prazos inteligentes em desenvolvimento',
            'empty_text' => 'Cálculo CPC, feriados, suspensão de prazos, prazos em dobro e fila de prioridade para a equipe.',
            'features' => [
                'Cálculo automático CPC/CPP com feriados e suspensões',
                'Prazos fatais, internos e de diligência',
                'Fila crítica com semáforo de risco',
                'Lembretes WhatsApp e e-mail por perfil',
                'Histórico auditável de prorrogações e justificativas',
            ],
            'capabilities' => [
                ['icon' => 'fa-calculator', 'title' => 'Sasha calcula prazos', 'text' => 'IA jurídica com tabela de prazos e jurisprudência de contagem.'],
                ['icon' => 'fa-bell', 'title' => 'Alertas em camadas', 'text' => 'D-7, D-3, D-1 e vencido — com escalonamento para o sócio.'],
                ['icon' => 'fa-calendar-check', 'title' => 'Agenda unificada', 'text' => 'Prazos, audiências e compromissos internos no mesmo calendário.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'Motor de prazos', 'items' => ['CPC dias úteis', 'Cadastro manual', 'Alertas básicos']],
                ['phase' => 'Q4', 'title' => 'Automação', 'items' => ['Publicações → prazo', 'WhatsApp', 'Dashboard crítico']],
            ],
            'integrations' => ['JurisFlow / Bruna', 'WhatsApp Meta', 'Google Calendar'],
            'bruna_prompt' => 'Calcule o prazo para contestação com citação em 15/03/2026, considerando feriado de Carnaval na comarca.',
            'metrics' => [
                ['label' => 'Vencem hoje', 'value' => '—'],
                ['label' => 'Críticos (48h)', 'value' => '—'],
                ['label' => 'Cumprimento', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'audiencias',
            'section' => 'contencioso',
            'label' => 'Audiências',
            'icon' => 'fa-gavel',
            'subtitle' => 'Sessões e preparação',
            'tagline' => 'Gestão de audiências com roteiro, testemunhas, links virtuais e pós-sessão.',
            'status' => 'planned',
            'empty_title' => 'Módulo de audiências em desenvolvimento',
            'empty_text' => 'Agenda de audiências, salas virtuais, checklist de preparação e ata assistida pela Sasha.',
            'features' => [
                'Calendário por vara, juiz e tipo de audiência',
                'Checklist de preparação (testemunhas, documentos, links)',
                'Registro de ata e deliberações pós-sessão',
                'Conflito de agenda entre advogados',
                'Integração com videoconferência do tribunal',
            ],
            'capabilities' => [
                ['icon' => 'fa-clipboard-list', 'title' => 'Roteiro da audiência', 'text' => 'Perguntas-chave, documentos de apoio e estratégia por tese.'],
                ['icon' => 'fa-video', 'title' => 'Links e salas', 'text' => 'Centralize links PJe, Teams e Zoom por processo.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Agenda', 'items' => ['Cadastro', 'Notificações', 'Conflitos']],
            ],
            'integrations' => ['PJe', 'Microsoft Teams', 'Google Meet'],
            'bruna_prompt' => 'Monte um roteiro de perguntas para audiência de instrução em reclamação trabalhista por horas extras.',
            'metrics' => [
                ['label' => 'Esta semana', 'value' => '—'],
                ['label' => 'Virtuais', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'publicacoes',
            'section' => 'contencioso',
            'label' => 'Publicações & Intimações',
            'icon' => 'fa-newspaper',
            'subtitle' => 'DJe e captura automática',
            'tagline' => 'Monitoramento de diários oficiais com triagem por IA e abertura automática de prazos.',
            'status' => 'beta',
            'empty_title' => 'Captura de publicações ativa',
            'empty_text' => 'Configure as OABs do escritório, capture publicações do DJEN e trie com a Sasha — matching automático de processos e sugestão de prazos.',
            'features' => [
                'Captura DJEN por OAB com variantes de inscrição (todos os tribunais)',
                'Classificação automática via Sasha (intimação, despacho, sentença)',
                'Matching automático ao processo CNJ do escritório',
                'Regras de prioridade por cliente premium, processo crítico e valor',
                'Prazo automático após triagem (quando há processo vinculado)',
                'Alerta na plataforma + WhatsApp Meta (telefone configurável)',
                'Download de certidão PDF oficial do DJEN',
                'Fila de triagem, registro manual e arquivamento',
            ],
            'capabilities' => [
                ['icon' => 'fa-robot', 'title' => 'Triagem Sasha', 'text' => 'IA resume a publicação, classifica e sugere ação em linguagem clara.'],
                ['icon' => 'fa-filter', 'title' => 'Regras por cliente', 'text' => 'Priorize publicações de clientes premium, processos críticos e valores elevados.'],
                ['icon' => 'fa-link', 'title' => 'Matching processo', 'text' => 'Vincula automaticamente ao processo CNJ cadastrado no escritório.'],
                ['icon' => 'fa-hourglass-half', 'title' => 'Prazo automático', 'text' => 'Abre prazo processual sozinho após triagem, com processo já vinculado.'],
                ['icon' => 'fa-comment-dots', 'title' => 'Alerta WhatsApp', 'text' => 'Avisa o escritório quando novas publicações chegam (Meta Cloud).'],
                ['icon' => 'fa-file-pdf', 'title' => 'Certidão PDF', 'text' => 'Baixa a certidão oficial da comunicação direto do DJEN.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Captura ✅', 'items' => ['DJEN por OAB ✅', 'Matching processo ✅', 'Triagem IA ✅']],
                ['phase' => 'Automação', 'title' => 'Operação ✅', 'items' => ['Publicação → prazo automático ✅', 'WhatsApp alerta ✅', 'Certidão PDF DJEN ✅']],
            ],
            'integrations' => ['DJEN (Comunica PJe)', 'JurisFlow / Sasha', 'Prazos', 'WhatsApp Meta'],
            'bruna_prompt' => 'Classifique esta publicação e sugira o prazo: [cole o texto do DJe aqui]',
            'metrics' => [
                ['label' => 'Não lidas', 'value' => '—'],
                ['label' => 'Triagem pendente', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'tribunais',
            'section' => 'contencioso',
            'label' => 'Integração Tribunais',
            'icon' => 'fa-building-columns',
            'subtitle' => 'PJe, e-SAJ, Projudi',
            'tagline' => 'Consulta oficial de andamentos via API Pública do DataJud (CNJ), que agrega PJe, e-SAJ, Projudi e mais de 90 tribunais.',
            'status' => 'alpha',
            'empty_title' => 'Hub de integração com tribunais',
            'empty_text' => 'Cadastre a chave gratuita do DataJud e consulte andamentos oficiais direto pelo número do processo, com histórico de movimentações.',
            'features' => [
                'Consulta oficial por número CNJ, com detecção automática do tribunal',
                'Cobertura de mais de 90 tribunais (TJs, TRFs, TRTs, STF, STJ e mais)',
                'Histórico de movimentações oficiais direto no processo',
                'Chave de API gratuita por escritório (cadastro no CNJ)',
                'Ferramenta "consultar_datajud" disponível no chat da Sasha',
            ],
            'capabilities' => [
                ['icon' => 'fa-landmark', 'title' => 'Base nacional do CNJ', 'text' => 'Metadados oficiais direto da fonte, sem scraping e sem depender de login em cada tribunal.'],
                ['icon' => 'fa-robot', 'title' => 'Sasha consulta para você', 'text' => 'Peça "consultar andamento oficial do processo X" no chat e receba o resultado na hora.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'DataJud ✅', 'items' => ['Chave por escritório ✅', 'Consulta por CNJ ✅', 'Ferramenta no chat ✅']],
                ['phase' => '2027', 'title' => 'Conectores nativos', 'items' => ['Webservice PJe (certificado A1/A3)', 'e-SAJ SP', 'Projudi PR']],
            ],
            'integrations' => ['DataJud (CNJ)', 'PJe', 'e-SAJ', 'Projudi', 'EPROC'],
            'bruna_prompt' => 'Consultar andamento oficial do processo 0001234-56.2026.8.26.0100 no DataJud.',
            'metrics' => [
                ['label' => 'Consultas realizadas', 'value' => '—'],
                ['label' => 'Última sync', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'clientes',
            'section' => 'relacionamento',
            'label' => 'CRM Jurídico',
            'icon' => 'fa-user-tie',
            'subtitle' => 'Clientes, leads e carteira',
            'tagline' => 'CRM especializado para advocacia — lead, proposta, contrato e carteira ativa com visão 360°.',
            'status' => 'alpha',
            'empty_title' => 'CRM jurídico em desenvolvimento',
            'empty_text' => 'Funil comercial, onboarding de clientes, KYC, conflito de interesses e histórico de relacionamento.',
            'features' => [
                'Lead → consulta → proposta → contrato de honorários',
                'Ficha completa PF/PJ com documentos e representantes',
                'Verificação automática de conflito de interesses',
                'Segmentação por área, ticket e risco',
                'Portal do cliente com convite por e-mail',
            ],
            'capabilities' => [
                ['icon' => 'fa-funnel-dollar', 'title' => 'Funil advocacia', 'text' => 'Etapas adaptadas a escritórios boutique e full service.'],
                ['icon' => 'fa-id-card', 'title' => 'KYC & compliance', 'text' => 'Coleta de documentos com checklist PLD/CFT quando aplicável.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'Base', 'items' => ['Cadastro', 'Documentos', 'Vínculo processos']],
                ['phase' => 'Q4', 'title' => 'Comercial', 'items' => ['Propostas', 'Funil', 'NPS cliente']],
            ],
            'integrations' => ['Asaas', 'WhatsApp Meta', 'Portal do Cliente'],
            'bruna_prompt' => 'Sugira um roteiro de onboarding para novo cliente PJ de consultoria tributária.',
            'metrics' => [
                ['label' => 'Clientes ativos', 'value' => '—'],
                ['label' => 'Propostas abertas', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'portal',
            'section' => 'relacionamento',
            'label' => 'Portal do Cliente',
            'icon' => 'fa-globe',
            'subtitle' => 'Transparência e self-service',
            'tagline' => 'Área exclusiva para o cliente acompanhar processos, enviar documentos e aprovar minutas.',
            'status' => 'planned',
            'empty_title' => 'Portal do cliente em desenvolvimento',
            'empty_text' => 'Experiência white-label com andamentos, documentos compartilhados e mensagens seguras.',
            'features' => [
                'Timeline de andamentos em linguagem simples',
                'Upload seguro de documentos pelo cliente',
                'Aprovação de minutas e procurações',
                'Cobrança de honorários com link de pagamento',
                'Branding do escritório (logo e cores)',
            ],
            'capabilities' => [
                ['icon' => 'fa-lock', 'title' => 'LGPD by design', 'text' => 'Consentimento, logs de acesso e expurgo programado.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'MVP portal', 'items' => ['Login', 'Timeline', 'Upload']],
            ],
            'integrations' => ['Asaas', 'WhatsApp Meta'],
            'bruna_prompt' => 'Redija um texto claro para o cliente entender o que significa uma sentença procedente em parte.',
            'metrics' => [
                ['label' => 'Acessos/mês', 'value' => '—'],
                ['label' => 'Documentos enviados', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'atendimento',
            'section' => 'relacionamento',
            'label' => 'Central de Atendimento',
            'icon' => 'fa-headset',
            'subtitle' => 'WhatsApp, e-mail e tickets',
            'tagline' => 'Omni-canal jurídico com SLA, templates e histórico vinculado ao caso.',
            'status' => 'beta',
            'empty_title' => 'Central de atendimento ativa',
            'empty_text' => 'Inbox de tickets com WhatsApp Meta, templates Sasha, SLA por carteira e contexto do processo sem trocar de tela.',
            'features' => [
                'Inbox unificada WhatsApp + e-mail + interno',
                'Tickets com SLA (4h Premium, 24h Standard)',
                'Templates de resposta por área do direito',
                'Sugestão de resposta pela Sasha com revisão humana',
                'Envio WhatsApp Meta ao responder (quando configurado)',
                'Vínculo ticket ↔ processo/cliente com contexto de prazos',
            ],
            'capabilities' => [
                ['icon' => 'fa-comments', 'title' => 'Contexto do caso', 'text' => 'Atendente vê prazos, status e fase processual sem trocar de tela.'],
                ['icon' => 'fa-robot', 'title' => 'Templates Sasha', 'text' => 'Respostas prontas e sugestão IA personalizada por ticket.'],
                ['icon' => 'fa-whatsapp', 'title' => 'WhatsApp', 'text' => 'Envio direto ao cliente via Meta Cloud API.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Canais ✅', 'items' => ['WhatsApp ✅', 'Tickets ✅', 'Templates Sasha ✅']],
            ],
            'integrations' => ['WhatsApp Meta', 'JurisFlow / Sasha', 'CRM Clientes', 'Processos'],
            'bruna_prompt' => 'Sugira resposta empática para cliente ansioso perguntando prazo de sentença em ação de indenização.',
            'metrics' => [
                ['label' => 'Tickets abertos', 'value' => '—'],
                ['label' => 'SLA médio', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'documentos',
            'section' => 'producao',
            'label' => 'GED Jurídico',
            'icon' => 'fa-folder-open',
            'subtitle' => 'Repositório e versionamento',
            'tagline' => 'Gestão eletrônica de documentos com OCR, versionamento e busca semântica.',
            'status' => 'alpha',
            'empty_title' => 'GED jurídico em desenvolvimento',
            'empty_text' => 'Pastas por caso, controle de versão, OCR, busca full-text e compartilhamento seguro.',
            'features' => [
                'Árvore de pastas por cliente, caso e processo',
                'Versionamento e comparação de minutas',
                'OCR e indexação para busca',
                'Etiquetas: sigiloso, trabalhista, tributário',
                'Exportação para protocolo e portal',
            ],
            'capabilities' => [
                ['icon' => 'fa-magnifying-glass', 'title' => 'Busca semântica', 'text' => 'Encontre cláusulas e trechos via Sasha em toda a base do escritório.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'Repositório', 'items' => ['Upload', 'Pastas', 'Permissões']],
                ['phase' => 'Q4', 'title' => 'IA', 'items' => ['OCR', 'Resumo Sasha', 'Comparador']],
            ],
            'integrations' => ['JurisFlow / Bruna', 'Portal do Cliente'],
            'bruna_prompt' => 'Resuma os pontos principais deste documento: [cole o texto ou descreva o anexo]',
            'metrics' => [
                ['label' => 'Documentos', 'value' => '—'],
                ['label' => 'Armazenamento', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'peticoes',
            'section' => 'producao',
            'label' => 'Petições & Modelos',
            'icon' => 'fa-file-contract',
            'subtitle' => 'Minutas e automação',
            'tagline' => 'Biblioteca de modelos com variáveis, montagem assistida e exportação para protocolo.',
            'status' => 'planned',
            'empty_title' => 'Modelos de petição em desenvolvimento',
            'empty_text' => 'Templates por área, merge de dados do processo e revisão colaborativa antes do protocolo.',
            'features' => [
                'Biblioteca por área (cível, trabalhista, tributário)',
                'Variáveis automáticas do cadastro processual',
                'Fluxo de revisão sócio → associado → protocolo',
                'Histórico de versões e comentários inline',
                'Sugestão de teses pela Sasha',
            ],
            'capabilities' => [
                ['icon' => 'fa-wand-magic-sparkles', 'title' => 'Montagem assistida', 'text' => 'Sasha preenche fundamentos a partir do resumo do caso.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Produção', 'items' => ['Templates', 'Variáveis', 'Export DOCX/PDF']],
            ],
            'integrations' => ['JurisFlow / Bruna', 'Microsoft Word'],
            'bruna_prompt' => 'Estruture uma petição inicial de danos morais por negativação indevida com pedidos e fundamentos.',
            'metrics' => [
                ['label' => 'Modelos', 'value' => '—'],
                ['label' => 'Geradas/mês', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'assinaturas',
            'section' => 'producao',
            'label' => 'Assinatura & Protocolo',
            'icon' => 'fa-file-signature',
            'subtitle' => 'E-sign e filing',
            'tagline' => 'Assinatura eletrônica, procurações digitais e registro de protocolo com certificado.',
            'status' => 'planned',
            'empty_title' => 'Assinatura eletrônica em desenvolvimento',
            'empty_text' => 'Fluxo de assinatura ICP-Brasil, ordem de signatários e comprovante de protocolo.',
            'features' => [
                'Ordem de assinatura multi-parte',
                'Procurações e substabelecimentos digitais',
                'Comprovante de protocolo anexado ao GED',
                'Lembretes de assinatura pendente',
            ],
            'capabilities' => [
                ['icon' => 'fa-certificate', 'title' => 'ICP-Brasil', 'text' => 'Suporte a certificados A1/A3 e nuvem.'],
            ],
            'roadmap' => [
                ['phase' => '2027', 'title' => 'E-sign', 'items' => ['Fluxo assinatura', 'ICP', 'Protocolo']],
            ],
            'integrations' => ['PJe', 'Certificados digitais'],
            'bruna_prompt' => 'Liste os documentos típicos que precisam de assinatura antes de protocolar uma apelação.',
            'metrics' => [
                ['label' => 'Pendentes', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'contratos',
            'section' => 'consultivo',
            'label' => 'Contratos',
            'icon' => 'fa-handshake',
            'subtitle' => 'Ciclo de vida e riscos',
            'tagline' => 'Gestão consultiva de contratos com análise de cláusulas, renovações e obrigações.',
            'status' => 'alpha',
            'empty_title' => 'Gestão de contratos em desenvolvimento',
            'empty_text' => 'Repositório contratual, alertas de renovação, playbooks de cláusulas e análise de risco pela Sasha.',
            'features' => [
                'Cadastro com partes, vigência e valor',
                'Alertas de renovação e reajuste',
                'Playbooks de cláusulas por tipo de contrato',
                'Análise de risco e desvios pela Sasha',
                'Vínculo com honorários e faturamento',
            ],
            'capabilities' => [
                ['icon' => 'fa-triangle-exclamation', 'title' => 'Mapa de riscos', 'text' => 'Semáforo por cláusula crítica (limitação, multa, foro).'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Consultivo', 'items' => ['CRUD contratos', 'Alertas', 'Análise IA']],
            ],
            'integrations' => ['JurisFlow / Bruna', 'GED Jurídico'],
            'bruna_prompt' => 'Analise os riscos deste contrato de prestação de serviços: [cole o texto aqui]',
            'metrics' => [
                ['label' => 'Vigentes', 'value' => '—'],
                ['label' => 'Renovam em 90d', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'due-diligence',
            'section' => 'consultivo',
            'label' => 'Due Diligence',
            'icon' => 'fa-magnifying-glass-chart',
            'subtitle' => 'M&A e auditoria legal',
            'tagline' => 'Data room, checklists por transação e relatório consolidado para M&A e investimentos.',
            'status' => 'planned',
            'empty_title' => 'Due diligence em desenvolvimento',
            'empty_text' => 'Salas virtuais, checklists setoriais, pendências e relatório executivo com apoio da Sasha.',
            'features' => [
                'Data room com permissões por investidor',
                'Checklists trabalhista, tributário, cível, regulatório',
                'Matriz de achados e severidade',
                'Geração de relatório executivo',
                'Q&A entre equipes com trilha de auditoria',
            ],
            'capabilities' => [
                ['icon' => 'fa-table-list', 'title' => 'Matriz de riscos', 'text' => 'Consolide achados por área com responsável e prazo de saneamento.'],
            ],
            'roadmap' => [
                ['phase' => '2027', 'title' => 'Transações', 'items' => ['Data room', 'Checklists', 'Relatório IA']],
            ],
            'integrations' => ['GED Jurídico', 'JurisFlow / Bruna'],
            'bruna_prompt' => 'Monte checklist de due diligence trabalhista para aquisição de empresa com 200 funcionários.',
            'metrics' => [
                ['label' => 'Projetos ativos', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'societario',
            'section' => 'consultivo',
            'label' => 'Societário & M&A',
            'icon' => 'fa-building',
            'subtitle' => 'Atos societários e fusões',
            'tagline' => 'Atas, alterações contratuais, quadro societário e operações de M&A em fluxo guiado.',
            'status' => 'planned',
            'empty_title' => 'Módulo societário em desenvolvimento',
            'empty_text' => 'Registro de atos, assembleias, quadro de sócios e integração com Junta Comercial.',
            'features' => [
                'Quadro societário e histórico de alterações',
                'Fluxo de atas e deliberações',
                'Calendário de obrigações societárias',
                'Vínculo com due diligence e contratos',
            ],
            'capabilities' => [
                ['icon' => 'fa-sitemap', 'title' => 'Estrutura societária', 'text' => 'Visualize holdings, filiais e participações.'],
            ],
            'roadmap' => [
                ['phase' => '2027', 'title' => 'Societário', 'items' => ['Quadro sócios', 'Atas', 'Obrigações']],
            ],
            'integrations' => ['Junta Comercial', 'GED Jurídico'],
            'bruna_prompt' => 'Quais documentos são necessários para alteração de contrato social com entrada de novo sócio?',
            'metrics' => [
                ['label' => 'Empresas', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'honorarios',
            'section' => 'financeiro',
            'label' => 'Honorários & Timesheet',
            'icon' => 'fa-clock',
            'subtitle' => 'Apontamento e tabelas OAB',
            'tagline' => 'Controle de horas, tabelas OAB, êxito e repasses com precificação transparente.',
            'status' => 'alpha',
            'empty_title' => 'Honorários em desenvolvimento',
            'empty_text' => 'Timesheet por caso, tabela OAB, honorários de êxito, rateio entre sócios e integração com cobrança.',
            'features' => [
                'Apontamento de horas por processo/cliente',
                'Tabelas OAB e políticas internas de preço',
                'Honorários fixos, êxito e mistos',
                'Rateio automático entre sócios e associados',
                'Exportação para faturamento e Asaas',
            ],
            'capabilities' => [
                ['icon' => 'fa-calculator', 'title' => 'Calculadora OAB', 'text' => 'Sasha calcula honorários com base na tabela e no valor da causa.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Financeiro jurídico', 'items' => ['Timesheet', 'Tabela OAB', 'Repasses']],
            ],
            'integrations' => ['Asaas', 'JurisFlow / Bruna', 'Núcleo Financeiro'],
            'bruna_prompt' => 'Calcule honorários advocatícios para ação cível de R$ 120.000 com 15% de êxito.',
            'metrics' => [
                ['label' => 'Horas/mês', 'value' => '—'],
                ['label' => 'A faturar', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'cobranca',
            'section' => 'financeiro',
            'label' => 'Cobrança & Inadimplência',
            'icon' => 'fa-money-bill-wave',
            'subtitle' => 'Recebíveis e recuperação',
            'tagline' => 'Régua de cobrança, links de pagamento, aging de inadimplência e acordos.',
            'status' => 'planned',
            'empty_title' => 'Cobrança jurídica em desenvolvimento',
            'empty_text' => 'Boletos, PIX, cartão via Asaas, régua automática e negociação de débitos de honorários.',
            'features' => [
                'Aging de honorários em aberto',
                'Régua de cobrança por e-mail e WhatsApp',
                'Links de pagamento e parcelamento',
                'Acordos com desconto e trilha de aprovação',
                'Integração com inadimplência processual',
            ],
            'capabilities' => [
                ['icon' => 'fa-chart-pie', 'title' => 'Previsão de caixa', 'text' => 'Projete recebimentos por carteira e área de atuação.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Recebíveis', 'items' => ['Asaas', 'Régua', 'Aging']],
            ],
            'integrations' => ['Asaas', 'WhatsApp Meta'],
            'bruna_prompt' => 'Sugira mensagem de cobrança educada para honorários em atraso há 15 dias.',
            'metrics' => [
                ['label' => 'Em aberto', 'value' => '—'],
                ['label' => 'Inadimplência', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'jurisprudencia',
            'section' => 'inteligencia',
            'label' => 'Jurisprudência IA',
            'icon' => 'fa-book-open',
            'subtitle' => 'Pesquisa STF, STJ e TRTs',
            'tagline' => 'Motor de pesquisa jurisprudencial com resumos, citações e teses sugeridas pela Sasha.',
            'status' => 'beta',
            'empty_title' => 'Pesquisa jurisprudencial com IA',
            'empty_text' => 'Busca em tribunais superiores, súmulas, teses repetitivas e geração de fundamentos para peças.',
            'features' => [
                'Pesquisa com IA por tema, tribunal e período',
                'Resumo executivo de acórdãos e teses',
                'Citação pronta para uso em peças, com botão de copiar',
                'Salvar sugestões direto na biblioteca do escritório',
                'Favoritar registros e filtrar biblioteca por tribunal/relevância/favoritos',
                'Histórico de pesquisas com KPIs reais (consultas do mês, biblioteca, favoritos)',
                'Sugestão de tese para o caso concreto',
            ],
            'capabilities' => [
                ['icon' => 'fa-robot', 'title' => 'Sasha + JurisFlow', 'text' => 'Motor de IA (Azure OpenAI) que resume teses, súmulas e precedentes sob demanda.'],
                ['icon' => 'fa-star', 'title' => 'Favoritos e histórico', 'text' => 'Marque os julgados mais usados e repita pesquisas anteriores com um clique.'],
                ['icon' => 'fa-copy', 'title' => 'Citação pronta', 'text' => 'Copie a referência formatada direto para a petição, sem precisar redigitar.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'IA ✅', 'items' => ['Chat jurisprudência ✅', 'Resumos ✅', 'Citações ✅', 'Favoritos e histórico de pesquisas ✅']],
                ['phase' => 'Q4', 'title' => 'Fontes oficiais', 'items' => ['Integração DataJud (metadados processuais)', 'RAG com base própria do escritório']],
            ],
            'integrations' => ['JurisFlow AI Service', 'DataJud'],
            'bruna_prompt' => 'Pesquise jurisprudência do STJ sobre dano moral por negativação indevida nos últimos 3 anos.',
            'metrics' => [
                ['label' => 'Consultas/mês', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'analytics',
            'section' => 'inteligencia',
            'label' => 'Analytics Jurídico',
            'icon' => 'fa-chart-line',
            'subtitle' => 'BI e produtividade',
            'tagline' => 'BI de carteira com gráficos reais: status, fase, área, tribunal, evolução mensal, receita e SLA de prazos.',
            'status' => 'beta',
            'empty_title' => 'BI de carteira em produção',
            'empty_text' => 'Dashboards executivos com dados reais da carteira — status, fase, área, tribunal, evolução mensal, receita de honorários e SLA de prazos, com visão consolidada de grupo (multi-escritório).',
            'features' => [
                'Composição da carteira por status, fase, área e tribunal',
                'Evolução mensal de novos processos e receita de honorários',
                'SLA de cumprimento de prazos com gauge executivo',
                'Taxa de êxito real calculada a partir dos processos encerrados',
                'Visão consolidada de grupo para escritórios com filiais (matriz/filial)',
                'Metas mensais de receita/taxa de êxito por escritório, área ou advogado',
                'Exportação do painel em PDF (impressão otimizada)',
                'Todos os gráficos com dados 100% reais do banco — zero mock',
            ],
            'capabilities' => [
                ['icon' => 'fa-gauge-high', 'title' => 'BI executivo real', 'text' => 'Gráficos Chart.js/ECharts com dados reais: composição, evolução e produtividade.'],
                ['icon' => 'fa-diagram-project', 'title' => 'Grupo consolidado', 'text' => 'Matriz enxerga a carteira somada de todas as filiais vinculadas por código de grupo.'],
                ['icon' => 'fa-bullseye', 'title' => 'Metas e progresso', 'text' => 'Defina metas de receita ou êxito por área/advogado e acompanhe o percentual atingido no mês.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'BI ✅', 'items' => ['Dashboards com dados reais ✅', 'SLA de prazos ✅', 'Multi-escritório consolidado ✅']],
                ['phase' => '2027', 'title' => 'Avançado ✅', 'items' => ['Exportação para PDF ✅', 'Metas por sócio/área ✅']],
            ],
            'integrations' => ['Pulso', 'Honorários', 'Processos', 'Prazos'],
            'bruna_prompt' => 'Quais KPIs um escritório de contencioso cível deve acompanhar mensalmente?',
            'metrics' => [
                ['label' => 'Taxa êxito', 'value' => '—'],
                ['label' => 'Receita/mês', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'predicao',
            'section' => 'inteligencia',
            'label' => 'Previsão de Êxito',
            'icon' => 'fa-brain',
            'subtitle' => 'Scoring de risco IA',
            'tagline' => 'Score 100% explicável de probabilidade de êxito — heurística por padrão, e regressão logística treinada automaticamente quando há histórico suficiente.',
            'status' => 'alpha',
            'empty_title' => 'Previsão de êxito ativa nos processos',
            'empty_text' => 'Abra qualquer processo ativo para ver o score de 0 a 100 com os fatores explicáveis que o compõem — histórico da área, fase, execução e tempo de tramitação.',
            'features' => [
                'Score de 0 a 100 exibido em cada processo ativo',
                'Fatores 100% explicáveis (sem "caixa-preta") — cada peso é auditável',
                'Baseado no histórico real de êxito por área do escritório',
                'Ferramenta "prever_exito" disponível no chat da Sasha',
                'Painel dedicado de carteira: KPIs, ranking de maior risco e filtros por área/nível',
                'Modelo estatístico treinado (regressão logística) ativado automaticamente a partir de 12 casos encerrados',
                'Calibração cruzada entre escritórios do mesmo grupo econômico (matriz + filiais)',
            ],
            'capabilities' => [
                ['icon' => 'fa-scale-unbalanced', 'title' => 'Apoio à decisão', 'text' => 'Insights explicáveis para sócios priorizarem acordos e recursos.'],
                ['icon' => 'fa-list-check', 'title' => 'Fatores auditáveis', 'text' => 'Cada ponto do score vem com a explicação exata do porquê — sem caixa-preta, mesmo no modelo treinado.'],
                ['icon' => 'fa-ranking-star', 'title' => 'Radar de risco', 'text' => 'Painel com os processos de menor score em destaque, para agir antes do problema crescer.'],
                ['icon' => 'fa-brain', 'title' => 'Modelo que aprende', 'text' => 'A partir de 12 casos encerrados, os pesos deixam de ser fixos e passam a ser calibrados com o histórico real de resultados do escritório (ou do grupo).'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Heurística ✅', 'items' => ['Score explicável ✅', 'Histórico por área ✅', 'Ferramenta no chat ✅', 'Painel de carteira com ranking e filtros ✅']],
                ['phase' => '2027', 'title' => 'ML jurídico ✅', 'items' => ['Modelo treinado com histórico do escritório ✅', 'Calibração cruzada entre escritórios do grupo ✅']],
            ],
            'integrations' => ['Processos', 'Analytics Jurídico'],
            'bruna_prompt' => 'Quais fatores aumentam a chance de procedência em ação de cobrança com nota promissória?',
            'metrics' => [
                ['label' => 'Casos scored', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'agente-autonomo',
            'section' => 'inteligencia',
            'label' => 'Agente Autônomo 24/7',
            'icon' => 'fa-robot',
            'subtitle' => 'Monitoramento proativo',
            'tagline' => 'Varre prazos, tarefas e carteira a cada 30 minutos — notifica sócios sem depender de ninguém perguntar no chat.',
            'status' => 'beta',
            'empty_title' => 'Agente Autônomo em produção',
            'empty_text' => 'Cron determinístico no servidor: varre escritórios, deduplica alertas e publica notificações reais na plataforma.',
            'features' => [
                'Cron a cada 30 minutos em todos os escritórios ativos',
                'Alertas de prazo crítico (D-3) e tarefas atrasadas',
                'Deduplicação inteligente — sem spam do mesmo alerta',
                'Indicador ao vivo na toolbar do Pulso',
                'Painel operacional com última execução e empresas monitoradas',
                'Independente do provedor de IA (não cai se o LLM oscilar)',
            ],
            'capabilities' => [
                ['icon' => 'fa-clock', 'title' => 'Sempre ligado', 'text' => 'Roda fora do chat — o escritório é vigiado mesmo com a Sasha fechada.'],
                ['icon' => 'fa-bell', 'title' => 'Alertas reais', 'text' => 'Notificações na plataforma, com deduplicação de 20h para não repetir o óbvio.'],
                ['icon' => 'fa-building', 'title' => 'Multi-escritório', 'text' => 'Varre matriz e filiais ativas na mesma passagem.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'Fundação ✅', 'items' => ['Cron 30 min ✅', 'Prazos/tarefas ✅', 'Status na toolbar ✅']],
                ['phase' => 'Q4', 'title' => 'Operação ✅', 'items' => ['Painel dedicado ✅', 'Resumo por escritório ✅', 'Execução manual admin ✅']],
            ],
            'integrations' => ['Prazos', 'Processos', 'Notificações', 'Pulso'],
            'bruna_prompt' => 'Quais alertas o Agente Autônomo gerou hoje e o que precisa de atenção imediata?',
            'metrics' => [
                ['label' => 'Última varredura', 'value' => '—'],
                ['label' => 'Alertas hoje', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'orquestracao-ia',
            'section' => 'inteligencia',
            'label' => 'Orquestração IA · Modo Lex',
            'icon' => 'fa-gem',
            'subtitle' => 'Agents + raciocínio superior',
            'tagline' => 'Camada de orquestração da Sasha: router de intenções, agents com tools reais, Modo Lex (reasoning médio) e integração Azure Container Apps.',
            'status' => 'beta',
            'empty_title' => 'Orquestração IA ativa',
            'empty_text' => 'No chat, ative o botão Lex para raciocínio superior. O router decide entre chain (RAG) e agent (tools) conforme a intenção.',
            'features' => [
                'Modo Lex no chat — reasoning medium + budget ampliado',
                'Router com 14 intenções (processo, prazo, contrato, minuta, sentença, carteira…)',
                'Agent LangChain com tools locais + API interna do Symfony',
                'Fallback honesto (sem mensagem falsa de “instabilidade”)',
                'Retry automático quando GPT-5 esgota tokens em reasoning',
                'Deploy em Azure Container Apps (Brazil South)',
            ],
            'capabilities' => [
                ['icon' => 'fa-gem', 'title' => 'Modo Lex', 'text' => 'Respostas de nível sócio: risco, fundamento e próximo passo — tom humano, não robótico.'],
                ['icon' => 'fa-diagram-project', 'title' => 'Router inteligente', 'text' => 'Decide sozinho se usa agent com tools ou chain RAG+LLM.'],
                ['icon' => 'fa-cloud', 'title' => 'Azure estável', 'text' => 'Infra fora da HostGator — sem LVE matando o processo Python.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'Core ✅', 'items' => ['Orchestrator ✅', 'Agent tools ✅', 'Azure deploy ✅']],
                ['phase' => 'Q4', 'title' => 'Lex ✅', 'items' => ['Toggle Lex no chat ✅', 'Reasoning medium ✅', 'Router ampliado ✅']],
            ],
            'integrations' => ['JurisFlow Azure', 'Sasha Chat', 'API Interna'],
            'bruna_prompt' => 'No Modo Lex, monte uma estratégia completa para um contencioso cível com risco de tutela antecipada.',
            'metrics' => [
                ['label' => 'Modo Lex', 'value' => 'Ativo'],
                ['label' => 'Intenções', 'value' => '14'],
            ],
        ],
        [
            'slug' => 'api-publica',
            'section' => 'governanca',
            'label' => 'API Pública',
            'icon' => 'fa-plug-circle-bolt',
            'subtitle' => 'Tokens e integrações externas',
            'tagline' => 'REST API v1 autenticada por token — conecte BI, ERP, portal do cliente e automações próprias ao Unio Jurídico.',
            'status' => 'beta',
            'empty_title' => 'API Pública em produção',
            'empty_text' => 'Gere tokens de acesso e consuma processos, prazos, tarefas e jurisprudência do escritório em qualquer sistema externo.',
            'features' => [
                'Tokens escopados por escritório (nunca vazam dados entre clientes)',
                'Autenticação Bearer stateless — sem sessão, sem cookie',
                'Endpoints reais: processos, prazos, tarefas e jurisprudência',
                'Painel de gestão para gerar e revogar tokens a qualquer momento',
                'Documentação com exemplos de requisição prontos para uso',
            ],
            'capabilities' => [
                ['icon' => 'fa-key', 'title' => 'Tokens seguros', 'text' => 'Hash SHA-256 no banco — o token bruto só é exibido uma vez, na criação.'],
                ['icon' => 'fa-code', 'title' => 'REST/JSON simples', 'text' => 'Sem SDK necessário: qualquer linguagem que fale HTTP consome a API.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'API v1 ✅', 'items' => ['Tokens por escritório ✅', 'Endpoints de leitura ✅', 'Painel de gestão ✅']],
                ['phase' => '2027', 'title' => 'Avançado', 'items' => ['Webhooks de eventos', 'Escopo de escrita completo', 'Rate limit configurável por plano']],
            ],
            'integrations' => ['Processos', 'Prazos', 'Jurisprudência IA'],
            'bruna_prompt' => 'Como um sistema de BI externo pode consumir os processos do meu escritório pela API?',
            'metrics' => [
                ['label' => 'Tokens ativos', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'compliance',
            'section' => 'governanca',
            'label' => 'Compliance OAB & LGPD',
            'icon' => 'fa-shield-halved',
            'subtitle' => 'Ética e privacidade',
            'tagline' => 'Conformidade com Código de Ética OAB, LGPD e políticas internas do escritório.',
            'status' => 'planned',
            'empty_title' => 'Compliance jurídico em desenvolvimento',
            'empty_text' => 'Políticas, treinamentos, registro de incidentes e evidências para auditoria regulatória.',
            'features' => [
                'Políticas de privacidade e ética',
                'Registro de incidentes e violações',
                'Treinamentos obrigatórios da equipe',
                'Mapa de dados e bases legais LGPD',
                'Relatórios para DPO e sócios',
            ],
            'capabilities' => [
                ['icon' => 'fa-file-shield', 'title' => 'Evidências', 'text' => 'Trilha auditável para fiscalização OAB e ANPD.'],
            ],
            'roadmap' => [
                ['phase' => '2027', 'title' => 'Governança', 'items' => ['Políticas', 'Incidentes', 'Treinamentos']],
            ],
            'integrations' => ['GED Jurídico', 'Portal do Cliente'],
            'bruna_prompt' => 'Quais cuidados LGPD ao compartilhar documentos de cliente no portal?',
            'metrics' => [
                ['label' => 'Incidentes abertos', 'value' => '0'],
            ],
        ],
        [
            'slug' => 'conflitos',
            'section' => 'governanca',
            'label' => 'Conflito de Interesses',
            'icon' => 'fa-user-slash',
            'subtitle' => 'Ethical wall',
            'tagline' => 'Verificação automática de conflitos entre clientes, partes adversas e sócios.',
            'status' => 'planned',
            'empty_title' => 'Gestão de conflitos em desenvolvimento',
            'empty_text' => 'Motor de regras, ethical wall entre equipes e bloqueio de novos casos conflitantes.',
            'features' => [
                'Verificação na entrada de leads e processos',
                'Ethical wall entre advogados e áreas',
                'Histórico de declarações de impedimento',
                'Alertas em tempo real no CRM',
            ],
            'capabilities' => [
                ['icon' => 'fa-ban', 'title' => 'Bloqueio preventivo', 'text' => 'Impeça cadastro de caso com parte adversa já representada.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Ética', 'items' => ['Motor regras', 'Wall', 'Alertas CRM']],
            ],
            'integrations' => ['CRM Jurídico', 'Processos'],
            'bruna_prompt' => 'Explique quando há conflito de interesses ao representar duas empresas do mesmo grupo econômico.',
            'metrics' => [
                ['label' => 'Verificações/mês', 'value' => '—'],
            ],
        ],
        [
            'slug' => 'auditoria',
            'section' => 'governanca',
            'label' => 'Auditoria de Atos',
            'icon' => 'fa-clipboard-check',
            'subtitle' => 'Trilha imutável',
            'tagline' => 'Log completo de ações sensíveis — quem acessou, alterou ou exportou dados do escritório.',
            'status' => 'planned',
            'empty_title' => 'Auditoria em desenvolvimento',
            'empty_text' => 'Trilha imutável de acessos, downloads, alterações em processos e comunicações com clientes.',
            'features' => [
                'Log de acesso a documentos sigilosos',
                'Histórico de alterações em cadastros',
                'Exportação para perícia e compliance',
                'Alertas de comportamento anômalo',
            ],
            'capabilities' => [
                ['icon' => 'fa-fingerprint', 'title' => 'Rastreabilidade', 'text' => 'Atenda exigências de SOC2 e auditorias internas.'],
            ],
            'roadmap' => [
                ['phase' => '2027', 'title' => 'Audit', 'items' => ['Logs', 'Export', 'Alertas']],
            ],
            'integrations' => ['GED Jurídico', 'Compliance'],
            'bruna_prompt' => 'Quais eventos um escritório deve auditar para conformidade com LGPD?',
            'metrics' => [
                ['label' => 'Eventos/dia', 'value' => '—'],
            ],
        ],
    ];

    /**
     * Módulos "graduados" para telas reais (CRUD com banco de dados), com a rota
     * dedicada que substitui a vitrine genérica `app_juridico_modulo`.
     *
     * @var array<string, string>
     */
    public const GRADUATED_ROUTES = [
        'processos' => 'app_juridico_processos',
        'prazos' => 'app_juridico_prazos',
        'clientes' => 'app_juridico_clientes',
        'documentos' => 'app_juridico_documentos',
        'honorarios' => 'app_juridico_honorarios',
        'jurisprudencia' => 'app_juridico_jurisprudencia',
        'analytics' => 'app_juridico_analytics',
        'predicao' => 'app_juridico_previsao_exito',
        'agente-autonomo' => 'app_juridico_agente_autonomo',
        'tribunais' => 'app_juridico_tribunais',
        'publicacoes' => 'app_juridico_publicacoes',
        'atendimento' => 'app_juridico_atendimento',
        'api-publica' => 'app_juridico_api_tokens',
    ];

    public static function isGraduated(string $slug): bool
    {
        return isset(self::GRADUATED_ROUTES[$slug]);
    }

    public static function graduatedRoute(string $slug): ?string
    {
        return self::GRADUATED_ROUTES[$slug] ?? null;
    }

    public static function findBySlug(string $slug): ?array
    {
        foreach (self::MODULES as $module) {
            if ($module['slug'] === $slug) {
                return $module;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_column(self::MODULES, 'slug');
    }

    /**
     * @return list<array{key: string, label: string, modules: list<JuridicoModule>}>
     */
    public static function grouped(): array
    {
        $buckets = [];
        foreach (self::MODULES as $module) {
            $buckets[$module['section']][] = $module;
        }

        $out = [];
        foreach (self::SECTIONS as $key => $label) {
            if (empty($buckets[$key])) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => $label,
                'modules' => $buckets[$key],
            ];
            unset($buckets[$key]);
        }

        foreach ($buckets as $key => $modules) {
            $out[] = [
                'key' => $key,
                'label' => $key,
                'modules' => $modules,
            ];
        }

        return $out;
    }

    /** @return list<array{value: string, label: string, tone: string}> */
    public static function dashboardKpis(): array
    {
        return [
            ['value' => (string) \count(self::MODULES), 'label' => 'Módulos no roadmap', 'tone' => 'sky'],
            ['value' => 'Sasha', 'label' => 'Copiloto IA ativo', 'tone' => 'amber'],
            ['value' => '7', 'label' => 'Áreas do escritório', 'tone' => 'neutral'],
            ['value' => '2027', 'label' => 'Visão enterprise', 'tone' => 'rose'],
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'alpha' => 'Alpha',
            'beta' => 'Beta',
            default => 'Em breve',
        };
    }
}
