# Qualidade, performance e hubs planejados

Documentação das melhorias aplicadas na plataforma Unio: empty states, catálogo de hubs, otimizações de front, cache Redis, PHPStan e correções de baixo risco.

---

## Índice

1. [Empty states](#empty-states)
2. [Hubs planejados](#hubs-planejados)
3. [Performance e rapidez](#performance-e-rapidez)
4. [Cache Redis (produção)](#cache-redis-produção)
5. [PHPStan — análise estática](#phpstan--análise-estática)
6. [Correções de risco mínimo](#correções-de-risco-mínimo)
7. [Comandos úteis](#comandos-úteis)

---

## Empty states

### Componente central

| Arquivo | Função |
|---------|--------|
| `templates/components/empty_state.html.twig` | Ícone, título, texto e botão opcional |
| `templates/components/empty-state-panel.html.twig` | Wrapper para telas cheias (preenche viewport sem scroll) |

### Modificadores CSS

| Classe | Uso |
|--------|-----|
| *(padrão)* | Listas e cards vazios (ícone ~36px) |
| `compact: true` | Cards internos (ex.: Talentos, certificações) |
| `hero: true` | Telas de hub / módulo em desenvolvimento (ícone grande) |

### Wrappers por contexto

| Wrapper | Quando usar |
|---------|-------------|
| `empty-state-panel` | Stub, hub em dev, hub sem permissão |
| `page_stub.html.twig` | Layout `stub.html.twig` (“Módulo em desenvolvimento”) |
| `hub_development_empty.html.twig` | Hubs do `PlannedHubRegistry` |
| `hub_scope_empty.html.twig` | Hub acessível, mas sem produtos no grant |

### Comportamento visual

- Sem borda tracejada nem fundo no ícone (só o ícone).
- Telas com `empty-state-panel`: altura preenche a área útil; **sem scroll** extra na página.
- CSS: `public/css/unio-app.css` (dev) / `public/css/unio-app.min.css` (prod).

---

## Hubs planejados

### Registry central

Toda definição de hub “só landing” fica em:

```
src/Config/PlannedHubRegistry.php
```

Cada entrada inclui: `id`, `scope`, `route`, `path`, `label`, `icon`, textos do empty state e **grupo** da sidebar (`HUB_GROUP`).

### Hubs operacionais (com produto)

| Hub | Rota principal |
|-----|----------------|
| Operações | `app_hub_operacoes` |
| Talentos | `app_talentos` |
| Maturidade | `app_maturidade` |
| Plataforma | `app_admin` (TENANT) |

### Hubs no registry (37 planejados + rotas)

Incluem, entre outros: Comercial, Benefícios, Seguros, Academy, Parceiros, Marketing, Financeiro, Compliance, Licitações, Analytics, Lakehouse, Jurídico, Clima, SST, Saúde Ocupacional, Comunicação, Publicidade, Obras, PMO, Portal, Recrutamento, Treinamento Regulatório, Terceiros, ESG, Suprimentos, TI, Expansão, Franquias, Qualidade, Facilities, Patrimônio, Conhecimento, Integrações, Customer Success, Inovação, Segurança da Informação, Multi-empresa.

Rotas extras em: `src/Controller/Module/Extended/ExtendedHubController.php`.

### Sidebar agrupada

O picker (“Hub Selecionado”) agrupa hubs em:

| Grupo | Exemplos |
|-------|----------|
| Operação | Operações, Talentos, Maturidade |
| Negócios & Growth | Comercial, Seguros, Marketing, Parceiros, Academy… |
| Pessoas & Cultura | Clima, Portal, Recrutamento, Treinamento Regulatório, Terceiros… |
| Finanças & Compliance | Financeiro, Jurídico, Licitações… |
| Dados & Inteligência | Analytics, Lakehouse, Integrações, Conhecimento |
| Operações & Ativos | Obras, PMO, Publicidade, SST, Saúde Ocupacional… |
| Tecnologia | TI, Inovação, Segurança da Informação |
| Estratégia & Governança | ESG, Franquias, Multi-empresa |
| Plataforma | Admin |

Template: `templates/layout/_sidebar_hub_picker.html.twig`  
Dados: `NavigationService::getVisiblePlannedHubGroups()`.

### Como adicionar um hub novo

1. Entrada em `PlannedHubRegistry::HUBS` + `HUB_GROUP`.
2. Escopo em `PermissionService::SCOPES` e `ALL_HUB_GROUPS`.
3. Rota em `ProductGrantRouteMap` (`product` => `_hub`).
4. Controller (ou método em `ExtendedHubController`).
5. `PageBackResolver::HUB_PARENT` e `OnboardingHubVisitSubscriber` (prefixos de rota).

A sidebar e o welcome passam a listar automaticamente (perfil GESTOR/TENANT ou grant no escopo).

---

## Performance e rapidez

### CSS minificado em produção

| Ambiente | Arquivo |
|----------|---------|
| `APP_ENV=dev` | `public/css/unio-app.css` |
| `APP_ENV=prod` | `public/css/unio-app.min.css` |

Gerar/atualizar o minificado:

```bash
composer minify-css
# ou
php bin/minify-css.php
```

Redução típica: ~30–35% do tamanho do arquivo.

### Flex só em empty state (correção de risco)

O `flex: 1` que estica a página **só** é aplicado quando existe `.empty-state-panel` (`:has()` no CSS). Listagens e hubs com cards **não** ganham espaço vazio extra antes do footer.

### Lazy-load dos gráficos (Welcome)

Na tela de boas-vindas, Chart.js, ECharts e `unio-charts.js` **não** carregam no primeiro paint.

| Arquivo | Função |
|---------|--------|
| `public/js/welcome-charts-lazy.js` | Carrega scripts quando `#welcome-analytics` entra na viewport |
| `templates/core/welcome/index.html.twig` | Não inclui mais `charts/assets.html.twig` no bloco global |

Dashboard e RH continuam carregando gráficos normalmente via `components/charts/assets.html.twig`.

### Analytics modular (backend)

Serviços em `src/Service/Analytics/` + `src/Chart/ChartPanelFactory.php` — `WelcomeAnalyticsService` apenas orquestra. Reutilização em welcome, RH e futuros painéis.

---

## Cache Redis (produção)

### Docker

```bash
docker compose up -d redis
```

Serviço definido em `compose.yaml` (porta `6379`).

### Configuração

| Arquivo | Função |
|---------|--------|
| `config/packages/prod/cache.yaml` | `cache.adapter.redis` em produção |
| `.env` / `.env.local` | `REDIS_URL=redis://127.0.0.1:6379` |

Em **dev**, o cache Symfony continua em **filesystem** (Redis opcional).

---

## PHPStan — análise estática

### Para que serve

Analisa o PHP em `src/` **sem executar** a aplicação e detecta, por exemplo:

- tipos incorretos e `null` inesperado;
- métodos inexistentes;
- propriedades não usadas;
- PHPDoc inconsistente.

**Não substitui** testes PHPUnit nem testes manuais — **complementa**.

### Configuração

| Arquivo | Função |
|---------|--------|
| `phpstan.neon.dist` | Nível 5, paths, extensões Symfony/Doctrine |
| `phpstan-baseline.neon` | 62 avisos legados “congelados”; só **erros novos** falham |

Excluído da análise: `src/DataFixtures/` (Fixtures não estão no autoload de produção).

### Instalação (Windows)

O comando `composer` do PATH pode apontar para o atalho da Microsoft Store e falhar em scripts. Use:

```bash
php composer.phar install
# ou instale o Composer via https://getcomposer.org/download/
```

### Comandos

```bash
# Análise (aquece cache Symfony + roda PHPStan)
composer phpstan

# Regenerar baseline após corrigir avisos antigos
composer phpstan:baseline
```

### Quando rodar

- Antes de commit/PR;
- Depois de mudanças em `Service/`, `Controller/`, permissões ou analytics.

---

## Correções de risco mínimo

Alterações pequenas, sem mudar regra de negócio:

| Correção | Motivo |
|----------|--------|
| `flex` com `:has(.empty-state-panel)` | Evita espaço vazio em listagens |
| Ícones inline vs `hero` separados | Listas não ficam com ícone gigante |
| `PageBackResolver`: chave duplicada `app_engenharia` removida | Bug real no mapa de “voltar” |
| `PlannedHubRegistry`: grupo `conhecimento` em `HUB_GROUP` | Teste de registry |
| `hasAnyHub()` usa `showAnyPlannedHub()` | Sidebar aparece para todos os hubs planejados |
| Welcome: gráficos lazy | Menos JS na carga inicial |
| CSS v236+ com cache-bust `?v=` em `base.html.twig` | Força atualização no browser |

---

## Comandos úteis

```bash
# Dependências
php composer.phar install          # Windows se "composer" falhar

# Qualidade
composer phpstan
composer test
php bin/console app:validate-system

# Front / assets
composer minify-css

# Infra local
docker compose up -d redis mercure
symfony server:start

# Cache Symfony (após deploy ou mudança de config)
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

---

## Referência rápida de arquivos

```
src/Config/PlannedHubRegistry.php      # Catálogo de hubs + grupos
src/Service/NavigationService.php      # Visibilidade e grupos na nav
src/Controller/Module/Extended/        # Rotas de hubs novos (landing)
templates/components/empty_state*.twig
templates/layout/_sidebar_hub_picker.html.twig
public/css/unio-app.css | unio-app.min.css
public/js/welcome-charts-lazy.js
phpstan.neon.dist | phpstan-baseline.neon
bin/minify-css.php
config/packages/prod/cache.yaml
compose.yaml                           # redis + mercure
```

---

## Leitura relacionada

- [ESTRUTURA.md](ESTRUTURA.md) — organização do projeto  
- [RH.md](RH.md) — módulo RH  
- [README.md](../README.md) — instalação geral  
