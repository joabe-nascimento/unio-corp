# GitLab — espelho, CI/CD e deploy HostGator

Guia para deixar o projeto **unio-corp** no GitLab alinhado ao GitHub, com pipeline equivalente.

Repositório GitLab: `https://gitlab.com/joabe-nascimento/unio-corp`

---

## Visão geral

| Caminho | O que faz |
|---------|-----------|
| **GitHub → GitLab (sync)** | Workflow `sync-gitlab-mirror.yml` após push |
| **Pull mirror (opcional)** | GitLab puxa do GitHub periodicamente |
| **GitLab CI** | `validate` sempre; **deploy** só com `UNIO_GITLAB_DEPLOY=1` |

> **Evite deploy duplo:** enquanto GitHub Actions faz deploy na HostGator, deixe `UNIO_GITLAB_DEPLOY` **desligado** no GitLab. O job `validate` roda igual — serve como segunda validação.

---

## Parte 1 — Espelho automático (GitHub → GitLab)

### Opção A — Workflow no GitHub (recomendado, plano Free)

1. GitLab → **Settings → Access tokens** → token **`unio-git-push`** (scope `write_repository`)
2. GitHub → repo **unio-corp** → **Settings → Secrets → Actions**
3. Novo secret: **`GITLAB_PROJECT_TOKEN`** = token do passo 1
4. Push em `production` / `new_staging` dispara **Sync GitLab mirror**

Arquivo: `.github/workflows/sync-gitlab-mirror.yml`

### Opção B — Pull mirror no GitLab

**Settings → Repository → Mirroring repositories → Add new**

| Campo | Valor |
|-------|--------|
| Git repository URL | `https://github.com/joabe-nascimento/unio-corp.git` |
| Mirror direction | **Pull** |
| Authentication | GitHub PAT (classic) com scope **`repo`** |
| Update interval | 5 min (ou o mínimo permitido) |

> Pull mirror de repo **privado** no GitLab.com pode exigir plano pago. Use a **Opção A** se não tiver mirror pull.

---

## Parte 2 — Variáveis CI/CD no GitLab

**Settings → CI/CD → Variables** (projeto unio-corp)

### Obrigatórias (para deploy)

| Variável | Tipo | Valor |
|----------|------|--------|
| `DEPLOY_SSH_KEY` | Variable, **Masked** | Conteúdo de `~/.ssh/unio_deploy` (chave privada) |
| `DEPLOY_SSH_USER` | Variable | `joabef36` |
| `DEPLOY_SSH_PORT` | Variable | `2222` |

### Recomendadas

| Variável | Valor |
|----------|--------|
| `DEPLOY_SSH_CANONICAL_HOST` | `br1136.hostgator.com.br` |
| `UNIO_GITLAB_DEPLOY` | `1` — **só quando quiser deploy pelo GitLab** |

### Opcionais (produção)

| Variável | Uso |
|----------|-----|
| `MAILBOX_JOABE_PASSWORD` | deploy-server |
| `MAILBOX_UNIO_PASSWORD` | deploy-server |
| `PLATFORM_OWNER_PASSWORD` | deploy-server |

---

## Parte 3 — Pipeline GitLab

Arquivo: **`.gitlab-ci.yml`**

```
push (production | new_staging)
    ↓
validate  — composer validate:ci (sempre)
    ↓
deploy    — HostGator SSH (se UNIO_GITLAB_DEPLOY=1)
    ↓
smoke     — curl HTTP 200
```

| Branch | Deploy | Smoke |
|--------|--------|-------|
| `new_staging` | automático (se `UNIO_GITLAB_DEPLOY=1`) | sim |
| `production` | **manual** no GitLab UI (se `UNIO_GITLAB_DEPLOY=1`) | após deploy manual |

Scripts: `.gitlab/ci/*.sh` (reutilizam `scripts/ci-ssh-setup.sh`, `ci-retry.sh`, etc.)

---

## Parte 4 — Ativar deploy só pelo GitLab (quando estiver pronto)

1. Confirme pipeline **validate** verde no GitLab
2. Cadastre variáveis SSH (Parte 2)
3. **Settings → CI/CD → Variables** → `UNIO_GITLAB_DEPLOY` = **`1`**
4. Desative deploy no GitHub (renomeie ou `workflow_dispatch` only em `deploy-production.yml` / `deploy-staging.yml`)
5. Push staging → deploy automático GitLab; prod → botão **Play** em `deploy_production`

---

## Parte 5 — Rotina recomendada (transição)

### Fase atual (GitHub deploy + GitLab espelho + validate)

```powershell
git push origin new_staging    # deploy staging GitHub + sync GitLab + validate GitLab
git push origin production     # deploy prod GitHub + sync GitLab + validate GitLab
```

Secret GitHub: **`GITLAB_PROJECT_TOKEN`**

### Fase futura (GitLab deploy)

- `UNIO_GITLAB_DEPLOY=1`
- GitHub: só sync + validate (ou desligar deploy workflows)

---

## Conferir

1. **GitLab → Build → Pipelines** — job `validate` verde após push
2. **GitLab → Code** — branch `production` = mesmo commit do GitHub
3. Com `UNIO_GITLAB_DEPLOY=1`: deploy staging → smoke → URLs staging OK

Ver também: [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md)
