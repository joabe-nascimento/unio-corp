# Último deploy com sucesso — resumo executivo

**Data:** 02/jul/2026  
**Branch:** `production`  
**Commit:** `7abf2c2` — `fix(core): sincroniza unio-app.min.css para corrigir kanban em prod`

---

## O que este commit entrega

1. **`unio-app.min.css` sincronizado** com `unio-app.css` (fixes de kanban finalmente em produção)
2. **Cache bust** `?v=294` em `base.html.twig`
3. **Regras CSS** extras para `page-body-inner--hub-tabs`
4. **`php bin/minify-css.php`** no workflow de deploy e no script de validação

---

## Commits incluídos no deploy (desde `42b78ea`)

| Commit | Descrição |
|--------|-----------|
| `728b43d` | Layout kanban: `page-body-scroll`, toolbars hidden, empty state compact |
| `62d3203` | Grants supervisor corrigidos no seed |
| `9adccc6` | Script validate pre-push + hooks |
| `0590eb4` | PHPStan baseline atualizado |
| `ddfda55` | Docker Compose validate + workflow reutilizável |
| `7abf2c2` | **Min.css sync** (fix visual em prod) |

---

## GitHub Actions — resultado

| Workflow | Status |
|----------|--------|
| CI (`production`) | success (~2m27s) |
| Deploy Production | success (~3m35s) |

Pipeline:

1. **validate** — `composer validate:ci` (lints, seeds, sistema, PHPUnit, minify, npm)
2. **deploy** — tar + scp + `deploy-server.sh` (migrate, cache, rsync CSS/JS)

---

## Servidor HostGator pós-deploy

| Item | Valor |
|------|--------|
| App | `/home2/joabef36/unio` |
| Web root | `/home2/joabef36/public_html` |
| Cache prod | Limpo e recriado |
| Migrations | Aplicadas automaticamente |
| Seeds | **Não** rodar no cron |

---

## Como validar no browser

1. Abrir Projetos e Metas (aba Kanban)
2. **Ctrl+Shift+R** (hard refresh)
3. Esperado:
   - Sem caixa branca gigante entre instrução e colunas
   - Sem texto/botão azul flutuando no meio do board
   - Footer no rodapé da página
   - Contadores em 0 se não houver projetos/tarefas (normal em prod sem seed)

---

## Próximos passos sugeridos

1. Instalar hook local: `composer hooks:install`
2. Confirmar crons removidos no cPanel
3. Confirmar `APP_DEBUG=0` em `.env.local` no servidor
4. Alinhar `main` / `new_staging` com `production` quando fizer release formal
5. Cadastrar projetos/tarefas reais ou rodar seed manual **uma vez** em prod se necessário (com `--allow-prod`, com cautela)

---

## Documentação relacionada

- [OPERACAO_INDICE.md](OPERACAO_INDICE.md) — índice geral
- [OPERACAO_HISTORICO_CORRECOES.md](OPERACAO_HISTORICO_CORRECOES.md) — todos os erros e fixes
- [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md) — fluxo por branch
- [OPERACAO_GITHUB_ACTIONS.md](OPERACAO_GITHUB_ACTIONS.md) — workflows detalhados
- [OPERACAO_VALIDACAO_LOCAL.md](OPERACAO_VALIDACAO_LOCAL.md) — validar antes de push
