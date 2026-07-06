# GitHub Actions — CI, validação e deploy

Referência dos workflows em `.github/workflows/` (jul/2026).

---

## Visão geral

```
                    ┌──────────────────────┐
                    │  validate-reusable   │
                    │  (workflow_call)     │
                    └──────────┬───────────┘
                               │
           ┌───────────────────┼───────────────────┐
           ▼                   ▼                   ▼
      ci.yml            deploy-production.yml   workflow_dispatch
   push: oficiais         push production         (manual)
   PR: todas
```

**Objetivo:** um push em `production` gera **2 runs** (CI + Deploy), não dezenas de runs idênticos ao sincronizar `product/*` e `feature/*`.

---

## 1. `validate-reusable.yml` — fonte da verdade

**Tipo:** reusable (`workflow_call`) — não dispara sozinho.

### O que executa

| Step | Comando / ação |
|------|----------------|
| MariaDB 10.11 | Service container |
| Composer install | `composer install` |
| Validação | `composer validate:ci` |

### Dentro de `validate:ci`

| # | Check |
|---|--------|
| 1 | `composer validate` |
| 2 | `lint:container` |
| 3 | `lint:yaml config` |
| 4 | `lint:twig templates` |
| 5 | Banco: schema + seeds |
| 6 | `app:validate-system` |
| 7 | PHPStan (exceto branch `production`) |
| 8 | PHPUnit |
| 9 | `php bin/minify-css.php` |
| 10 | `npm ci` + `npm run vendor:sync` |

---

## 2. `ci.yml` — integração contínua

### Triggers (atualizado jul/2026)

```yaml
on:
  push:
    branches:
      - main
      - production
      - new_staging
      - new_staging2
  pull_request:
    branches:
      - main
      - production
      - new_staging
      - new_staging2
      - 'product/**'
      - 'feature/**'
  workflow_dispatch:
```

### Concurrency

```yaml
concurrency:
  group: ci-${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true
```

Cancela run anterior na mesma branch se houver push novo.

### Quando o CI roda

| Evento | Branches | Runs |
|--------|----------|------|
| **push** | `production`, `main`, `new_staging`, `new_staging2` | 1× validate |
| **push** | `product/*`, `feature/*` | **Não roda** |
| **pull_request** | → oficiais ou product/feature | 1× validate |
| **workflow_dispatch** | manual | 1× validate |

### Por que `product/*` não dispara CI no push

Branches espelho são sincronizadas em massa com `scripts/sync-branches.sh`. Disparar CI em cada push gerava ~30 runs idênticos e poluía a tela de Actions. Validação dessas branches: **via PR** ou push em `production`.

---

## 3. Deploy HostGator (`deploy-reusable.yml`)

**Workflows que chamam o reusable:**

| Workflow | Branch | Environment |
|----------|--------|-------------|
| `deploy-production.yml` | `production` | `production` |
| `deploy-staging.yml` | `new_staging` | `staging` |
| `deploy-product-rh.yml` | `product/rh` | `product-rh` |

**Concurrency:** `deploy-*-${{ github.ref }}` com `cancel-in-progress: false` (não interrompe deploy em andamento).

### Fluxo

```
push branch oficial
      │
      ▼
┌─────────────┐     ┌─────────────────────────────────────┐
│  validate   │ OK  │  deploy (deploy-reusable.yml)         │
│  ~2–3 min   │────►│  1. hostgator-ssh (preflight)       │
└─────────────┘     │  2. composer + npm + minify         │
                    │  3. tar → SCP (retry) → SSH extract │
                    └──────────────┬──────────────────────┘
                                   ▼
                          deploy-server.sh
                          migrate + cache + symlinks
                                   ▼
                          smoke_test (curl HTTP 200)
```

Deploy **não inicia** se validate falhar.

### Host SSH canônico

> Domínios `*.uniowork.com.br` passam pelo Cloudflare — **não servem para SSH**.

