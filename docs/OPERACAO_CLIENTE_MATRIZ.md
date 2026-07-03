# Matriz cliente × produto × branch × URL

Referência para contratos, deploy isolado e organização de trabalho (jul/2026).  
Fluxo geral: [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) · Branches: [BRANCHES.md](BRANCHES.md)

---

## Como ler esta matriz

| Coluna | Significado |
|--------|-------------|
| **Produto / hub** | Módulo na plataforma |
| **Branch Git** | Onde espelhar código do módulo (`product/*`) |
| **URL base** | Caminho após o domínio (`https://DOMINIO/...`) |
| **Scope permissão** | Chave em grants (`hub_*` ou `product_*`) |
| **Maturidade** | Maduro · Em evolução · Stub |
| **Deploy próprio?** | Só com branch `client/*` + infra separada |

**Importante:** a URL não isola dados — isolamento real = **domínio + pasta + banco + `.env` + branch `client/*`**.

---

## Catálogo de produtos (Unio — código atual)

### Core (sempre presente em qualquer instância)

| Produto | Branch | URL | Scope | Maturidade |
|---------|--------|-----|-------|------------|
| Login / sessão | `product/core` | `/login` | — | Maduro |
| Dashboard | `product/core` | `/dashboard` | — | Maduro |
| Workspace (empresa) | `product/core` | `/workspace` | — | Maduro |
| Projetos internos Unio | `product/core` | `/core/projetos` | `product_core` | Maduro |
| Chat (Bate Papo) | `feature/core-chat-papo` | `/chat` | — | Em evolução |
| Perfil / notificações | `product/core` | `/perfil`, `/notificacoes` | — | Maduro |

### Núcleo Operações (agregador)

| Produto | Branch | URL | Scope | Maturidade |
|---------|--------|-----|-------|------------|
| Hub Operações | `product/hub-operacoes` | `/operacoes` | `hub_operacoes` | Maduro |

### Produtos dentro de Operações

| Produto | Branch | URL | Scope | Maturidade |
|---------|--------|-----|-------|------------|
| RH — hub | `product/rh` | `/rh` | `hub_operacoes` + `product_rh` | Maduro |
| RH — funcionários | `product/rh` | `/rh/funcionarios` | `product_rh` / `funcionarios` | Maduro |
| RH — admissões / demissões | `product/rh` | `/rh/admissoes`, `/rh/demissoes` | `product_rh` / `admissoes` | Maduro |
| RH — férias, folha, ponto | `product/rh` | `/rh/ferias`, `/rh/folha`, `/rh/ponto` | `product_rh` | Maduro |
| RH — portal colaborador | `product/rh` | `/rh/portal` | `product_rh` / `portal` | Maduro |
| RH — eSocial, folha legal | `product/rh` | `/rh/esocial`, `/rh/folha-legal` | `product_rh` | Em evolução |
| Pessoas — hub | `product/pessoas` | `/pessoas` | `hub_operacoes` + `product_pessoas` | Maduro |
| Pessoas — membros, equipes | `product/pessoas` | `/pessoas/membros`, `/pessoas/equipes` | `product_pessoas` | Maduro |
| Engenharia — hub | `product/engenharia` | `/engenharia` | `hub_operacoes` + `product_engenharia` | Maduro |
| Engenharia — projetos | `product/engenharia` | `/engenharia/projetos` | `product_engenharia` | Maduro |

### Hubs especializados

| Produto | Branch | URL | Scope | Maturidade |
|---------|--------|-----|-------|------------|
| Pós-operatório | `product/hub-pos-operatorio` | `/pos-operatorio` | `hub_pos_operatorio` | Maduro |
| Pós-op — pacientes | ↑ | `/pos-operatorio/pacientes` | `pacientes` | Maduro |
| Pós-op — alertas / sala crítica | ↑ | `/pos-operatorio/alertas` | `alertas` | Maduro |
| Pós-op — questionários | ↑ | `/pos-operatorio/questionarios` | `questionarios` | Maduro |
| Pós-op — portal paciente | ↑ | `/pos-operatorio/portal` | `portal_paciente` | Maduro |
| TI — hub | `product/hub-ti` | `/ti` | `hub_ti` | Maduro |
| TI — chamados | ↑ | `/ti/chamados`, `/ti/meus-chamados` | `chamados`, `meus_chamados` | Maduro |
| Talentos | `product/hub-talentos` | `/talentos` | `hub_talentos` | Em evolução |
| Maturidade | `product/hub-maturidade` | `/maturidade` | `hub_maturidade` | Em evolução |
| Recrutamento | — (feature/hub) | `/recrutamento` | `hub_recrutamento` | Em evolução |
| Publicidade | `product/publicidade` | `/publicidade` | `product_publicidade` | Em evolução |
| Admin plataforma | `product/admin` | `/admin` | `hub_admin` | Maduro |
| Integrações | — | `/integracoes` | `hub_integracoes` | Em evolução |
| Inovação | — | `/inovacao` | `hub_inovacao` | Em evolução |

