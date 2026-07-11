# Unio Saúde — relatório do deploy manual (11/07/2026)

Resumo do que **foi enviado com sucesso** e o que **falhou sem impactar o site**.

**URL verificada:** https://uniosaude.uniowork.com.br/login → HTTP 200, login novo ativo.

---

## O que entrou no ar (sucesso)

| Item | Status | Detalhe |
|------|--------|---------|
| Template login novo | OK | `templates/auth/login.html.twig` — “Saúde que acompanha.” |
| CSS login | OK | `public/css/auth-login.css` (18 KB) |
| Symlink `public_html/css` | OK | `uniosaude.uniowork.com.br/css/auth-login.css` |
| Redesign visível | OK | Layout split, Poppins, logo grande, sem abas no card |
| Rotas `/medico/*` | OK | No pacote enviado (commit `052eacc`+) |
| Carteirinha / beneficiário | OK | CSS + JS frente/verso no pacote |
| Migrations + cache | OK | `deploy-server.sh` manual às 06:18 — cache prod limpo e aquecido |
| SSH + SCP do PC | OK | Chave `unio_deploy`, host `br1136.hostgator.com.br:2222` |
| Pacote tar.gz | OK | ~28 MB extraído em `/home2/joabef36/unio-uniosaude` |

### Timestamps no servidor

```
templates/auth/login.html.twig     → 11/07 06:03
public/css/auth-login.css          → 11/07 06:03
public_html/css/auth-login.css     → 11/07 06:03 (symlink)
```

---

## O que falhou (sem impedir o site)

| Etapa | Quando | Causa | Impacto |
|-------|--------|-------|---------|
| 1º `ci-remote-extract` | 06:17 | `deploy-remote.env` com CRLF (Windows) | `deploy-server` falhou na 1ª tentativa; arquivos já extraídos |
| Deploy no PC (pasta errada) | — | Rodou em `unio-corp\` em vez de `unio-corp\unio-corp\` | Script não encontrado — nada enviado |
| Deploy interrompido no agente | ~06:22 | Comando cancelado no meio | Tar já tinha sido enviado antes |
| `sync-public-html-entrypoint.sh` | 06:25 | Exit 127 — script com CRLF no servidor | `deploy-report.txt` marca falha; `index.php` já estava OK de reparo anterior |
| GitHub Actions | todo push | Billing bloqueado (~4s, 0 steps) | Não publica; irrelevante com deploy manual |

### Relatório no servidor

Arquivo: `/home2/joabef36/unio-uniosaude/var/log/deploy-report.txt`  
Pode mostrar **FALHA** na última etapa mesmo com o site funcionando — é log da etapa `public_html`, não do upload do código.

---

## Commits no GitHub (branch `uniosaude`)

| Commit | Conteúdo |
|--------|----------|
| `052eacc` | Login redesign, `/medico`, carteirinha, templates ops |
| `02af636` | Script deploy manual + docs |
| `d3bfc95` | Fix `scp -P` e line endings Windows |
| `5745149` | Desativa Actions no push |
| `8a8c85a` | Documentação operação deploy |

**Produção:** código do pacote manual (~`052eacc`–`8a8c85a`); `revision.json` ainda pode mostrar `commit: manual` até próximo deploy completo.

---

## Próximo deploy (comando correto)

```powershell
cd "C:\projetos\Nova pasta\unio-corp\unio-corp"
git push origin uniosaude
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1
```

---

## Acessos após deploy

Ver [UNIOSAUDE_ACESSOS.md](UNIOSAUDE_ACESSOS.md):

```
renata.oliveira@unio.dev / unio123
```
