# Módulo RH — Unio

Documentação do **pacote operacional de RH** na plataforma Unio: o que está pronto, o que é opcional (polimento) e o que ainda **não foi implementado** (roadmap / mercado avançado).

> **Resumo:** a base operacional de RH por empresa está **funcional** para uso interno (gestores/RH). O **portal do colaborador** e o **ATS (Recrutamento)** existem em núcleos dedicados; eSocial, ponto legal e folha contábil completa seguem como fases futuras.

Para o roadmap detalhado por fase, veja também **[RH_ROADMAP.md](RH_ROADMAP.md)** e **[ROADMAP_90_DIAS.md](ROADMAP_90_DIAS.md)**.

---

## Índice

1. [Visão geral](#visão-geral)
2. [O que está implementado](#o-que-está-implementado)
3. [Rotas e permissões](#rotas-e-permissões)
4. [Regras de negócio](#regras-de-negócio)
5. [Banco de dados e migrations](#banco-de-dados-e-migrations)
6. [Interface (UI) e componentes](#interface-ui-e-componentes)
7. [Comandos, seeds e testes](#comandos-seeds-e-testes)
8. [Melhorias opcionais (curto prazo)](#melhorias-opcionais-curto-prazo)
9. [Ainda não implementado — roadmap](#ainda-não-implementado--roadmap)
10. [Como subir o ambiente](#como-subir-o-ambiente)

---

## Visão geral

O módulo RH cobre o ciclo básico de **pessoas na empresa** no workspace selecionado:

| Área | Função |
|------|--------|
| **Hub** (`/rh`) | KPIs: funcionários, admissões/demissões abertas, férias, folha do mês |
| **Funcionários** | Cadastro, edição, busca e filtros |
| **Admissões (onboarding)** | Processo com checklist, documentos, provisionamento de usuário |
| **Demissões (offboarding)** | Processo com checklist, inativação do colaborador e usuário |
| **Férias** | Solicitação, aprovação/rejeição, transição automática de status por data |
| **Folha (simplificada)** | Competência mensal, lançamentos, fechamento, export CSV |

Escopo por **empresa** (multi-tenant via workspace). Permissões via `product_rh` e grants por produto (`funcionarios`, `admissoes`, `ferias`, `folha`).

---

## O que está implementado

### Hub RH (`/rh`)

- Contadores: total de funcionários, ativos, processos de admissão/demissão em aberto, férias em gozo e pendentes.
- Status da folha da competência atual (`AAAA-MM`).
- Sincronização automática de status de férias por data (`RhFeriasService::syncStatusByDate`).

### Funcionários (`/rh/funcionarios`)

| Recurso | Detalhe |
|---------|---------|
| Listagem | Busca (`q`) por nome, e-mail ou cargo; filtro por status |
| Cadastro | Nome, e-mail, cargo, departamento, salário, foto, status, datas |
| Edição | Mesmos campos |
| Detalhe | Ficha do colaborador |
| E-mail | Único **por empresa** (não global) |
| Toolbar | Componentes padrão: `section_toolbar`, `toolbar_search`, `filter_group`, `toolbar_filter_actions` |

**Arquivos:** `RhFuncionarioController`, `FuncionarioService`, `templates/modules/rh/funcionarios*.twig`

### Admissões — onboarding (`/rh/admissoes`)

| Recurso | Detalhe |
|---------|---------|
| Listagem | Busca e filtro por status |
| Nova admissão | Formulário dedicado |
| Detalhe | Checklist, KPIs, documentos, provisionar usuário, cancelar, concluir |
| Checklist | Itens padrão; marcar/desmarcar; **100% obrigatório** para concluir |
| Conclusão | Cria `Funcionario` ativo vinculado ao processo |
| Documentos | Upload em `public/uploads/...` |
| Usuário | Criação de conta na plataforma a partir do processo (senha + perfil) |
| Cancelamento | Bloqueado se já concluído |

**Backend de edição:** `RhOnboardingService::update()` e action `update` no controller — **sem formulário dedicado na UI** (ver [melhorias opcionais](#melhorias-opcionais-curto-prazo)).

**Arquivos:** `RhController`, `RhOnboardingService`, `RhDocumentService`, `RhUserProvisioningService`, componentes em `templates/components/rh/`

### Demissões — offboarding (`/rh/demissoes`)

| Recurso | Detalhe |
|---------|---------|
| Listagem | Busca e filtro por status |
| Nova demissão | Apenas funcionários **ativos**; bloqueio se já existe offboarding aberto |
| Checklist | Igual admissão; conclusão exige 100% |
| Conclusão | Funcionário → `INATIVO`; usuário vinculado → `ativo = false` |
| Documentos / cancelar | Sim |

**Arquivos:** `RhOffboardingService`, templates `demissao_*.twig`

### Férias (`/rh/ferias`)

| Recurso | Detalhe |
|---------|---------|
| Solicitação | Período, dias calculados, observações |
| Aprovação / rejeição | Com motivo na rejeição |
| Regras | Sem sobreposição de períodos aprovados/em gozo; só ativos solicitam |
| Status | `SOLICITADA` → `APROVADA` → `EM_GOZO` → `CONCLUIDA` / `REJEITADA` |
| Automático | Status e flag `FERIAS` no funcionário conforme datas |
| Registro | `solicitante_user_id` e `aprovador_user_id` |

**Sem e-mail** ao aprovar/rejeitar (ver roadmap).

### Folha simplificada (`/rh/folha`)

| Recurso | Detalhe |
|---------|---------|
| Gerar competência | Mês `AAAA-MM`; lançamentos de salário base para ativos com salário > 0 |
| Detalhe | Lançamentos manuais (provento/desconto), totais, fechar competência |
| Export | CSV (`app_rh_folha_export`) |

**Limitações intencionais:** não é folha legal/contábil completa (INSS, IRRF, eSocial, holerite PDF, etc.).

### Segurança

- Rotas mapeadas em `ProductGrantRouteMap`.
- Ações sensíveis com nível mínimo em `ProductGrantAccess::MANAGE_ROUTES` (ex.: `GESTOR_EQUIPE` para criar, `GESTOR` para gerar folha).
- Formulários POST com CSRF (`rh_process_action`, `rh_funcionario_form`, `rh_folha_form`, etc.).

### Testes automatizados

- `tests/Service/RhOnboardingServiceTest.php` — conclusão bloqueada com checklist incompleto.

---

## Rotas e permissões

| Rota | Caminho | Produto (grant) |
|------|---------|-----------------|
| `app_rh` | `/rh` | `product_rh` |
| `app_rh_funcionarios` | `/rh/funcionarios` | `funcionarios` |
| `app_rh_funcionario_novo` | `/rh/funcionarios/novo` | `funcionarios` (GESTOR_EQUIPE+) |
| `app_rh_funcionario_show` | `/rh/funcionarios/{id}` | `funcionarios` |
| `app_rh_funcionario_editar` | `/rh/funcionarios/{id}/editar` | `funcionarios` (GESTOR_EQUIPE+) |
| `app_rh_admissoes` | `/rh/admissoes` | `admissoes` |
| `app_rh_admissoes_nova` | `/rh/admissoes/nova` | `admissoes` (GESTOR_EQUIPE+) |
| `app_rh_admissoes_show` | `/rh/admissoes/{id}` | `admissoes` |
| `app_rh_demissoes` | `/rh/demissoes` | `admissoes` |
| `app_rh_demissoes_nova` | `/rh/demissoes/nova` | `admissoes` (GESTOR_EQUIPE+) |
| `app_rh_demissoes_show` | `/rh/demissoes/{id}` | `admissoes` |
| `app_rh_ferias` | `/rh/ferias` | `ferias` |
| `app_rh_ferias_nova` | `/rh/ferias/nova` | `ferias` (GESTOR_EQUIPE+) |
| `app_rh_ferias_show` | `/rh/ferias/{id}` | `ferias` |
| `app_rh_folha` | `/rh/folha` | `folha` |
| `app_rh_folha_gerar` | POST `/rh/folha/gerar` | `folha` (GESTOR+) |
| `app_rh_folha_show` | `/rh/folha/{id}` | `folha` |
| `app_rh_folha_export` | `/rh/folha/{id}/exportar` | `folha` |

---

## Regras de negócio

### Onboarding

- E-mail não pode duplicar outro processo ativo nem funcionário na **mesma empresa**.
- Não edita/conclui/cancela processo já **concluído** (edição também bloqueada se **cancelado**).
- Checklist completo obrigatório para `complete()`.

### Offboarding

- Apenas funcionário **ATIVO**.
- Um único processo aberto por funcionário.
- Conclusão: inativa funcionário e desativa usuário ligado (por vínculo ou e-mail).

### Férias

- Validação de intervalo e sobreposição.
- Aprovação registra aprovador e data; pode passar direto para `EM_GOZO` se período inclui hoje.

### Folha

- Uma competência por `empresa + referência`.
- Competência fechada não aceita novos lançamentos.

### Exceções

- `RhProcessException` → mensagens amigáveis em flash na UI.

---

## Banco de dados e migrations

### Tabelas principais (RH)

| Tabela | Entidade |
|--------|----------|
| `funcionario` | `Funcionario` (+ índice único `empresa_id` + `email`) |
| `rh_onboarding_process` | `RhOnboardingProcess` |
| `rh_offboarding_process` | `RhOffboardingProcess` |
| `rh_ferias` | `RhFerias` |
| `rh_folha_competencia` | `RhFolhaCompetencia` |
| `rh_folha_lancamento` | `RhFolhaLancamento` |
| `rh_process_document` | `RhProcessDocument` |
| `departamento` | `Departamento` (usado no cadastro de funcionário) |

### Migration do pacote completo

- `migrations/Version20260527120000.php` — férias, folha, documentos, índice de e-mail por empresa.

### Mapeamento importante

Colunas de usuário em férias no banco:

- `solicitante_user_id`
- `aprovador_user_id`

A entidade `RhFerias` declara isso explicitamente em `JoinColumn` (não usar `solicitante_id` / `aprovador_id`).

```bash
php bin/console doctrine:migrations:migrate
php bin/console doctrine:migrations:status
```

---

## Interface (UI) e componentes

### Layout de listas

Todas as listas RH usam `layout/page_list.html.twig` e a **toolbar padrão** (não criar filtros HTML ad hoc):

- `components/section_toolbar.html.twig`
- `components/toolbar_search.html.twig`
- `components/filter_group.html.twig`
- `components/toolbar_filter_actions.html.twig`

Referência: `templates/modules/admin/usuarios.html.twig`.

### Componentes RH reutilizáveis

| Componente | Uso |
|------------|-----|
| `components/rh_process_table.html.twig` | Tabela de processos admissão/demissão |
| `components/rh_process_detail.html.twig` | Detalhe do processo + checklist |
| `components/unio/checklist.html.twig` | Checklist genérico |
| `components/unio/detail_field_*.html.twig` | Campos de detalhe (evita HTML inválido em `<dl>`) |
| `components/rh/admissao_form_panel.html.twig` | Form nova admissão |
| `components/rh/demissao_form_panel.html.twig` | Form nova demissão |
| `components/rh/process_extras_panel.html.twig` | Documentos, usuário, cancelar |

Estilos: `public/css/unio-app.css` (classes `rh-*`, `unio-checklist`, etc.).

### Estrutura de código

```
src/
  Controller/Module/Rh/
    RhController.php           # Hub, admissões, demissões
    RhFuncionarioController.php
    RhFeriasController.php
    RhFolhaController.php
    RhEmpresaScopeTrait.php
  Entity/
    RhFerias.php, RhOnboardingProcess.php, ...
  Service/
    RhOnboardingService.php, RhOffboardingService.php,
    RhFeriasService.php, RhFolhaService.php,
    FuncionarioService.php, RhHubService.php,
    RhDocumentService.php, RhUserProvisioningService.php
  Repository/
    Rh*Repository.php, FuncionarioRepository.php
templates/modules/rh/
tests/Service/
  RhOnboardingServiceTest.php
```

---

## Comandos, seeds e testes

### Ambiente de demonstração

```bash
php bin/console app:seed-users
php bin/console app:seed-product-grants
php bin/console app:seed-rh-processes          # admissões/demissões demo
php bin/console app:seed-rh-processes --fresh  # recria processos demo
```

Credenciais típicas de dev (ver `SeedUsersCommand`):

- `tenant@unio.dev` / `unio123`

### Testes

```bash
php bin/phpunit tests/Service/RhOnboardingServiceTest.php
```

---

## Melhorias opcionais (curto prazo)

Itens **pequenos** que melhoram UX mas **não impedem** o RH de funcionar:

| Item | Situação atual | Esforço estimado |
|------|----------------|------------------|
| **Seed de departamentos demo** | `Departamento` existe; funcionário pode selecionar na UI, mas **não há** `app:seed-departamentos` | Baixo — comando console |
| **Editar admissão em andamento na UI** | `update` no backend/controller; **falta** formulário na tela de detalhe | Baixo/médio — form em `admissao_show` ou painel lateral |
| **E-mail ao aprovar/rejeitar férias** | Não implementado; depende de `Mailer` Symfony + templates | Médio |
| **Editar demissão em andamento** | Sem `update` no offboarding (só criar + checklist) | Médio — paridade com admissão |
| **CRUD de departamentos no RH** | Departamentos geridos fora do módulo RH (entidade compartilhada) | Médio — se quiser gestão só em RH |
| **Mais testes** | Só onboarding/checklist | Médio — férias, offboarding, folha |
| **Notificações in-app reais** | `NotificationMockService` referencia rotas RH | Depende do produto de notificações |

---

## Ainda não implementado — roadmap

Estes itens são comuns em **RH de mercado** e foram **deliberadamente deixados fora** do pacote operacional atual. Não significam que o sistema está “quebrado” — são **novos produtos / integrações**.

### Portal do colaborador ✅

Implementado em **`/rh/portal`** (`RhPortalController`): dashboard, férias, holerites, comunicados e auto-vínculo por e-mail. Grant `product_rh` → `portal`. Ver [RH_ROADMAP.md](RH_ROADMAP.md) fase 1.

### Recrutamento e seleção (ATS) ✅

Hub dedicado **`/recrutamento`**: vagas, candidatos, pipeline, analytics, carreiras. Integração com admissão RH em evolução (`RhRecrutamentoService::convertToOnboarding`). O núcleo **Talentos** redireciona vagas para Recrutamento.

### eSocial e obrigações legais

- Envio de eventos (S-2200, S-2230, folha, etc.).
- Certificado digital, retornos, reconciliação.
- Layouts e versões do governo.

### Ponto eletrônico 🟡

MVP web em **`/rh/ponto`** (batidas). REP, geolocalização e integração folha legal — fases futuras.

### Assinatura digital

- Contratos e termos com validade jurídica (ICP-Brasil, DocuSign, etc.).

### E-mail automático (transacional RH)

- Disparos em eventos: admissão criada, checklist pendente, férias aprovadas, offboarding, folha fechada.
- Diferente de “melhoria opcional” pontual: aqui é **módulo de comunicação** completo.

### Organograma visual

- Árvore hierárquica interativa (reportes, departamentos, cargos).
- O módulo **Gestão de Pessoas** pode cobrir parte disso no roadmap da plataforma.

### Provisão contábil automática

- Provisão de férias e 13º, encargos, integração contábil/ERP.

### Folha legal completa

- INSS, IRRF, FGTS, rubricas legais, holerite PDF, DIRF, RAIS.
- A folha atual é **operacional simplificada** (salário base + lançamentos + CSV).

### Outros (plataforma)

| Item | Nota |
|------|------|
| BI / dashboards avançados de RH | Hub tem KPIs básicos apenas |
| Mobile app dedicado | Web responsiva AdminLTE |
| Integração ERP / contábil | API/export limitado a CSV da folha |
| Histórico auditável completo | Logs parciais; sem trilha de auditoria dedicada RH |
| Workflow configurável | Checklists fixos em código/JSON na entidade |

---

## Como subir o ambiente

```bash
composer install
cp .env.local.example .env.local   # configurar DATABASE_URL

php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction

php bin/console app:seed-users
php bin/console app:seed-product-grants

symfony server:start
# Acessar /rh com usuário que tenha grant product_rh na empresa do workspace
```

### Checklist rápido se algo falhar

1. Migration `Version20260527120000` aplicada?
2. Workspace com empresa selecionada?
3. `app:seed-product-grants` executado para o usuário?
4. Erro em `rh_ferias` com coluna `solicitante_id`? → conferir mapeamento `solicitante_user_id` em `RhFerias.php`.

---

## Histórico de escopo

| Fase | Entregue |
|------|----------|
| **Base operacional** | Funcionários, hub, admissão/demissão com checklist, documentos, provisionamento de usuário, férias, folha simplificada, grants, CSRF, testes básicos |
| **Polimento UI** | Toolbars componentizadas, detalhe de processos, formulários admissão/demissão |
| **Fora do pacote** | Portal colaborador, eSocial, ponto, ATS, assinatura digital, e-mail transacional completo, organograma visual, provisões contábeis |

---

*Última atualização: maio/2026 — alinhado ao código em `src/Controller/Module/Rh/` e migration `Version20260527120000`.*