### Stubs (menu existe, pouca ou nenhuma função)

Comercial, Benefícios, Academy, Financeiro, Compliance, Analytics, Jurídico, Clima, SST, Comunicação, ESG, etc. — scope `hub_*`, maturidade **Stub**.

---

## Modelo de contrato (template)

Preencha uma linha por cliente:

```markdown
### Cliente: NOME
- **Branch Git:** client/slug-cliente
- **Domínio:** https://...
- **Deploy:** workflow `deploy-client-slug` → environment `client-slug`
- **Servidor:** DEPLOY_PATH / banco / .env (ver checklist abaixo)
- **Empresa tenant:** nome no Admin → Empresas
- **URL entrada sugerida:** /login ou subpath (/rh, /pos-operatorio)

| Incluído | Produto | Branch trabalho | URL | Grants a liberar |
|----------|---------|-----------------|-----|------------------|
| ☐ | ... | product/... | /... | scope / produto |
```

---

## Exemplo A — Unio (plataforma principal, hoje)

| Item | Valor |
|------|--------|
| **Cliente / instância** | Unio Corp (SaaS) |
| **Branch deploy** | `production` |
| **Domínio** | `https://uniowork.com.br` |
| **GitHub Environment** | `production` |
| **Deploy** | Automático a cada push em `production` |

### Módulos típicos (owner / tenant)

| Incluído | Produto | Branch espelho | URL | Observação |
|:--------:|---------|----------------|-----|------------|
| ✅ | Core | `product/core` | `/login`, `/dashboard` | Sempre |
| ✅ | Admin | `product/admin` | `/admin` | TENANT / PLATFORM_OWNER |
| ✅ | Operações | `product/hub-operacoes` | `/operacoes` | Agregador |
| ✅ | RH | `product/rh` | `/rh` | Conforme grants |
| ✅ | Pessoas | `product/pessoas` | `/pessoas` | Conforme grants |
| ✅ | Engenharia | `product/engenharia` | `/engenharia` | Conforme grants |
| ✅ | TI | `product/hub-ti` | `/ti` | Conforme grants |
| ✅ | Pós-operatório | `product/hub-pos-operatorio` | `/pos-operatorio` | Conforme grants |
| ◐ | Talentos, Maturidade, Publicidade | `product/hub-*` | `/talentos`, … | Parcial |
| ❌ | Stubs diversos | — | `/comercial`, … | Só se habilitar grant |

### Trabalho do dia a dia

```bash
git checkout production
git checkout -b feature/minha-tarefa
# ... commits ...
# PR → production  OU  merge direto → push production
bash scripts/sync-branches.sh production   # alinha product/*
```

---

## Exemplo B — União Médica (cliente dedicado — proposta)

Cenário ilustrativo: clínica/hospital com **RH interno + pós-operatório**, sem admin global da plataforma Unio.

| Item | Valor proposto |
|------|----------------|
| **Cliente** | União Médica |
| **Branch Git** | `client/uniao-medica` |
| **Domínio** | `https://uniao-medica.com.br` (ou subdomínio temporário) |
| **GitHub Environment** | `client-uniao-medica` (a criar) |
| **Deploy** | Push em `client/uniao-medica` → **só esse servidor** |
| **Promoção para Unio** | Merge seletivo → `production` quando feature for genérica |

### Módulos do contrato (sugestão)

| Incluído | Produto | Branch trabalho | URL principal | Grants / perfis |
|:--------:|---------|-----------------|---------------|-----------------|
| ✅ | Core (login, layout) | `product/core` | `/login` | Todos |
| ✅ | Pós-operatório | `product/hub-pos-operatorio` | `/pos-operatorio` | Equipe clínica, enfermagem |
| ✅ | Pós-op portal paciente | ↑ | `/pos-operatorio/portal` | Paciente / link externo |
| ✅ | RH — funcionários | `product/rh` | `/rh/funcionarios` | RH, gestão |
| ✅ | RH — admissões | `product/rh` | `/rh/admissoes` | RH |
| ◐ | RH — folha / eSocial | `product/rh` | `/rh/folha` | Só se contratado |
| ✅ | Pessoas — equipes | `product/pessoas` | `/pessoas/equipes` | Gestão |
| ❌ | Engenharia | `product/engenharia` | `/engenharia` | Fora do contrato |
| ❌ | Admin plataforma Unio | `product/admin` | `/admin` | **Não** — usar perfil GESTOR tenant |
| ❌ | TI, Talentos, stubs | — | — | Fora do contrato |

