# Organização de branches — Unio

> **Operação atual (CI/CD, deploy HostGator, validação):** [OPERACAO_INDICE.md](OPERACAO_INDICE.md) e [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md).

## Ambientes (deploy)

| Branch | Uso |
|--------|-----|
| `production` | Produção — código estável em uso pelos clientes |
| `new_staging` | Homologação / staging principal |
| `new_staging2` | Segundo ambiente de staging (testes paralelos, QA, demos) |

## Tronco de desenvolvimento

| Branch | Uso |
|--------|-----|
| `main` | Integração contínua — merge de produtos e features antes de staging |

## Produtos (módulos)

Cada produto tem branch de longa duração. Trabalhe em `feature/*` e integre no `product/*` correspondente.

| Branch | Escopo | Pastas principais |
|--------|--------|-------------------|
| `product/core` | Login, dashboard, layout, menu global | `templates/base.html.twig`, `core/`, `Security/` |
| `product/rh` | Recursos Humanos | `templates/modules/rh/`, `RhController.php` |
| `product/pessoas` | Gestão de Pessoas | `templates/modules/pessoas/`, `PessoasController.php` |
| `product/engenharia` | Obras e Projetos | `templates/modules/engenharia/`, `EngenhariaController.php` |
| `product/hub-operacoes` | *(legado)* Hub Operações agregado — preferir `rh` + `pessoas` + `engenharia` | `templates/modules/operacoes/` |
| `product/hub-talentos` | Hub de Talentos | `templates/modules/talentos/` |
| `product/hub-maturidade` | Hub de Maturidade | `templates/modules/maturidade/` |
| `product/publicidade` | Marca e Comunicação | `templates/modules/publicidade/` |
| `product/admin` | Administração / tenant | `templates/modules/admin/` |

### Hub Operações (3 produtos)

```
product/rh  ──┐
product/pessoas  ──┼──► main ──► new_staging ──► new_staging2 ──► production
product/engenharia  ──┘
```

## Fluxo normal (feature → produção)

**Operação atual (jul/2026):** trabalho pode ir direto para `production` (deploy automático) ou via PR. Branches `product/*` e `feature/*` são **espelhos** — sincronize com:

```bash
bash scripts/sync-branches.sh production
```

Push em `product/*` / `feature/*` **não dispara CI**; validação via PR ou push em `production`. Detalhes: [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md).

```
feature/<nome>  →  (PR opcional)  →  production  →  deploy
                         │
              product/<módulo>  (espelho, sync script)
```

```bash
# Exemplo: nova tela em RH (via PR)
git checkout -b feature/rh-folha-relatorio
# ... commits ...
git push origin feature/rh-folha-relatorio
# Abrir PR: feature/rh-folha-relatorio → production

# Depois do deploy, alinhar espelhos:
bash scripts/sync-branches.sh production
```

## Hotfix (urgente em produção)

Padrão: `hotfix/<descricao-curta>`. **Sempre criar a partir de `production`.**

```bash
git checkout production
git pull origin production
git checkout -b hotfix/corrige-login-expirado
# ... correção mínima ...
git push -u origin hotfix/corrige-login-expirado
```

**Merge (ordem):**

1. `hotfix/*` → `production` (deploy imediato)
2. `hotfix/*` → `main` (não perder a correção no tronco)
3. `hotfix/*` → `new_staging` e `new_staging2` (alinhar ambientes)

```bash
git checkout production && git merge hotfix/corrige-login-expirado && git push
git checkout main && git merge hotfix/corrige-login-expirado && git push
git checkout new_staging && git merge hotfix/corrige-login-expirado && git push
git checkout new_staging2 && git merge hotfix/corrige-login-expirado && git push
```

## Release (versão congelada antes de produção)

Padrão: `release/<versao>` (ex.: `release/1.2.0`). Criar a partir de `main` quando `main` estiver pronto para subir.

```bash
git checkout main
git pull origin main
git checkout -b release/1.2.0
# apenas ajustes finais: versão, changelog, correções de última hora
git push -u origin release/1.2.0
```

**Promoção:**

1. `release/*` → `new_staging` (validação)
2. `release/*` → `new_staging2` (QA paralelo, se necessário)
3. `release/*` → `production` (go-live)
4. `release/*` → `main` (merge back de qualquer ajuste feito na release)

```bash
git checkout new_staging && git merge release/1.2.0 && git push
git checkout new_staging2 && git merge release/1.2.0 && git push
git checkout production && git merge release/1.2.0 && git push
git checkout main && git merge release/1.2.0 && git push
```

## Resumo visual

```
                    ┌── hotfix/* ──────────────► production
                    │         └────────────────► main, staging...
                    │
feature/* ──► product/* ──► main ──► release/* ──► staging ──► production
```

## Branches no remoto (permanentes)

Criadas no repositório — não é preciso recriar:

- `main`, `production`, `new_staging`, `new_staging2`
- `product/core`, `product/rh`, `product/pessoas`, `product/engenharia`
- `product/hub-operacoes`, `product/hub-talentos`, `product/hub-maturidade`
- `product/publicidade`, `product/admin`

`hotfix/*` e `release/*` são criadas **sob demanda** (uma branch por correção ou por versão).

## Cliente dedicado (deploy isolado)

Para contratos com instância própria (subdomínio ou domínio + banco + branch `client/*`), use [OPERACAO_CLIENTE_MATRIZ.md](OPERACAO_CLIENTE_MATRIZ.md).
