# Deploy automático — GitHub Actions → HostGator (SSH)

Push nas branches oficiais dispara build no GitHub e envio para o servidor via SSH.

Repositório: `https://github.com/joabe-nascimento/unio-corp`

---

## Branches e ambientes

| Branch | Workflow | URL | Pasta no servidor |
|--------|----------|-----|-------------------|
| `production` | Deploy Production | https://uniowork.com.br | `/home2/joabef36/unio` |
| `new_staging` | Deploy Staging | https://staging.uniowork.com.br | `/home2/joabef36/unio-staging` |
| `product/rh` | Deploy Product RH | https://rh.uniowork.com.br | `/home2/joabef36/unio-rh` |

Cada push nessas branches: **validate** → **deploy** → **smoke test** (URLs HTTP 200).

---

## Visão do fluxo

```
PC: git push origin new_staging | production | product/rh
        ↓
GitHub Actions
  1. validate-reusable (PHPUnit, PHPStan, assets…)
  2. deploy-reusable:
       a. Setup SSH HostGator (preflight + IPv4)
       b. composer install --no-dev
       c. npm ci + vendor:sync + minify CSS
       d. tar.gz → SCP → /tmp/
       e. SSH: extract + scripts/deploy-server.sh
  3. smoke test (curl /login, /termos…)
        ↓
Site atualizado
```

**Não sobrescreve no servidor:** `.env.local`, `var/log/`, `public/uploads/`.

---

## Host SSH — regra crítica

> **Nunca use o domínio público (`uniowork.com.br`, `staging.uniowork.com.br`) como host SSH.**  
> Esses domínios passam pelo **Cloudflare** (CDN). SSH na porta 2222 **não funciona** via CDN → erro `Network is unreachable`.

### Host canônico (versionado no repo)

Arquivo: `config/deploy-hostgator.defaults.env`

```env
DEPLOY_SSH_CANONICAL_HOST=br1136.hostgator.com.br
DEPLOY_SSH_DEFAULT_USER=joabef36
DEPLOY_SSH_DEFAULT_PORT=2222
```

O pipeline **sempre conecta** nesse hostname (ou override via variable GitHub). O secret `DEPLOY_SSH_HOST` é legado — se apontar para domínio público, é ignorado com aviso no log.

### Componentes do pipeline SSH

| Arquivo | Função |
|---------|--------|
| `.github/actions/hostgator-ssh/action.yml` | Action reutilizável — setup de chave + preflight |
| `scripts/ci-ssh-setup.sh` | Resolve IPv4, bloqueia Cloudflare, cria alias `hg-deploy` |
| `scripts/ci-retry.sh` | Retry com exit code correto (3 tentativas, 15s) |
| `scripts/ci-remote-extract.sh` | Extract + `deploy-server.sh` no servidor |

### Testar SSH do seu PC

```powershell
ssh -i "$env:USERPROFILE\.ssh\unio_deploy" -p 2222 joabef36@br1136.hostgator.com.br
```

Se entrar no shell, SSH está OK. Digite `exit` para sair.

---

## Parte 1 — HostGator (setup único)

### 1.1 Ativar SSH

1. cPanel → **SSH Access** / **Acesso SSH**
2. Se não aparecer, abra chamado na HostGator (planos Business/Turbo costumam ter)
3. Anote no cPanel o **hostname do servidor** (ex.: `br1136.hostgator.com.br`) — **não** o domínio do site

### 1.2 Pastas no servidor

```
/home2/joabef36/unio/                    ← produção (Symfony)
/home2/joabef36/public_html/             ← document root produção
/home2/joabef36/unio-staging/             ← staging
/home2/joabef36/staging.uniowork.com.br/  ← document root staging
/home2/joabef36/unio-rh/                 ← homolog RH
/home2/joabef36/rh.uniowork.com.br/       ← document root RH
```

### 1.3 `.env.local` no servidor (obrigatório)