**URL de entrada sugerida:** `https://uniao-medica.com.br/pos-operatorio` ou subdomínio `https://pos.uniao-medica.com.br` (redirecionamento futuro).

### Fluxo de trabalho só União Médica

```bash
git checkout client/uniao-medica
git pull origin client/uniao-medica
git checkout -b feature/uniao-medica-alertas-pos-op
# edita: templates/modules/pos-operatorio/, src/.../PosOperatorio/
git push origin feature/uniao-medica-alertas-pos-op
# PR → client/uniao-medica  →  deploy só do cliente

# Trazer correção da Unio principal:
git checkout client/uniao-medica
git merge production          # traz fixes genéricos; resolver conflitos

# Subir melhoria genérica para todos:
git checkout production
git cherry-pick <commit>      # ou merge client/uniao-medica (revisar customizações)
git push origin production
```

---

## Checklist de configuração por cliente novo

### 1. Git / GitHub

| # | Item |
|---|------|
| 1 | Criar branch `client/<slug>` a partir de `production` |
| 2 | Environment `client-<slug>` no GitHub |
| 3 | Secrets: `DEPLOY_SSH_HOST`, `DEPLOY_SSH_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_SSH_PORT` |
| 4 | Variables: `DEPLOY_PATH`, `DEPLOY_PUBLIC_HTML` |
| 5 | Workflow deploy só para `client/<slug>` (futuro) |
| 6 | CI: push em `client/**` (futuro) |

### 2. HostGator / servidor

| # | Item |
|---|------|
| 1 | Domínio ou subdomínio apontando para a conta |
| 2 | Pasta app: ex. `/home/USER/uniao-medica` |
| 3 | `public_html` ou symlink para `public/` |
| 4 | Banco MySQL dedicado |
| 5 | `.env.local`: `DATABASE_URL`, `APP_SECRET`, `DEFAULT_URI=https://dominio` |
| 6 | SSL (AutoSSL) |
| 7 | E-mail / `MAILER_DSN` do cliente (não usar `joabe@` / caixas Unio) |

### 3. Plataforma (após primeiro deploy)

| # | Item |
|---|------|
| 1 | Admin → Empresas: cadastrar tenant do cliente |
| 2 | Admin → Usuários: gestor + equipes |
| 3 | Permissões: liberar só scopes da tabela de contrato |
| 4 | Configurações → Identidade: logo, nome, website do cliente |
| 5 | Rodapé / suporte: e-mail e site do cliente |
| 6 | Validar login + URLs de cada módulo contratado |

### 4. O que **não** copiar da Unio

- Secrets `MAILBOX_JOABE_*`, `PLATFORM_OWNER_PASSWORD` no deploy do cliente
- Steps `app:platform:sync-email-identity` / `setup-platform-mailboxes.sh` (Unio)
- Usuário `joabe@uniowork.com.br` como owner

---

## Mapa rápido: onde codar cada frente

| Se você está mexendo em… | Branch feature sugerida | Pastas principais |
|--------------------------|-------------------------|-------------------|
| RH | `feature/rh-*` | `templates/modules/rh/`, `src/Controller/Module/Rh/` |
| Pós-operatório | `feature/pos-*` | `templates/modules/pos-operatorio/`, `src/.../PosOperatorio/` |
| Pessoas | `feature/pessoas-*` | `templates/modules/pessoas/` |
| TI | `feature/ti-*` | `templates/modules/ti/` |
| Layout global | `feature/core-*` | `templates/base.html.twig`, `public/css/` |
| Só cliente X | `feature/<slug>-*` → PR para `client/<slug>` | Módulos do contrato + custom em `var/admin_config.json` |

---

## Resumo

| Instância | Branch deploy | Isolamento |
|----------|---------------|------------|
| Unio (`uniowork.com.br`) | `production` | Tenant por empresa no mesmo banco |
| Cliente (ex. União Médica) | `client/uniao-medica` | Domínio + pasta + banco + grants |

A **matriz de contrato** define *o que* o cliente vê (URLs + permissões). A **branch `client/*`** define *onde* você desenvolve e publica sem afetar a Unio até fazer merge.
