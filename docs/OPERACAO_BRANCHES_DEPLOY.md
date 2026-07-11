# Branches e deploy — como funciona a partir de hoje

Guia operacional atualizado (jul/2026) após otimização do CI e espelhamento de branches.  
Homolog por produto (piloto RH): [OPERACAO_HOMOLOG_PRODUTO.md](OPERACAO_HOMOLOG_PRODUTO.md) · Branches: [BRANCHES.md](BRANCHES.md) · Workflows: [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md)

---

## Mapa rápido

```
┌──────────────────────────────────────────────────────────────────────┐
│  DESENVOLVIMENTO                                                       │
│                                                                        │
│  Opção A — direto (mais usado hoje)                                   │
│    production  ──push──►  CI + Deploy Production  ──►  HostGator      │
│                                                                        │
│  Opção B — com revisão                                                │
│    feature/* ou product/*  ──PR──►  production  ──►  CI + Deploy    │
│                                                                        │
│  Espelhar branches (sem disparar CI extra)                            │
│    bash scripts/sync-branches.sh production                           │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Papéis das branches

| Branch | Deploy | CI no push | Uso |
|--------|--------|------------|-----|
| **`production`** | Sim (automático) | Sim | Código no ar — `uniowork.com.br` |
| **`product/rh`** | Sim → `rh.uniowork.com.br` | Sim | Homolog isolada do módulo RH |
| **`main`** | Não | Sim | Tronco / referência estável |
| **`new_staging`** | Sim → `staging.uniowork.com.br` | Sim | Homologação principal (Unio completa) |
| **`new_staging2`** | Não* | Sim | Segundo staging (QA paralelo — deploy futuro) |
| **`uniosaude`** | Manual (PC) | **Não** (jul/2026) | Unio Saúde — `uniosaude.uniowork.com.br` |
| **`product/*`** (exceto `rh`) | Não | **Não** | Espelho por módulo (organização) |
| **`feature/*`** | Não | **Não** | Espelho por feature (organização) |

\* Deploy automático: `production`, `product/rh` (homolog RH), `new_staging` (homolog Unio).  
**`uniosaude`:** deploy manual via `scripts/deploy-uniosaude-manual.ps1` — ver [UNIOSAUDE_DEPLOY_OPERACAO.md](UNIOSAUDE_DEPLOY_OPERACAO.md).

**HEAD atual de referência:** `production` @ `3f2a3b1`

---

## Fluxo do dia a dia

### 1. Publicar em produção (caminho direto)

```bash
git checkout production
git pull origin production
# ... editar, validar localmente ...
composer validate:pre-push    # ou: make validate-docker
git add .
git commit -m "feat: descrição"
git push origin production
```

**O que acontece no GitHub Actions (2 runs):**

1. **CI** — `validate-reusable` (lint, seeds, PHPUnit, assets)
2. **Deploy Production** — só se CI passar → HostGator

Acompanhar: https://github.com/joabe-nascimento/unio-corp/actions

---

### 2. Trabalhar com revisão (PR)

```bash
git checkout -b feature/minha-mudanca
# ... commits ...
git push origin feature/minha-mudanca
```

Abrir PR: `feature/minha-mudanca` → `production` (ou `main` / staging)

- **CI roda no PR** (validate completo)
- Após merge → push em `production` → CI + Deploy

Push direto em `feature/*` ou `product/*` **não dispara CI** — use PR para validar.

---

### 3. Sincronizar branches espelho (todas iguais)

Depois de um deploy importante, alinhar `main`, `product/*` (espelho) e `feature/*` com `production`:

```bash
git fetch origin
bash scripts/sync-branches.sh production
```

| Situação | Comportamento do script |
|----------|-------------------------|
| Branch só atrás de `production` | Fast-forward (seguro) |
| Branch divergiu (commits exclusivos) | Reset forçado para `production` |
| Branch em `config/deploy-branches.txt` | **Ignorada** (homolog com deploy próprio) |

**Branches de homolog/deploy** (ex.: `product/rh`) **nunca** entram no sync — commits exclusivos do produto ficam preservados.

**Por que não dispara dezenas de CI:** push em `product/*` e `feature/*` não está no trigger do `ci.yml`. `product/rh` valida no workflow **Deploy Product RH** (sem CI duplicado).

---

### 4. Hotfix urgente

```bash
git checkout production
git pull origin production
git checkout -b hotfix/descricao-curta
# correção mínima
composer validate:pre-push
git push -u origin hotfix/descricao-curta
```

Merge `hotfix/*` → `production` → push → deploy automático.

Depois: `bash scripts/sync-branches.sh production` para alinhar espelhos.

---

## Branch por branch (detalhe)

### `production`

- **Única branch com deploy automático**
- Cada `git push origin production` → validate + deploy
- Preferir validação local antes do push quando possível

### `uniosaude` — Unio Saúde (instância dedicada)

- **URL:** https://uniosaude.uniowork.com.br
- **Servidor:** `/home2/joabef36/unio-uniosaude`
- **Push** → só GitHub; **não** atualiza o site
- **Produção** → `powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1`
- **GitHub Actions** no push desativado (billing jul/2026); disparo manual opcional
- Guia: [UNIOSAUDE_DEPLOY_OPERACAO.md](UNIOSAUDE_DEPLOY_OPERACAO.md)

### `product/*` — módulos

| Branch | Escopo |
|--------|--------|
| `product/core` | Layout, dashboard, shell |
| `product/rh` | RH |
| `product/pessoas` | Pessoas |
| `product/engenharia` | Engenharia |
| `product/hub-ti` | Hub TI |
| `product/hub-pos-operatorio` | Pós-operatório |
| `product/admin` | Admin tenant |
| … | Ver [BRANCHES.md](BRANCHES.md) |

Espelhos: mantidos iguais a `production` via `sync-branches.sh`. Trabalho real pode ser feito em `production` ou em branch temporária + PR.

### `feature/*` — funcionalidades

Mesma lógica dos `product/*`: espelho organizacional, CI via PR, não via push.

### `main` / staging

Integração e homologação. CI no push; deploy manual ou futuro workflow dedicado.

---

## O que acontece no servidor a cada deploy

Script `scripts/deploy-server.sh`:

1. `rm -rf var/cache/prod/*`
2. `doctrine:migrations:migrate`
3. `cache:clear --env=prod --no-warmup`
4. `cache:warmup --env=prod`
5. `rsync` de assets → `public_html`
6. Identidade de e-mail / caixas (se secrets configurados)

**Cache:** recriado a cada deploy. **Banco, uploads e logs:** preservados.

---

## Produção vs local

| | Local (`APP_DEBUG=1`) | Produção (`APP_DEBUG=0`) |
|---|---|---|
| CSS | `unio-app.css` | `unio-app.min.css` |
| Bump cache | opcional | `?v=` em `base.html.twig` após CSS |

Após alterar `unio-app.css`: `php bin/minify-css.php` (automatizado no deploy).

---

## Regra de ouro

> Nada sobe para a HostGator sem passar por **`production`** + pipeline **Validate** + **Deploy Production**.

> Sincronizar espelhos com **`scripts/sync-branches.sh`** — não com push manual em 29 branches.
