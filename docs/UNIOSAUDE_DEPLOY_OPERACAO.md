# Unio Saúde — operação de deploy (jul/2026)

Guia único do fluxo **atual**: o que fazer no dia a dia, por que aparece **X vermelho** no GitHub, e como publicar na HostGator **sem depender do Actions**.

---

## Resumo em 30 segundos

| Ação | Atualiza o site? |
|------|------------------|
| `git push origin uniosaude` | **Não** — só salva no GitHub |
| `deploy-uniosaude-manual.ps1` | **Sim** — publica em produção |
| GitHub Actions no push | **Desativado** (billing bloqueado) |
| GitHub Actions manual (*Run workflow*) | Sim, se billing voltar |

**URL:** https://uniosaude.uniowork.com.br  
**Branch:** `uniosaude`  
**Servidor:** `/home2/joabef36/unio-uniosaude`

---

## Fluxo oficial (use sempre)

```powershell
cd "C:\projetos\Nova pasta\unio-corp\unio-corp"

# 1) Versionar no GitHub
git push origin uniosaude

# 2) Publicar na HostGator
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1
```

Opcional antes do push:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\validate-before-push.ps1
```

Se o build local já foi feito e você só quer reenviar o pacote:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1 -SkipBuild
```

---

## O X vermelho no commit — o que significa?

### Sintoma

No GitHub, commits em `uniosaude` aparecem com **ícone laranja/vermelho (X)** ao lado, workflow **Deploy Unio Saúde** em *failure*.

### Causa (jul/2026)

**Não é bug no código.** O job `validate` falha em **~3–5 segundos** sem executar nenhum step — típico de **billing ou limite do GitHub Actions** esgotado.

Como confirmar:

```powershell
gh run list --branch uniosaude --limit 3
gh api repos/joabe-nascimento/unio-corp/commits/SEU_SHA/check-runs --jq ".check_runs[] | {name, conclusion}"
```

| Check | Significado |
|-------|-------------|
| `validate / validate` → failure em poucos segundos | Actions bloqueado |
| `deploy` → skipped | Nem tentou publicar |
| GitGuardian → pass | Segurança OK — irrelevante para deploy |

### O site quebrou por causa do X?

**Não necessariamente.** O X só indica que o **pipeline automático não rodou**. Produção depende do **deploy manual** (ou de um Actions bem-sucedido no passado).

Verificação rápida:

```powershell
(Invoke-WebRequest -Uri "https://uniosaude.uniowork.com.br/login" -UseBasicParsing).StatusCode
# esperado: 200
```

### O que foi feito para parar o alarme

A partir do commit `5745149`, o workflow `.github/workflows/deploy-uniosaude.yml` **não dispara mais no push** — apenas em **workflow_dispatch** (disparo manual no GitHub).

Assim, novos `git push` em `uniosaude` **não geram X vermelho** por falha de billing.

---

## GitHub Actions vs deploy manual

| | Actions (quando billing OK) | Manual (ativo hoje) |
|--|----------------------------|---------------------|
| **Disparo** | push ou *Run workflow* | você executa o `.ps1` |
| **Requisitos** | secrets no repo + minutos Actions | chave SSH no PC |
| **Build** | runner Ubuntu | seu PC (composer + npm) |
| **Envio** | tar + scp via CI | tar + scp via script |
| **Pós-deploy** | `deploy-server.sh` no servidor | mesmo script |
| **Atualiza produção?** | Sim | Sim |

**Regra prática:** trate o **push** como backup/versionamento e o **`.ps1`** como publicação.

Documentação detalhada do script: [DEPLOY_MANUAL_UNIOSAUDE.md](DEPLOY_MANUAL_UNIOSAUDE.md)

---

## Setup no PC (já configurado em jul/2026)

### Chave SSH

```
C:\Users\joabe\.ssh\unio_deploy
```

Teste:

```powershell
ssh -p 2222 -i C:\Users\joabe\.ssh\unio_deploy joabef36@br1136.hostgator.com.br "echo ok"
```

Use **`br1136.hostgator.com.br`** — nunca o domínio público (Cloudflare).

### Config local (não commitada)

Arquivo: `config/deploy-uniosaude.local.env` (modelo: `config/deploy-uniosaude.local.env.example`)

