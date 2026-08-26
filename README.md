# Unio Corp — Plataforma SaaS Multi-Produto

Monólito Symfony multi-produto: cada vertical (RH/Saúde e Jurídico) roda no mesmo
codebase, isolado por branch, prefixo de rota e registry de módulos.

## Produtos

| Produto | Branch | Descrição |
|---------|--------|-----------|
| **Unio RH / Saúde** | `main` / `uniosaude` | Gestão de Pessoas, Talentos, Hub de Maturidade, Pós-Operatório |
| **Unio Jurídico** | `uniojuridico` | Gestão de escritórios de advocacia com IA (JurisFlow + Sasha) |

> Documentação do Jurídico: [docs/uniojuridico/README.md](docs/uniojuridico/README.md)
> · Arquitetura: [docs/uniojuridico/ARCHITECTURE.md](docs/uniojuridico/ARCHITECTURE.md)

## Stack

- **PHP 8.2** + **Symfony 7.4**
- **Doctrine ORM** + **MySQL**
- **AdminLTE 3** + **Bootstrap 4** (assets locais em `public/vendor/`, sincronizados via npm)
- **Twig** (templates)
- **JurisFlow AI Service** (Python + FastAPI + LangChain) — apenas no vertical Jurídico

## Perfis de Acesso

| Perfil | Acesso |
|--------|--------|
| **Tenant** | Acesso total: todas as empresas, produtos e configurações globais da plataforma |
| **Admin** | Gestão de usuários, configurações da empresa e permissões de produto |
| **Gestor** | Dashboards gerenciais, analytics, metas e módulos estratégicos do produto ativo |
| **Supervisor** | Módulos operacionais: equipes, funcionários, processos e tarefas do dia a dia |
| **Operador / Advogado** | Processos, prazos, documentos e atendimentos *(vertical Jurídico)* |
| **Colaborador** | Acesso ao próprio perfil, contracheque, férias e portal do colaborador *(vertical RH)* |

## Módulos

### Unio RH / Saúde

| Módulo | Funcionalidades |
|--------|----------------|
| **RH** | Funcionários, admissões, demissões, férias, ponto, holerite, folha de pagamento, comunicados internos — [docs/RH.md](docs/RH.md) |
| **Recrutamento** | Vagas, pipeline de candidatos, avaliação, aprovação e onboarding/offboarding |
| **Gestão de Pessoas** | Equipes, cargos, organograma, avaliação de desempenho, PDI |
| **Hub de Talentos** | Pool de talentos, trilhas de carreira, mentoria e mapeamento de competências |
| **Hub de Maturidade** | Radar por dimensão, plano de ação, histórico e evolução por período |
| **SST** | Saúde e Segurança do Trabalho: exames, EPIs, incidentes e laudos |
| **Benefícios** | Gestão e consulta de benefícios corporativos |
| **TI** | Chamados, ativos, licenças, planejamento, base de conhecimento e notas de atualização |
| **Pós-Operatório** | Gestão clínica: agendamentos, protocolos, pacientes, TISS, prontuários e alertas |

### Unio Jurídico

| Módulo | Funcionalidades |
|--------|----------------|
| **Processos e Prazos** | Gestão de processos judiciais, controle de prazos, timeline, partes e eventos |
| **Clientes** | Cadastro de clientes, portal de acompanhamento e aprovação de documentos |
| **Financeiro** | Honorários, cobranças, provisões e metas do escritório |
| **Documentos e IA** | Upload, análise de contratos, geração de minutas, comparação e resumo via JurisFlow |
| **Jurisprudência** | Pesquisa com IA, biblioteca do escritório, consultas e sugestões por caso |
| **Publicações** | Captura e triagem automática de diários oficiais (Sasha/DataJud) |
| **Audiências** | Agendamento, preparo, atas e controle de audiências |
| **Compliance** | Incidentes, conflitos de interesse e checklist regulatório |
| **Sasha IA** | Chat jurídico (Modo Lex), tools autônomas, RAG, agente 24/7 e orquestração inteligente |

## Documentação para desenvolvedores

