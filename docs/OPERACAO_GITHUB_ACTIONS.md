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

## 3. `deploy-production.yml` — HostGator

**Trigger:** `push` apenas em `production`  
**Concurrency:** `deploy-production` (cancela deploy anterior)

### Fluxo

```
push production
      │
      ▼
┌─────────────┐     ┌─────────────┐
│  validate   │ OK  │   deploy    │
│  ~2–3 min   │────►│  ~2–3 min   │
└─────────────┘     └──────┬──────┘
                           ▼
                 deploy-server.sh
                 migrate + cache + rsync
```

Deploy **não inicia** se validate falhar.

### Secrets (environment `production`)

| Secret | Uso |
|--------|-----|
| `DEPLOY_SSH_HOST` | Host HostGator |
| `DEPLOY_SSH_USER` | Usuário SSH |
| `DEPLOY_SSH_KEY` | Chave privada OpenSSH |
| `DEPLOY_SSH_PORT` | Porta (ex.: 2222) |
| `MAILBOX_JOABE_PASSWORD` | Caixa joabe@ (opcional) |
| `PLATFORM_OWNER_PASSWORD` | Reset senha owner (opcional) |

### Variables

| Variable | Exemplo |
|----------|---------|
| `DEPLOY_PATH` | `/home2/joabef36/unio` |
| `DEPLOY_PUBLIC_HTML` | `/home2/joabef36/public_html` |

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
| Deploy SSH | Verificar `DEPLOY_SSH_KEY`, newline, porta |
| Site com CSS antigo | Hard refresh; bump `?v=` em `base.html.twig` |
| Muitos runs na Actions | Verificar se push foi em `product/*` antes da otimização; hoje só oficiais disparam CI |

---

## O que ainda não existe no Actions

- Deploy automático para `new_staging` / `new_staging2`
- Notificação Slack/email em falha

Ver também: [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) · [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md)