```dotenv
DEPLOY_KEY_FILE=C:\Users\joabe\.ssh\unio_deploy
DEPLOY_PATH=/home2/joabef36/unio-uniosaude
DEPLOY_PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
DEFAULT_URI=https://uniosaude.uniowork.com.br
```

---

## O que o deploy manual faz (por dentro)

1. `composer install --no-dev`
2. `npm ci` + `npm run vendor:sync` + `php bin/minify-css.php`
3. Cria `deploy-uniosaude.tar.gz` (mesmos excludes do Actions)
4. `scp` → `/tmp/` no servidor
5. `ci-remote-extract.sh` → extrai em `unio-uniosaude`
6. `deploy-server.sh` → migrations, cache, symlinks `public_html`
7. Smoke em `/login`

Relatório local: `deploy-reports/deploy-report.txt`  
Relatório no servidor: `/home2/joabef36/unio-uniosaude/var/log/deploy-report.txt`

---

## Problemas conhecidos e correções

### 1. `scp: stat local "2222"` (Windows)

**Causa:** no Windows, `scp` usa **`-P`** (maiúsculo) para porta; `ssh` usa **`-p`**.

**Status:** corrigido em `scripts/deploy-uniosaude-manual.ps1` (commit `d3bfc95`).

### 2. `DATABASE_URL` não encontrado no primeiro extract

**Causa:** arquivo `deploy-remote.env` gerado com **CRLF** (Windows); variável `DEPLOY_PATH` chegava com `\r` no servidor.

**Status:** corrigido — env remoto agora é gravado com LF apenas.

**Se acontecer de novo:** rode no servidor:

```bash
export DEPLOY_PATH=/home2/joabef36/unio-uniosaude
export PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
bash /home2/joabef36/unio-uniosaude/scripts/deploy-server.sh
```

### 3. HTTPS 404 após deploy

Reparo no Terminal do cPanel — [UNIOSAUDE_DEPLOY_REPAIR.md](UNIOSAUDE_DEPLOY_REPAIR.md)

### 4. Caminho do projeto com espaço

O repositório Git fica em:

```
C:\projetos\Nova pasta\unio-corp\unio-corp
```

Sempre use aspas no `cd`:

```powershell
cd "C:\projetos\Nova pasta\unio-corp\unio-corp"
```

A pasta pai `unio-corp` **não** é o repositório Git.

---

## Reativar deploy automático no push (futuro)

Quando o billing do GitHub Actions estiver regularizado:

1. GitHub → **Settings → Billing** — confirmar minutos disponíveis
2. Testar: **Actions → Deploy Unio Saúde → Run workflow**
3. Se passar, reativar o trigger no workflow:

```yaml
# .github/workflows/deploy-uniosaude.yml
on:
  push:
    branches:
      - uniosaude
  workflow_dispatch:
```

4. Atualizar este doc e [OPERACAO_INDICE.md](OPERACAO_INDICE.md)

---

## Histórico (jul/2026)

| Data | Evento |
|------|--------|
| 11/07 | Billing Actions bloqueado — jobs falham em ~4s sem steps |
| 11/07 | Criado `deploy-uniosaude-manual.ps1` + `DEPLOY_MANUAL_UNIOSAUDE.md` |
| 11/07 | Primeiro deploy manual bem-sucedido; `/login` → HTTP 200 |
| 11/07 | Corrigido `scp -P` e line endings no script Windows |
| 11/07 | Desativado deploy Actions no **push** (commit `5745149`) |

---

## Documentos relacionados

- [DEPLOY_MANUAL_UNIOSAUDE.md](DEPLOY_MANUAL_UNIOSAUDE.md) — passo a passo do script
- [UNIOSAUDE_DEPLOY_REPAIR.md](UNIOSAUDE_DEPLOY_REPAIR.md) — vhost HTTPS e 404
- [DNS_UNIOWORK_UNIOSAUDE.md](DNS_UNIOWORK_UNIOSAUDE.md) — DNS do subdomínio
- [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md) — pipeline quando Actions voltar
- [OPERACAO_VALIDACAO_LOCAL.md](OPERACAO_VALIDACAO_LOCAL.md) — validar antes do push