Deve existir **antes** do primeiro deploy. O GitHub **nunca** envia `.env` / `.env.local`.

### 1.4 Symlinks de estáticos

O `scripts/deploy-server.sh` recria symlinks `css`, `js`, `images`, `vendor` do document root para `public/` da app.

---

## Parte 2 — Chave SSH de deploy (uma vez)

### 2.1 Gerar par de chaves

```powershell
ssh-keygen -t ed25519 -C "github-deploy-unio" -f "$env:USERPROFILE\.ssh\unio_deploy" -N '""'
```

- `unio_deploy` → **privada** (secret `DEPLOY_SSH_KEY`)
- `unio_deploy.pub` → **pública** (cPanel → SSH Keys → Import → Authorize)

---

## Parte 3 — Secrets e variables no GitHub

Settings → **Secrets and variables** → **Actions**

### 3.1 Secrets (repositório — compartilhados)

| Nome | Valor | Obrigatório |
|------|--------|-------------|
| `DEPLOY_SSH_KEY` | Conteúdo inteiro de `unio_deploy` (com `-----BEGIN…`) | Sim |
| `DEPLOY_SSH_USER` | `joabef36` | Sim |
| `DEPLOY_SSH_PORT` | `2222` | Sim |
| `DEPLOY_SSH_HOST` | Legado; pode ser qualquer valor — **conexão usa host canônico** | Não crítico |

### 3.2 Variables (repositório ou por environment)

| Nome | Valor | Onde |
|------|--------|------|
| `DEPLOY_SSH_CANONICAL_HOST` | `br1136.hostgator.com.br` | Repo + environments `staging`, `production`, `product-rh` |
| `DEPLOY_PATH` | Caminho da app no servidor | Por environment |
| `DEPLOY_PUBLIC_HTML` | Document root | Por environment |
| `DEFAULT_URI` | URL pública do ambiente | Homologs |

**Defaults por environment:**

| Environment | `DEPLOY_PATH` | `DEPLOY_PUBLIC_HTML` |
|-------------|---------------|----------------------|
| `production` | `/home2/joabef36/unio` | `/home2/joabef36/public_html` |
| `staging` | `/home2/joabef36/unio-staging` | `/home2/joabef36/staging.uniowork.com.br` |
| `product-rh` | `/home2/joabef36/unio-rh` | `/home2/joabef36/rh.uniowork.com.br` |

Se mudar de servidor HostGator, altere **`config/deploy-hostgator.defaults.env`** ou a variable `DEPLOY_SSH_CANONICAL_HOST` — não é necessário reconfigurar secrets de host.

### 3.3 Secrets por environment (opcionais)

| Environment | Secrets |
|-------------|---------|
| `production` | `MAILBOX_JOABE_PASSWORD`, `MAILBOX_UNIO_PASSWORD`, `PLATFORM_OWNER_PASSWORD` |
| `staging` | `APP_SECRET_STAGING`, `DATABASE_URL_STAGING` (só setup inicial) |
| `product-rh` | `APP_SECRET_RH`, `DATABASE_URL_RH` (só setup inicial) |

---

## Parte 4 — Workflows

| Workflow | Arquivo | Trigger |
|----------|---------|---------|
| Deploy Production | `deploy-production.yml` | push `production` |
| Deploy Staging | `deploy-staging.yml` | push `new_staging` |
| Deploy Product RH | `deploy-product-rh.yml` | push `product/rh` |
| Deploy (core) | `deploy-reusable.yml` | `workflow_call` |
| Setup Staging | `setup-staging.yml` | manual |
| Setup Product RH | `setup-product-rh.yml` | manual |
| Validate | `validate-reusable.yml` | CI + pré-deploy |

### Concurrency

Deploys usam `cancel-in-progress: false` — um push novo **não cancela** deploy em andamento (evita servidor inconsistente).

### Ordem no deploy

