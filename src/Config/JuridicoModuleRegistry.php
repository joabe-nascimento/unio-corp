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
            'subtitle' => 'Casos, partes e andamentos',
            'tagline' => 'Painel único de contencioso com fases, varas, pedidos e estratégia por caso.',
            'status' => 'alpha',
            'empty_title' => 'Gestão de processos em construção',
            'empty_text' => 'Cadastro estruturado de processos, partes, varas, pedidos, fases e histórico de andamentos — integrado ao Pulso e à Bruna.',
            'features' => [
                'Cadastro CNJ, tribunal, vara, classe e assunto',
                'Partes, advogados adversos e terceiros interessados',
                'Linha do tempo de andamentos e peças protocoladas',
                'Distribuição por área, equipe e advogado responsável',
                'Vínculo cliente ↔ múltiplos processos e subpastas',
            ],
            'capabilities' => [
                ['icon' => 'fa-diagram-project', 'title' => 'Kanban processual', 'text' => 'Visualize fases (conhecimento, instrução, recursal, execução) com SLA por etapa.'],
                ['icon' => 'fa-users', 'title' => 'Equipes multidisciplinares', 'text' => 'Sócio, associado, estagiário e backoffice com permissões granulares por caso.'],
                ['icon' => 'fa-link', 'title' => 'Integração tribunais', 'text' => 'Sincronização com PJe, e-SAJ e Projudi para captura automática de movimentações.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'Núcleo do caso', 'items' => ['CRUD processos', 'Partes e representação', 'Dashboard por status']],
                ['phase' => 'Q4', 'title' => 'Operação avançada', 'items' => ['Kanban de fases', 'Tarefas por processo', 'Alertas de risco']],
                ['phase' => '2027', 'title' => 'Mercado enterprise', 'items' => ['Multi-escritório', 'API pública', 'BI de carteira']],
            ],
            'integrations' => ['PJe', 'e-SAJ', 'Projudi', 'JurisFlow / Bruna'],
            'bruna_prompt' => 'Resuma o status estratégico de um processo trabalhista em fase de conhecimento com pedido de horas extras.',
            'metrics' => [
                ['label' => 'Casos ativos', 'value' => '—'],
                ['label' => 'Em recurso', 'value' => '—'],
                ['label' => 'SLA médio', 'value' => '—'],
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
                ['icon' => 'fa-calculator', 'title' => 'Bruna calcula prazos', 'text' => 'IA jurídica com tabela de prazos e jurisprudência de contagem.'],
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
            'empty_text' => 'Agenda de audiências, salas virtuais, checklist de preparação e ata assistida pela Bruna.',
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
            'status' => 'planned',
            'empty_title' => 'Captura de publicações em desenvolvimento',
            'empty_text' => 'Integração com diários oficiais, classificação por relevância e vinculação ao processo correto.',
            'features' => [
                'Captura DJe / DJEN por OAB e escritório',
                'Classificação automática (intimação, despacho, sentença)',
                'Sugestão de prazo com confirmação humana',
                'Fila de triagem para estagiários',
                'Trilha de auditoria LGPD',
            ],
            'capabilities' => [
                ['icon' => 'fa-robot', 'title' => 'Triagem Bruna', 'text' => 'IA resume a publicação e sugere ação em linguagem clara.'],
                ['icon' => 'fa-filter', 'title' => 'Regras por cliente', 'text' => 'Priorize publicações de clientes estratégicos ou valores elevados.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Captura', 'items' => ['DJe', 'Matching processo', 'Triagem IA']],
            ],
            'integrations' => ['DJe', 'DJEN', 'JurisFlow / Bruna'],
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
            'tagline' => 'Conectores oficiais para sincronizar processos, documentos e movimentações sem retrabalho.',
            'status' => 'planned',
            'empty_title' => 'Hub de integrações tribunais',
            'empty_text' => 'Conectores PJe, e-SAJ, Projudi e APIs de tribunais estaduais com monitoramento de saúde.',
            'features' => [
                'Autenticação segura por certificado A1/A3',
                'Sincronização incremental de andamentos',
                'Download automático de anexos relevantes',
                'Monitor de disponibilidade dos tribunais',
                'Log de sincronização por escritório',
            ],
            'capabilities' => [
                ['icon' => 'fa-shield-halved', 'title' => 'Credenciais isoladas', 'text' => 'Cofre por advogado com permissão do escritório.'],
            ],
            'roadmap' => [
                ['phase' => '2027', 'title' => 'Conectores', 'items' => ['PJe TRT', 'e-SAJ SP', 'Projudi PR']],
            ],
            'integrations' => ['PJe', 'e-SAJ', 'Projudi', 'EPROC'],
            'bruna_prompt' => 'Quais tribunais devo priorizar para integração se atuo em contencioso trabalhista em SP e RJ?',
            'metrics' => [
                ['label' => 'Conectores ativos', 'value' => '0'],
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
            'status' => 'planned',
            'empty_title' => 'Central de atendimento em desenvolvimento',
            'empty_text' => 'WhatsApp Business, e-mail e tickets internos com contexto do processo e respostas sugeridas pela Bruna.',
            'features' => [
                'Inbox unificada WhatsApp + e-mail',
                'Templates de resposta por área do direito',
                'SLA por tipo de cliente (premium, standard)',
                'Sugestão de resposta pela Bruna com revisão humana',
                'Vínculo automático mensagem ↔ processo/cliente',
            ],
            'capabilities' => [
                ['icon' => 'fa-comments', 'title' => 'Contexto do caso', 'text' => 'Atendente vê prazos, status e última movimentação sem trocar de tela.'],
            ],
            'roadmap' => [
                ['phase' => 'Q4', 'title' => 'Canais', 'items' => ['WhatsApp', 'Tickets', 'Templates Bruna']],
            ],
            'integrations' => ['WhatsApp Meta', 'JurisFlow / Bruna'],
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
                ['icon' => 'fa-magnifying-glass', 'title' => 'Busca semântica', 'text' => 'Encontre cláusulas e trechos via Bruna em toda a base do escritório.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'Repositório', 'items' => ['Upload', 'Pastas', 'Permissões']],
                ['phase' => 'Q4', 'title' => 'IA', 'items' => ['OCR', 'Resumo Bruna', 'Comparador']],
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
                'Sugestão de teses pela Bruna',
            ],
            'capabilities' => [
                ['icon' => 'fa-wand-magic-sparkles', 'title' => 'Montagem assistida', 'text' => 'Bruna preenche fundamentos a partir do resumo do caso.'],
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
            'empty_text' => 'Repositório contratual, alertas de renovação, playbooks de cláusulas e análise de risco pela Bruna.',
            'features' => [
                'Cadastro com partes, vigência e valor',
                'Alertas de renovação e reajuste',
                'Playbooks de cláusulas por tipo de contrato',
                'Análise de risco e desvios pela Bruna',
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
            'empty_text' => 'Salas virtuais, checklists setoriais, pendências e relatório executivo com apoio da Bruna.',
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
                ['icon' => 'fa-calculator', 'title' => 'Calculadora OAB', 'text' => 'Bruna calcula honorários com base na tabela e no valor da causa.'],
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
            'tagline' => 'Motor de pesquisa jurisprudencial com resumos, citações e teses sugeridas pela Bruna.',
            'status' => 'alpha',
            'empty_title' => 'Pesquisa jurisprudencial em desenvolvimento',
            'empty_text' => 'Busca em tribunais superiores, súmulas, teses repetitivas e geração de fundamentos para peças.',
            'features' => [
                'Busca por tema, tribunal e período',
                'Resumo executivo de acórdãos',
                'Exportação de citações ABNT',
                'Favoritos e pastas de jurisprudência',
                'Sugestão de tese para o caso concreto',
            ],
            'capabilities' => [
                ['icon' => 'fa-robot', 'title' => 'Bruna + JurisFlow', 'text' => 'RAG jurídico com base do escritório e fontes públicas.'],
            ],
            'roadmap' => [
                ['phase' => 'Q3', 'title' => 'IA', 'items' => ['Chat jurisprudência', 'Resumos', 'Citações']],
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
            'tagline' => 'Indicadores de produtividade, taxa de êxito, receita por área e carga da equipe.',
            'status' => 'planned',
            'empty_title' => 'BI jurídico em desenvolvimento',
            'empty_text' => 'Dashboards executivos para sócios — carteira, produtividade, SLA de prazos e rentabilidade por cliente.',
            'features' => [
                'Taxa de êxito por área e magistrado',
                'Receita e margem por cliente/caso',
                'Carga horária vs. orçado (timesheet)',
                'SLA de prazos e gargalos operacionais',
                'Exportação para PDF e apresentações',
            ],
            'capabilities' => [
                ['icon' => 'fa-gauge-high', 'title' => 'Pulso executivo', 'text' => 'KPIs jurídicos integrados ao Pulso do escritório.'],
            ],
            'roadmap' => [
                ['phase' => '2027', 'title' => 'BI', 'items' => ['Dashboards', 'Export', 'Metas']],
            ],
            'integrations' => ['Pulso', 'Honorários', 'Processos'],
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
            'tagline' => 'Modelo preditivo de probabilidade de êxito, duração e valor baseado em histórico e jurisprudência.',
            'status' => 'planned',
            'empty_title' => 'Inteligência preditiva em pesquisa',
            'empty_text' => 'Score de risco por caso, simulação de cenários e recomendação de acordo vs. litígio.',
            'features' => [
                'Score de probabilidade de êxito',
                'Estimativa de duração e custo processual',
                'Simulador acordo vs. sentença',
                'Fatores explicáveis (XAI) para o advogado',
            ],
            'capabilities' => [
                ['icon' => 'fa-scale-unbalanced', 'title' => 'Apoio à decisão', 'text' => 'Insights para sócios priorizarem acordos e recursos.'],
            ],
            'roadmap' => [
                ['phase' => '2027', 'title' => 'ML jurídico', 'items' => ['Modelo piloto', 'Explicabilidade', 'Calibração']],
            ],
            'integrations' => ['JurisFlow AI Service', 'Analytics Jurídico'],
            'bruna_prompt' => 'Quais fatores aumentam a chance de procedência em ação de cobrança com nota promissória?',
            'metrics' => [
                ['label' => 'Casos scored', 'value' => '—'],
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

    /** @return list<array{value: string, label: string, tone: string}>} */
    public static function dashboardKpis(): array
    {
        return [
            ['value' => (string) \count(self::MODULES), 'label' => 'Módulos no roadmap', 'tone' => 'sky'],
            ['value' => 'Bruna', 'label' => 'Copiloto IA ativo', 'tone' => 'amber'],
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
