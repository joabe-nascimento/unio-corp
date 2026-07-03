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
      ci.yml            deploy-production.yml    (futuro)
   push feature/*         push production
   push product/*         validate → deploy
   PR → main/production
```

---

## 1. `validate-reusable.yml` — fonte da verdade

**Tipo:** reusable (`workflow_call`)  
**Não dispara sozinho** — é chamado por outros workflows.

### O que executa

| Step | Comando / ação |
|------|----------------|
| MariaDB 10.11 | Service container (banco limpo) |
| Composer install | `composer install` |
| Validação completa | `composer validate:ci` |

### Dentro de `validate:ci` (`scripts/validate-before-push.sh`)

| # | Check |
|---|--------|
| 1 | `composer validate` |
| 2 | `lint:container` |
| 3 | `lint:yaml config` |
| 4 | `lint:twig templates` |
| 5 | Banco: drop/create/schema + `app:seed-users` + `app:seed-product-grants --force` |
| 6 | `app:validate-system` (permissões, rotas, seeds) |
| 7 | PHPStan (exceto se `GIT_BRANCH=production`) |
| 8 | PHPUnit (`APP_ENV=test`) |
| 9 | `php bin/minify-css.php` |
| 10 | `npm ci` + `npm run vendor:sync` |

### Input

| Parâmetro | Uso |
|-----------|-----|
| `git_branch` | Define regras (ex.: pular PHPStan em `production`) |

---

## 2. `ci.yml` — integração contínua

**Trigger:**

```yaml
on:
  push:
    branches: [main, production, new_staging, new_staging2, feature/**, product/**]
  pull_request:
    branches: [main, production, new_staging, new_staging2, product/**]
```

**Job:**

```yaml
jobs:
  validate:
    uses: ./.github/workflows/validate-reusable.yml
    with:
      git_branch: ${{ github.ref_name }}
```

| Branch pushada | O que roda |
|----------------|------------|
| `feature/*` | Validate completo (com PHPStan) |
| `product/*` | Validate completo |
| `production` | Validate (PHPStan **pulado** no script) |
| `main`, `new_staging*` | Validate completo |
| PR para `main` / `production` | Validate completo |

**Resultado esperado:** job `validate` verde antes de merge.

---

## 3. `deploy-production.yml` — HostGator

**Trigger:** `push` apenas em `production`  
**Concurrency:** `deploy-production` (cancela deploy anterior se novo push)

### Job 1: `validate`

```yaml
validate:
  uses: ./.github/workflows/validate-reusable.yml
  with:
    git_branch: production
```

Deploy **não inicia** se validate falhar.

### Job 2: `deploy` (`needs: validate`)

| Step | Ação |
|------|------|
| Checkout | Código da branch |
| Composer | `install --no-dev --no-scripts` |
| Node | `npm ci` + `vendor:sync` |
| **Minify CSS** | `php bin/minify-css.php` |
| SSH | Configura chave (`DEPLOY_SSH_KEY`) |
| Tar + SCP | Arquivo em `$RUNNER_TEMP`, upload para `/tmp/deploy.tar.gz` |
| SSH extract | `tar -xzf` em `DEPLOY_PATH` |
| Post-deploy | `bash scripts/deploy-server.sh` |

### Secrets (GitHub → Environment `production`)

| Secret | Uso |
|--------|-----|
| `DEPLOY_SSH_HOST` | Host HostGator |
| `DEPLOY_SSH_USER` | Usuário SSH |
| `DEPLOY_SSH_KEY` | Chave privada (OpenSSH, com newline) |
| `DEPLOY_SSH_PORT` | Porta (ex.: 2222) |

### Variables

| Variable | Exemplo |
|----------|---------|
| `DEPLOY_PATH` | `/home2/joabef36/unio` |
| `DEPLOY_PUBLIC_HTML` | `/home2/joabef36/public_html` |

### Exclusões do tar

`.git`, `.github`, `node_modules`, `.env*`, `var/cache`, `var/log`, `public/uploads`, `tests`, `docs`, etc.

---

## Diagrama do deploy bem-sucedido (último)

```
push production (7abf2c2)
        │
        ▼
┌───────────────┐     ┌───────────────┐
│   validate    │ OK  │    deploy     │
│  ~2m30s       │────►│   ~3m30s      │
│  MariaDB+CI   │     │ tar→scp→ssh   │
└───────────────┘     └───────┬───────┘
                              ▼
                    deploy-server.sh
                    migrate + cache + rsync
```

Runs de referência:

- Validate + Deploy: buscar em Actions filtro branch `production`, commit `7abf2c2`

---

## Falhas comuns no Actions

| Sintoma | Onde olhar | Ação |
|---------|------------|------|
| Validate falha PHPStan | Log step "Validate" | `composer phpstan:baseline` ou corrigir código |
| Validate falha PHPUnit | Log PHPUnit | Rodar `php bin/phpunit` local |
| Validate falha `app:validate-system` | Permissões/seeds | `app:seed-product-grants --force` local |
| Deploy falha SSH | "Setup SSH key" | Verificar secret, newline, porta |
| Deploy OK mas site antigo | Browser cache | Ctrl+Shift+R; verificar `?v=` no CSS |
| Layout quebrado em prod | CSS min desatualizado | Garantir `minify-css.php` no deploy (já incluso) |

---

## O que **não** existe ainda no Actions

- Deploy automático para `new_staging` / `new_staging2`
- Deploy em `main`
- Notificação Slack/email em falha (pode adicionar depois)

Para staging, hoje: deploy manual ou estender workflow copiando o padrão de `deploy-production.yml`.
