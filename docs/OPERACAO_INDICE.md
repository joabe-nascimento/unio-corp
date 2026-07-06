# Operação Unio — índice (jul/2026)

Documentação consolidada após estabilização do deploy HostGator, CI/CD e validação pré-push.

| Documento | Conteúdo |
|-----------|----------|
| [OPERACAO_HISTORICO_CORRECOES.md](OPERACAO_HISTORICO_CORRECOES.md) | Cronologia completa: erros → correções → sucesso |
| [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) | Como cada branch funciona **a partir de hoje** |
| [OPERACAO_CLIENTE_MATRIZ.md](OPERACAO_CLIENTE_MATRIZ.md) | Matriz cliente × produto × branch × URL (contratos e deploy dedicado) |
| [OPERACAO_HOMOLOG_PRODUTO.md](OPERACAO_HOMOLOG_PRODUTO.md) | **Homolog por produto** — piloto RH (`rh.uniowork.com.br`), deploy, sync, scaffold |
| [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md) | Workflows CI, validate, deploy (prod, staging, RH) |
| [OPERACAO_VALIDACAO_LOCAL.md](OPERACAO_VALIDACAO_LOCAL.md) | Script, Docker Compose, hooks git, comandos |
| [OPERACAO_EMAIL_PLATAFORMA.md](OPERACAO_EMAIL_PLATAFORMA.md) | E-mails HostGator/Titan, login, assinaturas, secrets e deploy |
| [OPERACAO_SESSAO_EMAIL_TITAN_2026-07-03.md](OPERACAO_SESSAO_EMAIL_TITAN_2026-07-03.md) | **Sessão 3 jul 2026** — Titan, DKIM, MAILER_DSN, forgot-password, cron Messenger |
| [OPERACAO_INPI_CHECKLIST.md](OPERACAO_INPI_CHECKLIST.md) | Checklist registro INPI (RPC) — Unio, CNPJ titular, hash, e-CNPJ, GRU 730 |
| [BRANCHES.md](BRANCHES.md) | Modelo oficial de branches (feature → product → main → staging → production) |
| [DEPLOY_AGORA.md](DEPLOY_AGORA.md) | Checklist rápido HostGator |
| [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) | Pipeline SSH HostGator, secrets, host canônico, troubleshooting |

## Estado atual (último deploy)

| Item | Valor |
|------|--------|
| **Branch de produção** | `production` |
| **Homolog principal** | `new_staging` → https://staging.uniowork.com.br |
| **Homolog RH** | `product/rh` → https://rh.uniowork.com.br |
| **Deploy prod** | push `production` → CI + Deploy Production |
| **Deploy staging** | push `new_staging` → Deploy Staging (validate incluso) |
| **Deploy RH** | push `product/rh` → Deploy Product RH (validate incluso) |
| **SSH deploy** | `br1136.hostgator.com.br:2222` — **não** domínio público (Cloudflare) |
| **Servidor prod** | `/home2/joabef36/unio` + `public_html` |
| **Servidor staging** | `/home2/joabef36/unio-staging` + `staging.uniowork.com.br` |
| **Servidor RH** | `/home2/joabef36/unio-rh` + `rh.uniowork.com.br` |
| **Sync espelhos** | `bash scripts/sync-branches.sh production` (ignora `product/rh`) |

Ver detalhes: [OPERACAO_HOMOLOG_PRODUTO.md](OPERACAO_HOMOLOG_PRODUTO.md)

## Regra de ouro

> Nada sobe para `uniowork.com.br` sem passar por `production` + **Deploy Production**.

> Homolog RH: push em `product/rh` → **Deploy Product RH** → `rh.uniowork.com.br` (isolado até merge).

> Espelhar branches: `bash scripts/sync-branches.sh production` — homologs em `config/deploy-branches.txt` são preservados.