| Onde | Valor |
|------|--------|
| `config/deploy-hostgator.defaults.env` | `DEPLOY_SSH_CANONICAL_HOST=br1136.hostgator.com.br` |
| Variable GitHub | `DEPLOY_SSH_CANONICAL_HOST` (staging, production, product-rh) |
| Action | `.github/actions/hostgator-ssh` |
| Alias SSH no job | `hg-deploy` |

Teste local: `ssh -p 2222 joabef36@br1136.hostgator.com.br`

### Secrets SSH (repositório — compartilhados)

| Secret | Uso |
|--------|-----|
| `DEPLOY_SSH_KEY` | Chave privada OpenSSH (**obrigatório**) |
| `DEPLOY_SSH_USER` | Usuário SSH (`joabef36`) |
| `DEPLOY_SSH_PORT` | Porta (`2222`) |
| `DEPLOY_SSH_HOST` | Legado; conexão usa host canônico do repo/variable |

Usados por todos os deploys e setups via `secrets: inherit`.

### Scripts de suporte

| Script | Função |
|--------|--------|
| `scripts/ci-ssh-setup.sh` | IPv4, anti-Cloudflare, `~/.ssh/config` |
| `scripts/ci-retry.sh` | Retry SCP/SSH |
| `scripts/ci-remote-extract.sh` | Extract remoto + `deploy-server.sh` |
| `scripts/deploy-server.sh` | Migrations, cache, symlinks, validação CSS |

### Secrets (environment — só Unio completa)

| Secret | Uso |
|--------|-----|
| `MAILBOX_JOABE_PASSWORD` | Caixa joabe@ (opcional) |
| `MAILBOX_UNIO_PASSWORD` | Caixa unio@ (opcional) |
| `PLATFORM_OWNER_PASSWORD` | Reset senha owner (opcional) |

### Variables por environment

| Environment | Variables |
|-------------|-----------|
| `production` | `DEPLOY_PATH`, `DEPLOY_PUBLIC_HTML`, `DEPLOY_SSH_CANONICAL_HOST` |
| `staging` | `DEPLOY_PATH`, `DEPLOY_PUBLIC_HTML`, `DEFAULT_URI`, `DEPLOY_SSH_CANONICAL_HOST` |
| `product-rh` | `DEPLOY_PATH`, `DEPLOY_PUBLIC_HTML`, `DEFAULT_URI`, `DEPLOY_SSH_CANONICAL_HOST` |

### Homolog de produto (`product/rh`)

- Workflow: `deploy-product-rh.yml` → `deploy-reusable.yml`
- Setup manual: `setup-product-rh.yml` (usa mesma action `hostgator-ssh`)
- Novo produto: `bash scripts/scaffold-product-homolog.sh <slug> [--apply-server] [--apply-github]`

---

## Diagrama — fluxo completo recomendado

```
Desenvolver
    │
    ├─► push production ──► CI ──► Deploy ──► uniowork.com.br
    │
    ├─► PR feature/* → production ──► CI no PR ──► merge ──► CI + Deploy
    │
    └─► sync-branches.sh ──► espelhos alinhados (sem CI extra)
```

---

## Falhas comuns

| Sintoma | Ação |
|---------|------|
| PHPUnit 500 Twig block | Block deve estar definido em `base.html.twig` (`{% block page_scroll_mode %}flow{% endblock %}`) |
| PHPStan | `composer phpstan:baseline` ou corrigir |
| `Network is unreachable` no deploy | Host SSH era domínio Cloudflare — conferir log `SSH target: … @ 50.6.x.x`; ver [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) |
| Deploy SSH / `Permission denied` | Verificar chave autorizada no cPanel; `DEPLOY_SSH_KEY` com newline |
| Site com CSS antigo | Hard refresh; bump `?v=` em `base.html.twig` |
| Muitos runs na Actions | Push em `product/*` não dispara CI; só branches oficiais |

---

## O que ainda não existe no Actions

- Deploy automático para `new_staging2` (se criada)
- Deploy automático para outros `product/*` além de `product/rh` (use `scaffold-product-homolog.sh`)
- Notificação Slack/email em falha

**Staging (`new_staging`):** deploy automático ativo via `deploy-staging.yml` → ver [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md).

Ver também: [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) · [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md)
