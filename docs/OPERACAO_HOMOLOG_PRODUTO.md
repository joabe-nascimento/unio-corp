# Homolog por produto — piloto RH e infraestrutura (jul/2026)

Guia completo do que foi implementado para homologação isolada por produto, começando pelo **RH** em `rh.uniowork.com.br`.

Relacionados: [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) · [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md) · [OPERACAO_CLIENTE_MATRIZ.md](OPERACAO_CLIENTE_MATRIZ.md)

---

## Visão geral

| Instância | Branch deploy | URL | Isolamento |
|-----------|---------------|-----|------------|
| **Unio (prod)** | `production` | `https://uniowork.com.br` | Tenant por empresa, mesmo banco |
| **RH (homolog)** | `product/rh` | `https://rh.uniowork.com.br` | Pasta + banco + `.env` + branch próprios |

```
Desenvolvimento RH
    │
    ├─► git push product/rh
    │       └─► Deploy Product RH (validate + deploy)
    │               └─► rh.uniowork.com.br  (~/unio-rh)
    │
    └─► merge product/rh → production + push
            └─► Deploy Production
                    └─► uniowork.com.br  (~/unio)
```

**Regra:** trabalho na branch `product/rh` **fica no homolog RH** até você fazer merge em `production`.

---

## O que foi feito no servidor (HostGator)

Conta: **Plano M** · usuário `joabef36` · SSH porta `2222`

| Item | Valor |
|------|--------|
| Subdomínio | `rh.uniowork.com.br` (criado via cPanel API) |
| App Symfony | `/home2/joabef36/unio-rh` |
| Document root | `/home2/joabef36/rh.uniowork.com.br` |
| Banco MySQL | `joabef36_unio_rh` (usuário dedicado) |
| `.env` + `.env.local` | Só no servidor — nunca vão no tar de deploy |
| SSL | AutoSSL (grátis no Plano M) |

### Arquivos no document root

- `index.php` → aponta para `../unio-rh/vendor/autoload_runtime.php`
- `.htaccess` → rewrite Symfony (copiado do padrão `public_html`)
- Symlinks: `css`, `js`, `images`, `vendor` → `unio-rh/public/`

### Bootstrap de banco vazio

Em instância nova, `doctrine:migrations:migrate` sozinho **falha** (tabela `user` não é criada pelas migrations antigas).

O `scripts/deploy-server.sh` detecta banco sem schema e roda:

1. `doctrine:schema:create`
2. `doctrine:migrations:version --add --all`
3. **Sem seeds demo** — igual staging/produção (`skip_unio_platform_steps: false`); contas reais via `PLATFORM_OWNER` / cadastro manual.

Steps de plataforma (e-mail, `PLATFORM_OWNER`, mailboxes) **rodam** no deploy RH, como em staging.

---

## Git e GitHub

### Branch

| Branch | Deploy automático | CI no push |
|--------|-------------------|------------|
| `production` | `uniowork.com.br` | Sim (`ci.yml` + deploy) |
| `product/rh` | `rh.uniowork.com.br` | Só dentro do **Deploy Product RH** (sem CI duplicado) |
| Outras `product/*` | Não (espelho) | Não — validam via PR |

### Environment GitHub: `product-rh`

| Tipo | Nome | Valor |
|------|------|--------|
| Variable | `DEPLOY_PATH` | `/home2/joabef36/unio-rh` |
| Variable | `DEPLOY_PUBLIC_HTML` | `/home2/joabef36/rh.uniowork.com.br` |
| Variable | `DEFAULT_URI` | `https://rh.uniowork.com.br` |

### Secrets SSH (nível do **repositório**)

Compartilhados por Production e todos os homologs:

| Secret | Exemplo | Notas |
|--------|---------|--------|
| `DEPLOY_SSH_KEY` | chave `unio_deploy` | Obrigatório |
| `DEPLOY_SSH_USER` | `joabef36` | |
| `DEPLOY_SSH_PORT` | `2222` | |
| `DEPLOY_SSH_HOST` | legado | **Não usar domínio público.** Pipeline usa `br1136.hostgator.com.br` |

Variable **`DEPLOY_SSH_CANONICAL_HOST`** = `br1136.hostgator.com.br` (repo + environments).  
Detalhes: [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) · `config/deploy-hostgator.defaults.env`

Secrets **só da Unio** (environment `production`): `MAILBOX_*`, `PLATFORM_OWNER_PASSWORD`.

---

## Workflows e scripts

### Workflows

| Arquivo | Gatilho | Função |
|---------|---------|--------|
| `.github/workflows/deploy-reusable.yml` | `workflow_call` | Deploy genérico HostGator (SSH, tar, `deploy-server.sh`) |
| `.github/workflows/deploy-production.yml` | push `production` | Prod Unio → chama reusable |
| `.github/workflows/deploy-product-rh.yml` | push `product/rh` | Homolog RH → chama reusable |
| `.github/workflows/deploy-staging.yml` | push `new_staging` | Staging Unio → chama reusable |
| `.github/workflows/setup-staging.yml` | manual | Reparar pastas/index staging |
| `.github/workflows/setup-product-rh.yml` | manual | Reparar pastas/index/.env no servidor RH |
| `.github/workflows/templates/deploy-product.yml` | — | Template para novos produtos |

### Scripts

| Script | Uso |
|--------|-----|
| `scripts/setup-product-env-server.sh` | Setup inicial: pastas, `index.php`, `.htaccess`, `.env` stub, `.env.local` |
| `scripts/scaffold-product-homolog.sh` | Scaffold do próximo produto (workflow + `deploy-branches.txt` + opcional servidor/GitHub) |
| `scripts/deploy-server.sh` | Pós-deploy: bootstrap DB, migrate, cache, symlinks, steps Unio (condicional) |
| `scripts/sync-branches.sh` | Espelha branches com `production` — **ignora** homologs |

