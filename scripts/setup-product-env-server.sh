#!/usr/bin/env bash
# Setup inicial de ambiente de produto/homolog (ex.: RH em rh.uniowork.com.br).
# Uso manual ou via GitHub Actions (setup-product-rh.yml):
#
#   DEPLOY_PATH=/home2/joabef36/unio-rh \
#   PUBLIC_HTML=/home2/joabef36/rh.uniowork.com.br \
#   DEFAULT_URI=https://rh.uniowork.com.br \
#   bash scripts/setup-product-env-server.sh
#
# Opcional (cria .env.local se nao existir):
#   APP_SECRET=... DATABASE_URL=... bash scripts/setup-product-env-server.sh

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:?DEPLOY_PATH obrigatorio}"
PUBLIC_HTML="${PUBLIC_HTML:?PUBLIC_HTML obrigatorio}"
DEFAULT_URI="${DEFAULT_URI:?DEFAULT_URI obrigatorio (ex.: https://rh.uniowork.com.br)}"
APP_DIR_NAME="$(basename "$DEPLOY_PATH")"

echo "== Setup produto: $DEFAULT_URI"
echo "   app:  $DEPLOY_PATH"
echo "   web:  $PUBLIC_HTML"

mkdir -p "$DEPLOY_PATH"/{var/cache,var/log,var/sessions,public/uploads/users,public/uploads/chat/voice,public/uploads/chat/files,public/uploads/config}
mkdir -p "$PUBLIC_HTML"

chmod -R ug+rwx "$DEPLOY_PATH/var" "$DEPLOY_PATH/public/uploads" 2>/dev/null || true

INDEX_PHP="$PUBLIC_HTML/index.php"
cat > "$INDEX_PHP" <<PHP
<?php

use App\\Kernel;

require_once __DIR__.'/../${APP_DIR_NAME}/vendor/autoload_runtime.php';

return static function (array \$context) {
    return new Kernel(\$context['APP_ENV'], (bool) \$context['APP_DEBUG']);
};
PHP
echo "Atualizado: $INDEX_PHP"

HTACCESS="$PUBLIC_HTML/.htaccess"
if [[ ! -f "$HTACCESS" ]]; then
  cat > "$HTACCESS" <<'HTA'
DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule !mod_rewrite.c>
    DirectoryIndex index.php
</IfModule>
HTA
  echo "Criado: $HTACCESS"
else
  if ! grep -q 'RewriteRule \^ index.php' "$HTACCESS" 2>/dev/null; then
    cat > "$HTACCESS" <<'HTA'
DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule !mod_rewrite.c>
    DirectoryIndex index.php
</IfModule>
HTA
    echo "Reparado: $HTACCESS (faltava rewrite Symfony)"
  else
    echo "Mantido: $HTACCESS (rewrite OK)"
  fi
fi

ENV_FILE="$DEPLOY_PATH/.env.local"
ENV_BASE="$DEPLOY_PATH/.env"
if [[ ! -f "$ENV_BASE" ]]; then
  cat > "$ENV_BASE" <<ENV
APP_ENV=prod
APP_SECRET=
APP_SHARE_DIR=var/share
DEFAULT_URI=${DEFAULT_URI}
DATABASE_URL=
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
ENV
  echo "Criado: $ENV_BASE (valores sensiveis ficam em .env.local)"
else
  echo "Mantido: $ENV_BASE (ja existia)"
fi

if [[ ! -f "$ENV_FILE" ]]; then
  if [[ -z "${APP_SECRET:-}" || -z "${DATABASE_URL:-}" ]]; then
    echo "AVISO: .env.local nao criado — defina APP_SECRET e DATABASE_URL e rode de novo."
  else
    cat > "$ENV_FILE" <<ENV
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=${APP_SECRET}

DEFAULT_URI=${DEFAULT_URI}

DATABASE_URL="${DATABASE_URL}"

MAILER_DSN=null://null
MAILER_FROM_ADDRESS=noreply@uniowork.com.br

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

REDIS_URL=

UNIO_ORGANISMO_ENABLED=${UNIO_ORGANISMO_ENABLED:-false}
UNIO_ORGANISMO_PULSO_HOME=${UNIO_ORGANISMO_PULSO_HOME:-true}

VITORIA_AI_ENABLED=false
VITORIA_AI_URL=
VITORIA_AI_KEY=

MERCURE_URL=
MERCURE_PUBLIC_URL=
MERCURE_JWT_SECRET=
ENV
    chmod 600 "$ENV_FILE"
    echo "Criado: $ENV_FILE"
  fi
else
  echo "Mantido: $ENV_FILE (ja existia)"
  if [[ "${UNIO_ORGANISMO_ENABLED:-false}" == "true" ]]; then
    grep -q '^UNIO_ORGANISMO_ENABLED=' "$ENV_FILE" 2>/dev/null \
      || echo "UNIO_ORGANISMO_ENABLED=true" >> "$ENV_FILE"
    grep -q '^UNIO_ORGANISMO_PULSO_HOME=' "$ENV_FILE" 2>/dev/null \
      || echo "UNIO_ORGANISMO_PULSO_HOME=true" >> "$ENV_FILE"
  fi
fi

VHOST="${DEFAULT_URI#https://}"
VHOST="${VHOST#http://}"
VHOST="${VHOST%%/*}"
if command -v uapi >/dev/null 2>&1 && [[ -n "$VHOST" ]]; then
  echo "Configurando PHP 8.2 para $VHOST (cPanel)..."
  uapi --output=json LangPHP php_set_vhost_versions version=ea-php82 vhost="${VHOST}" 2>/dev/null \
    || uapi --output=json LangPHP php_set_vhost_versions version=ea-php81 vhost="${VHOST}" 2>/dev/null \
    || echo "AVISO: nao foi possivel definir versao PHP via uapi"
  uapi --output=json SSL start_autossl_check 2>/dev/null || true
fi

echo ""
echo "Proximos passos:"
echo "  1. cPanel: subdominio apontando para $PUBLIC_HTML (se ainda nao existir)"
echo "  2. Conferir .env.local em $ENV_FILE"
echo "  3. Deploy da branch product/rh (GitHub Actions -> Deploy Product RH)"
echo "  4. Apos primeiro deploy: configure PLATFORM_OWNER ou usuarios reais (sem seeds demo)"
