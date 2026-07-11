# Unio Saúde — deploy, vhost e reparo manual

Guia para quando o site responde **404 HostGator** em HTTPS, scripts antigos no servidor ou falha de billing no GitHub Actions.

## Caminhos no servidor

| Item | Caminho |
|------|---------|
| App Symfony | `/home2/joabef36/unio-uniosaude` |
| Document root (subdomínio) | `/home2/joabef36/uniosaude.uniowork.com.br` |
| URL | https://uniosaude.uniowork.com.br |

## Automático em todo deploy

A partir deste guia, cada deploy via GitHub Actions executa no servidor (`scripts/deploy-server.sh`):

1. **`scripts/lib/sync-public-html-entrypoint.sh`** — regrava `index.php` e `.htaccess` no `public_html` do subdomínio
2. **`scripts/lib/repair-subdomain-vhost.sh`** — confirma docroot no cPanel, rebuild Apache e smoke HTTP/HTTPS

O workflow **Deploy Unio Saúde** também roda smoke externo (`smoke-test-hostgator-reusable.yml`) com validação HTTPS.

## Setup inicial (uma vez)

GitHub Actions → **Setup Unio Saúde (server)** na branch `uniosaude`, ou no **Terminal do cPanel** (sem SSH):

```bash
export DEPLOY_PATH=/home2/joabef36/unio-uniosaude
export PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
export DEFAULT_URI=https://uniosaude.uniowork.com.br

bash "$DEPLOY_PATH/scripts/setup-uniosaude-hostgator.sh"
bash "$DEPLOY_PATH/scripts/setup-product-env-server.sh"
```

Atalho que executa os dois + smoke:

```bash
bash "$DEPLOY_PATH/scripts/repair-uniosaude-now.sh"
```

## Reparo manual (404 em HTTPS)

Use no **Terminal do cPanel** (`joabef36@uniowork`) — **não** abra SSH para `br1136` de novo.

```bash
export PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
export DEPLOY_PATH=/home2/joabef36/unio-uniosaude
export DEFAULT_URI=https://uniosaude.uniowork.com.br

bash "$DEPLOY_PATH/scripts/lib/sync-public-html-entrypoint.sh"
bash "$DEPLOY_PATH/scripts/lib/repair-subdomain-vhost.sh"
```

Ou cole o bloco completo (equivalente ao reparo de jul/2026):

```bash
export PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
export DEPLOY_PATH=/home2/joabef36/unio-uniosaude

cat > "$PUBLIC_HTML/index.php" <<'PHP'
<?php

use App\Kernel;

require_once __DIR__.'/../unio-uniosaude/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
PHP

cat > "$PUBLIC_HTML/.htaccess" <<'HTA'
DirectoryIndex index.php
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTA

uapi --output=json SubDomain changedocroot domain=uniosaude rootdomain=uniowork.com.br docroot=uniosaude.uniowork.com.br
/scripts/rebuildhttpdconf 2>/dev/null || true
/scripts/restartsrv_httpd 2>/dev/null || true

curl -sI -H "Host: uniosaude.uniowork.com.br" http://127.0.0.1/login | head -1
curl -sI --resolve uniosaude.uniowork.com.br:443:50.6.138.130 -k https://uniosaude.uniowork.com.br/login | head -1
```

**Esperado:** `HTTP/1.1 200` ou `302` nas duas últimas linhas.

## Sintomas e causas

| Sintoma | Causa provável |
|---------|----------------|
| `HTTPS /login => HTTP 404` | `index.php` antigo ou docroot SSL incorreto |
| `Mantido: .../index.php (ja existia)` | Script antigo no servidor (antes do fix que sempre regrava) |
| `line 1: ﻿#!/usr/bin/env: No such file` | BOM UTF-8 no shebang de `setup-product-env-server.sh` (corrigido no repo) |
| GitHub Actions falha em ~4s | Billing/limit — job nem inicia |
| `[5/6] Smoke (forca DNS local...)` | `setup-uniosaude-hostgator.sh` antigo (sem rebuild Apache) |
| DNS OK, site 404 | Problema de vhost Apache, não de DNS |

## Falhas conhecidas do pipeline

1. **Billing GitHub Actions** — regularize em Settings → Billing; depois re-run **Deploy Unio Saúde** ou **Setup Unio Saúde (server)**.
2. **AutoSSL** — após criar subdomínio, HTTPS pode levar 5–30 min; smoke pode mostrar 404 temporário.
3. **Scripts desatualizados no servidor** — resolvido após um deploy bem-sucedido; até lá use reparo manual acima.

## Deploy manual do PC (sem GitHub Actions)

Enquanto o billing do Actions estiver bloqueado, publique com:

```powershell
cd C:\projetos\Nova pasta\unio-corp\unio-corp
powershell -ExecutionPolicy Bypass -File scripts\deploy-uniosaude-manual.ps1
```

Guia completo: [DEPLOY_MANUAL_UNIOSAUDE.md](DEPLOY_MANUAL_UNIOSAUDE.md)

## Verificação rápida

```bash
# No servidor
curl -sI -H "Host: uniosaude.uniowork.com.br" http://127.0.0.1/login | head -1
curl -sI --resolve uniosaude.uniowork.com.br:443:50.6.138.130 -k https://uniosaude.uniowork.com.br/login | head -1

# DNS
dig +short uniosaude.uniowork.com.br @8.8.8.8
# esperado: 50.6.138.130
```

## Documentos relacionados

- [DNS_UNIOWORK_UNIOSAUDE.md](DNS_UNIOWORK_UNIOSAUDE.md)
- [DEPLOY_GITHUB_ACTIONS.md](DEPLOY_GITHUB_ACTIONS.md)
- [OPERACAO_BRANCHES_DEPLOY.md](OPERACAO_BRANCHES_DEPLOY.md)