### Config

| Arquivo | Função |
|---------|--------|
| `config/deploy-branches.txt` | Branches com deploy próprio — **nunca** resetadas pelo sync |
| `.env.product-rh.example` | Modelo de `.env.local` para homolog RH |
| `.gitattributes` | `*.sh text eol=lf` — evita CRLF quebrando deploy no Windows |

---

## Fluxo do dia a dia (RH)

```bash
git checkout product/rh
git pull origin product/rh

# ... editar, validar local ...
composer validate:pre-push

git add .
git commit -m "feat(rh): descricao"
git push origin product/rh
# → GitHub Actions: Deploy Product RH → rh.uniowork.com.br
```

### Trazer correções da prod para o RH

```bash
git checkout product/rh
git merge production
git push origin product/rh
```

### Publicar RH na Unio oficial

```bash
git checkout production
git pull origin production
git merge product/rh
git push origin production
# → uniowork.com.br
```

### Sync de espelhos (seguro)

```bash
bash scripts/sync-branches.sh production
```

`product/rh` está em `config/deploy-branches.txt` → **ignorada**. Commits exclusivos do RH **não são apagados**.

---

## Teste e acesso

| Item | Valor |
|------|--------|
| URL | https://rh.uniowork.com.br/login |
| Login seed | `gestor@unio.dev` / `unio123` |
| Módulo | `/rh` após login |

### O que o usuário vê no menu

O subdomínio isola **dados e deploy**, não o menu automaticamente.

- `gestor@unio.dev` (seed) tem grants amplos → vê RH, Pessoas, Engenharia, Talentos, etc.
- Isso é **ok para homolog da equipe dev**.
- Para demo “só RH”: criar usuário com grants apenas `product_rh` / `hub_operacoes` → `rh`.

---

## Eficiências implementadas (refactor jul/2026)

| Melhoria | Benefício |
|----------|-----------|
| `product/rh` fora do `ci.yml` push | ~3 min a menos por push (sem validate duplicado) |
| `config/deploy-branches.txt` + sync | RH não perde commits no `sync-branches.sh` |
| `deploy-reusable.yml` | Novo produto = ~25 linhas de workflow, não 150 |
| SSH no repositório | Não duplicar 4 secrets por environment |
| `scaffold-product-homolog.sh` | Próximo produto em minutos |
| `.gitattributes` LF em `.sh` | Deploy estável no Windows |

---

## Adicionar outro produto (ex.: Pessoas)

```bash
# 1. Scaffold (gera workflow + deploy-branches.txt)
bash scripts/scaffold-product-homolog.sh pessoas

# 2. Opcional: criar subdomínio, banco e pastas no servidor
bash scripts/scaffold-product-homolog.sh pessoas --apply-server

# 3. Opcional: environment GitHub (vars de path)
bash scripts/scaffold-product-homolog.sh pessoas --apply-github

# 4. Commit, push production, criar branch
git checkout -b product/pessoas production
git push origin product/pessoas
```

Resultado esperado:

- URL: `https://pessoas.uniowork.com.br`
- App: `~/unio-pessoas`
- Branch: `product/pessoas` em `deploy-branches.txt`
- Workflow: `.github/workflows/deploy-product-pessoas.yml`

---

## Plano HostGator — limites práticos

| Recurso | Plano M |
|---------|---------|
| Subdomínios | Ilimitados |
| Bancos MySQL | Ilimitados (criação) |
| Disco | 100 GB |
| Conexões MySQL simultâneas | 25 |

Cada homolog consome pasta + banco + deploy. **3–5 homologs** é confortável; não criar subdomínios vazios “por precaução”.

---

## O que **não** fazer

| Ação | Risco |
|------|--------|
| Rodar `sync-branches.sh` esperando alinhar `product/rh` | Branch é ignorada — correto |
| Commitar `.env.local` ou senhas de banco | Segurança |
| Assumir que `rh.uniowork.com.br` esconde outros módulos no menu | Só grants controlam visibilidade |
| Usar `app:seed-*` em cron de prod | Bloqueado — só dev/homolog com `--allow-prod` |

---

## Mapa de arquivos tocados (implementação completa)

```
.github/workflows/
  deploy-reusable.yml          # novo — deploy compartilhado
  deploy-production.yml        # simplificado
  deploy-product-rh.yml        # homolog RH
  setup-product-rh.yml         # setup manual servidor
  templates/deploy-product.yml # template scaffold
  ci.yml                       # product/rh removido do push

config/
  deploy-branches.txt          # product/rh

scripts/
  setup-product-env-server.sh  # setup servidor produto
  scaffold-product-homolog.sh  # scaffold novos produtos
  deploy-server.sh             # bootstrap DB, skip Unio, .env stub
  sync-branches.sh             # respeita deploy-branches.txt

.env.product-rh.example
.gitattributes
docs/OPERACAO_HOMOLOG_PRODUTO.md  # este arquivo
```

---

## Commits de referência (jul/2026)

| Commit | Conteúdo |
|--------|----------|
| `ee29934` | Deploy piloto RH: workflows, setup, `deploy-server` skip Unio |
| `870a8b8` | Bootstrap banco vazio + stub `.env` |
| `b9869b2` | `.htaccess` no document root de produto |
| `dd63af5` | Deploy reutilizável, sync seguro, scaffold, SSH no repo |

---

## Resumo em uma frase

> **`product/rh` + `rh.uniowork.com.br` = laboratório isolado do RH; merge em `production` quando estiver pronto para a Unio.**
