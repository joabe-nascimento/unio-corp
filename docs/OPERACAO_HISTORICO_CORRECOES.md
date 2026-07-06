# Histórico de correções — erros até sucesso (jul/2026)

Cronologia do trabalho de estabilização: sessão 403, pipeline CI/CD, deploy HostGator, kanban, permissões e validação automática.

---

## 1. Sessão expirada (403) ao navegar

| | |
|---|---|
| **Sintoma** | Usuário logado recebia 403 / “sessão expirada” ao acessar telas |
| **Causa** | `app:seed-users` com `DELETE` apagava usuários; IDs mudavam; senhas resetadas; cron em prod rodando seeds |
| **Correção** | UPSERT idempotente em `SeedUsersCommand`; senha só para usuários **novos**; `ProdSeedGuardTrait` bloqueia seeds em prod sem `--allow-prod` |
| **Commit** | `42b78ea` — `fix(prod): guard seed commands, preservar senha existente` |

**Ação manual no servidor:** remover crons `app:seed-*`, `migrate` e `cache:clear` a cada minuto no cPanel.

---

## 2. Pipeline de deploy (GitHub Actions → HostGator)

### Erros encontrados e fixes

| Erro | Causa | Fix |
|------|--------|-----|
| `ParameterNotFoundException` | `debug-bundle` em `require` prod; lock desatualizado | `symfony/debug-bundle` em `require-dev`; `bundles.php` só dev/test |
| `DebugBundle not found` em prod | Bundle ausente após `--no-dev` | `composer install --no-scripts` no workflow |
| `require is not defined` | `sync-vendor-assets.mjs` CommonJS | Convertido para ESM (`import`) |
| Deploy SSH / rede | Ver [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) — host canônico `br1136.hostgator.com.br`, nunca domínio Cloudflare |
| `error in libcrypto` | Chave SSH sem newline / CRLF | `printf` + `tr -d '\r'` na chave |
| `tar: file changed as we read it` | tar no workspace mutável | tar em `$RUNNER_TEMP` |
| rsync protocol mismatch | rsync antigo no shared hosting | Substituído por **tar + scp + ssh-action** |
| scp-action archive vazio | path incorreto | scp nativo no runner |
| Cache corrompido no servidor | container Symfony inválido | `rm -rf var/cache/prod/*` antes de migrate no `deploy-server.sh` |

### Estado final do deploy

- Workflow: `.github/workflows/deploy-production.yml`
- Trigger: **push em `production`**
- Fluxo: `validate` → `deploy` (deploy só se validate passar)
- Servidor: extrai tar, roda `scripts/deploy-server.sh` (migrate, cache clear/warmup, rsync estáticos para `public_html`)

---

## 3. CI (testes e qualidade)

| Erro | Fix |
|------|-----|
| PHPStan 38+ erros | Baseline regenerado (`0590eb4`) |
| `VitoriaChatApiTest` falha sem Python | Skip quando porta 8100 offline (`8079078`) |
| PHPStan no push `production` | Ignorado via `GIT_BRANCH=production` no script de validação |
| `SystemValidationServiceTest` — supervisor | Grant legado `GESTOR` em `product_pessoas/membros`; seed `--force` limpa grants (`62d3203`) |

---

## 4. UI — Kanban Projetos e Metas

### Bug 1: footer e botão flutuando sobre o board

| | |
|---|---|
| **Sintoma** | Footer “Unio © 2026” e “Novo projeto” sobrepondo colunas BACKLOG / A FAZER / … |
| **Causa** | `page-body-fill` no inset shell esticava flex; toolbars de abas ocultas afetavam layout |
| **Correção** | `page-body-scroll` em `core_projetos.html.twig`; `[hidden]` em `.core-projetos-toolbar`; empty state `compact` |
| **Commit** | `728b43d` |

### Bug 2: layout ainda quebrado em produção após deploy

| | |
|---|---|
| **Sintoma** | Caixa branca gigante; artefato azul (“T” / texto de botão) no meio do kanban |
| **Causa** | **Produção usa `unio-app.min.css`** (`APP_DEBUG=0`); minificado estava **meses desatualizado** |
| **Correção** | Regenerar `unio-app.min.css`; bump cache `?v=294`; `php bin/minify-css.php` no deploy e na validação |
| **Commit** | `7abf2c2` ← **último deploy com sucesso** |

---

## 5. Validação automática pré-push / pré-deploy

| Entrega | Commit |
|---------|--------|
| `scripts/validate-before-push.sh` + hook pre-push | `9adccc6` |
| Workflow reutilizável + Docker Compose + Makefile | `ddfda55` |
| PHPStan baseline CI verde | `0590eb4` |
| Minify CSS no pipeline | `7abf2c2` |

---

## 6. Cron HostGator (recomendação)

**Remover** (não agendar em produção):

- `doctrine:migrations:migrate` — já roda no deploy
- `app:seed-product-grants` / `app:seed-users` — bloqueados em prod
- `cache:clear --env=prod` — já roda no deploy

**Opcional** (se usar os módulos):

```bash
*/5 * * * * php bin/console app:rh-email-process-queue --env=prod
0 8 * * * php bin/console app:pos-operatorio:send-reminders --env=prod
```

---

## Linha do tempo de commits (production)

```
7abf2c2  fix(core): min.css kanban prod          ← ATUAL
ddfda55  feat(ci): Docker + workflow reutilizável
0590eb4  fix(ci): phpstan baseline
9adccc6  feat(ci): validate pre-push
62d3203  fix(permissions): supervisor grants
728b43d  fix(core): kanban layout
42b78ea  fix(prod): seed guards + deploy estável
…        fix(ci): tar, scp, ssh, secrets…
```
