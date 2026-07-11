# Deploy manual — Unio Saúde (PC → HostGator)

Fluxo fixo para atualizar **produção** sem depender do GitHub Actions.

| Etapa | Onde | O que faz |
|-------|------|-----------|
| 1 | PC | `git push` salva no GitHub (não atualiza o site) |
| 2 | PC | `deploy-uniosaude-manual.ps1` envia código via SSH |
| 3 | Servidor | `deploy-server.sh` — migrate, cache, sync `public_html` |

**URL:** https://uniosaude.uniowork.com.br  
**App:** `/home2/joabef36/unio-uniosaude`  
**Document root:** `/home2/joabef36/uniosaude.uniowork.com.br`

---

## Setup único no PC

### 1. Chave SSH

No cPanel → **SSH Access** → autorize sua chave pública (ou gere par no PC).

Salve a chave privada, por exemplo:

```
C:\Users\joabe\.ssh\unio_deploy
```

Teste:

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br "echo ok"
```

Use sempre o host **`br1136.hostgator.com.br`** (não o domínio público — passa pelo Cloudflare).

### 2. Config local

```powershell
cd C:\projetos\Nova pasta\unio-corp\unio-corp
copy config\deploy-uniosaude.local.env.example config\deploy-uniosaude.local.env
```

Edite `config/deploy-uniosaude.local.env` e ajuste `DEPLOY_KEY_FILE`.

> Esse arquivo **não vai para o Git** (está no `.gitignore`).

---

## Comando fixo (todo deploy)

Na raiz do projeto, branch `uniosaude`:

```powershell
cd C:\projetos\Nova pasta\unio-corp\unio-corp

# Opcional — validar antes
powershell -ExecutionPolicy Bypass -File scripts\validate-before-push.ps1

git push origin uniosaude

powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1
```

Isso executa, na ordem:

1. `composer install --no-dev`
2. `npm ci` + `vendor:sync` + `minify-css`
3. `tar.gz` (mesmos excludes do GitHub Actions)
4. `scp` → servidor `/tmp/deploy-uniosaude.tar.gz`
5. `ci-remote-extract.sh` → extrai + `deploy-server.sh`
6. Smoke em `https://uniosaude.uniowork.com.br/login`

### Só reenviar (sem rebuild local)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1 -SkipBuild
```

---

## Se der 404 em HTTPS após deploy

No **Terminal do cPanel** (sem SSH do PC):

```bash
export PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
export DEPLOY_PATH=/home2/joabef36/unio-uniosaude
export DEFAULT_URI=https://uniosaude.uniowork.com.br

bash "$DEPLOY_PATH/scripts/lib/sync-public-html-entrypoint.sh"
bash "$DEPLOY_PATH/scripts/lib/repair-subdomain-vhost.sh"
```

Detalhes: [UNIOSAUDE_DEPLOY_REPAIR.md](UNIOSAUDE_DEPLOY_REPAIR.md)

---

## Relatório de deploy

Após cada execução, o script tenta baixar:

```
deploy-reports/deploy-report.txt
```

No servidor, o log completo fica em:

```
/home2/joabef36/unio-uniosaude/var/log/deploy-report.txt
```

---

## GitHub Actions vs manual

| | GitHub Actions | Manual (este guia) |
|--|----------------|-------------------|
| Dispara em | *Run workflow* (push desativado jul/2026) | você roda o `.ps1` |
| Requer | billing Actions + secrets | chave SSH no PC |
| Atualiza produção | Sim | Sim |
| X vermelho no commit? | Só se rodar workflow e falhar | Não |

**Recomendação atual:** `git push` para versionar + `deploy-uniosaude-manual.ps1` para publicar.

**Guia completo (billing, X vermelho, troubleshooting):** [UNIOSAUDE_DEPLOY_OPERACAO.md](UNIOSAUDE_DEPLOY_OPERACAO.md)

---

## Windows — armadilhas

| Problema | Solução |
|----------|---------|
| `scp: stat local "2222"` | Script já usa `-P` no scp (não `-p`) |
| `DEPLOY_PATH` com `\r` no servidor | Script grava `deploy-remote.env` com LF |
| `cd` falha com "Nova pasta" | Use aspas: `cd "C:\projetos\Nova pasta\unio-corp\unio-corp"` |
| Repo "not a git repository" | Entre na pasta **unio-corp\unio-corp**, não na pai |