| Documento | Conteúdo |
|-----------|----------|
| [docs/ROADMAP_90_DIAS.md](docs/ROADMAP_90_DIAS.md) | Roadmap 30/60/90 dias + backlog por módulo |
| [docs/ROADMAP_TRANSICAO_ORGANISMO.md](docs/ROADMAP_TRANSICAO_ORGANISMO.md) | Transição Hub/Núcleo → Colônia/Cena/Pulso + matriz tech + tasks T0 |
| [docs/ESTRUTURA.md](docs/ESTRUTURA.md) | Pastas, convenções, módulos |
| [docs/QUALIDADE_PERFORMANCE_E_HUBS.md](docs/QUALIDADE_PERFORMANCE_E_HUBS.md) | Empty states, hubs planejados, performance, Redis, PHPStan |
| [docs/RH.md](docs/RH.md) | Módulo RH (implementado e roadmap) |
| [docs/HUB_POS_OPERATORIO_INTEGRACAO.md](docs/HUB_POS_OPERATORIO_INTEGRACAO.md) | Hub Pós-Operatório — integração na plataforma ([PDF](docs/HUB_POS_OPERATORIO_INTEGRACAO.pdf)) |
| [docs/DEPLOY_HOSTGATOR.md](docs/DEPLOY_HOSTGATOR.md) | Deploy na HostGator (`uniowork.com.br` / `.online`) |
| [docs/uniojuridico/README.md](docs/uniojuridico/README.md) | Unio Jurídico — produto, IA e deploy |
| [docs/uniojuridico/ARCHITECTURE.md](docs/uniojuridico/ARCHITECTURE.md) | Arquitetura, ADRs e fluxos do vertical Jurídico |

## Instalação

```bash
# 1. Instalar dependências
composer install
# Windows: se "composer" falhar, use php composer.phar install

# 2. Configurar banco
cp .env.local.example .env.local
# edite o .env.local com suas credenciais

# 3. Criar banco e executar migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 4. Usuários e grants de demonstração
php bin/console app:seed-users
php bin/console app:seed-product-grants

# 5. Sincronizar assets de UI (Bootstrap, AdminLTE, Font Awesome, etc.)
npm install
npm run vendor:sync

# 6. Iniciar servidor
symfony server:start

# 7. (Opcional) Qualidade e CSS de produção
composer phpstan
composer minify-css

# 8. (Opcional) Regenerar PDF da doc Hub Pós-Operatório
npx puppeteer browsers install chrome   # só na primeira vez
npm run docs:pos-operatorio-pdf
```

### Serviços Docker opcionais

```bash
# Sasha AI (assistente) — serviço definido em docker-compose.yml
docker compose up -d Sasha-ai

# Mercure e Redis: configure no .env para produção (ver config/packages/prod/cache.yaml).
# Não há serviços mercure/redis no compose local neste repositório.
```

## Estrutura

```
src/
  Contract/
    LegalAiClientInterface.php        # Contrato do cliente IA jurídico (ISP/DIP)

  Controller/
    Auth/                             # Login, logout, recuperação de senha
    Core/                             # Dashboard, chat, perfil, notificações
    Module/
      Rh/                             # Funcionários, férias, ponto, folha, comunicados
      Pessoas/                        # Equipes, cargos, avaliação de desempenho
      Talentos/                       # Pool de talentos, vagas, recrutamento
      Maturidade/                     # Radar, plano de ação, histórico
      Sst/                            # Saúde e Segurança do Trabalho
      Beneficios/                     # Benefícios corporativos
      Ti/                             # Chamados, ativos, licenças, base de conhecimento
      PosOperatorio/                  # Gestão clínica (TISS, pacientes, agendamentos)
      Juridico/                       # Processos, prazos, clientes, honorários, IA
      Admin/                          # Configurações globais e gestão de usuários
      Analytics/                      # Dashboards e métricas
      Compliance/                     # Incidentes e conflitos
      Inovacao/ Comercial/ Financeiro/ Ti/ Operacoes/ ...
    Api/
      SashaApiController.php          # Chat IA (Sasha / JurisFlow)
      AsaasWebhookController.php      # Cobranças
      WhatsappMetaWebhookController.php

  Entity/
    User.php / Empresa.php / Funcionario.php / Departamento.php
    Rh*.php                           # Férias, folha, ponto, holerite, vagas, comunicados
    Juridico*.php                     # Processos, prazos, documentos, honorários, publicações
    Clinic*.php                       # Pós-Operatório (pacientes, atendimentos, TISS)
    Ti*.php / Inovacao*.php / Integ*.php ...

  Service/
    Juridico/
      JurisFlowAiClient.php           # Implementação HTTP do cliente IA
      NullLegalAiClient.php           # Null Object para testes sem rede
      JuridicoJurisprudenciaService.php
      JuridicoPublicacaoTriagemService.php
      JuridicoDocumentoRagSyncService.php
      ...
    Sasha/
      SashaClient.php                 # Cliente do assistente Sasha (RH/Saúde)
      SashaToolRegistry.php           # Registry de tools (tag app.sasha_tool)
      Tool/Juridico/                  # 15+ tools jurídicas (AnalisarContrato, GerarMinuta...)

templates/
  base.html.twig                      # Layout AdminLTE
  juridico/                           # Templates do vertical Jurídico
  rh/ pessoas/ talentos/ maturidade/  # Templates do vertical RH/Saúde
  pos-operatorio/ ti/ inovacao/ ...

config/
  services.yaml                       # DI: alias LegalAiClientInterface -> JurisFlowAiClient
```