1. **Setup SSH HostGator** — preflight antes do build (~10s se rede OK)
2. Build (composer + npm)
3. Upload tar.gz com retry
4. Extract + `deploy-server.sh` com retry
5. Smoke test

Acompanhe: `https://github.com/joabe-nascimento/unio-corp/actions`

---

## Parte 5 — Rotina do dia a dia

### Staging (homolog principal)

```powershell
git checkout new_staging
# ... alterações ...
git add .
git commit -m "feat: minha alteração"
git push origin new_staging
```

→ Deploy Staging (~2–4 min) → https://staging.uniowork.com.br

### Produção

```powershell
git checkout production
git merge new_staging   # ou main, conforme fluxo da equipe
git push origin production
```

→ Deploy Production + GitHub Release automática

### Re-run manual

Actions → workflow com falha → **Re-run all jobs**

---

## Parte 6 — Conferir se deu certo

1. GitHub Actions → job verde (validate + deploy + smoke)
2. Abrir URL do ambiente (`/login`)
3. Se falhar: baixar artifact **deploy-report-…** ou ver step summary

Relatório no servidor: `{DEPLOY_PATH}/var/log/deploy-report.txt`

---

## Problemas comuns

| Erro | Causa | Solução |
|------|--------|---------|
| `Network is unreachable` | SSH tentou Cloudflare/domínio público | Usar `br1136.hostgator.com.br` (já automático desde jul/2026) |
| `Permission denied (publickey)` | Chave não autorizada no cPanel | Import + Authorize em SSH Keys |
| `DEPLOY_SSH_KEY` inválida | Chave truncada ou CRLF | Recolar com `printf`; workflow faz `tr -d '\r'` |
| `ci-retry: falhou apos 3 tentativas` | Rede GitHub→HostGator instável | Re-run; verificar firewall HostGator |
| Host SSH aponta para CDN | Secret `DEPLOY_SSH_HOST=uniowork.com.br` | Ignorado pelo pipeline; conferir log `SSH target: … @ 50.6.x.x` |
| Site 500 após deploy | `.env.local` ausente ou migration | Ver deploy-report; SSH manual no servidor |
| CSS antigo | Cache browser ou symlink | Hard refresh; bump `?v=` em assets |

### Migrar host SSH (novo servidor)

1. Atualizar `config/deploy-hostgator.defaults.env`
2. Atualizar variable `DEPLOY_SSH_CANONICAL_HOST` nos 3 environments
3. Commit + push → deploy testa preflight automaticamente

---

## Pipeline avançado (jul/2026)

| Recurso | O que faz |
|---------|-----------|
| **Preflight SSH** | Falha cedo se servidor inacessível |
| **IPv4 forçado** | Evita `Network is unreachable` por rota IPv6 |
| **Retry SCP/SSH** | 3 tentativas com backoff |
| **Smoke test** | curl em `/`, `/login`, `/termos`, `/privacidade` |
| **GitHub Release** | Tag `deploy-N` após prod OK |
| **Backup DB** | `mysqldump` antes de migration (últimos 7) |
| **Artifact deploy-report** | Log do `deploy-server.sh` por 14 dias |

Backups: `~/unio/var/backups/db/pre-migrate-*.sql.gz`

---

## Segurança

- Nunca commite `.env.local` ou chaves SSH no Git
- Rotacione `DEPLOY_SSH_KEY` se a chave vazar
- Domínio público ≠ host SSH
- Apague scripts temporários em `public_html` após emergências

---

## Resumo

| Quando | O que fazer |
|--------|-------------|
| Setup inicial | SSH no cPanel + chave + secrets + `.env.local` + variables |
| Staging | `git push origin new_staging` |
| Produção | `git push origin production` |
| Novo servidor | Editar `config/deploy-hostgator.defaults.env` |
| Upload manual | Só emergência — ver [DEPLOY_AGORA.md](DEPLOY_AGORA.md) |

Ver também: [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md) · [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md)
