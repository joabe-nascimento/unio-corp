#!/usr/bin/env bash
# Setup inicial de ambiente de produto/homolog (ex.: RH em rh.uniowork.com.br).
# Uso manual ou via GitHub Actions (setup-product-rh.yml):
#
#   DEPLOY_PATH=/home2/joabef36/unio-rh \
#   PUBLIC_HTML=/home2/joabef36/rh.uniowork.com.br \
#   DEFAULT_URI=https://rh.uniowork.com.br \
#   bash scripts/setup-product-env-server.sh
#
# Opcional (cria .env.local se nÃ£o existir):
#   APP_SECRET=... DATABASE_URL=... bash scripts/setup-product-env-server.sh

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:?DEPLOY_PATH obrigatÃ³rio}"
PUBLIC_HTML="${PUBLIC_HTML:?PUBLIC_HTML obrigatÃ³rio}"
DEFAULT_URI="${DEFAULT_URI:?DEFAULT_URI obrigatÃ³rio (ex.: https://rh.uniowork.com.br)}"
APP_DIR_NAME="$(basename "$DEPLOY_PATH")"
HOME_DIR="$(dirname "$DEPLOY_PATH")"

echo "== Setup produto: $DEFAULT_URI"
echo "   app:  $DEPLOY_PATH"
echo "   web:  $PUBLIC_HTML"

mkdir -p "$DEPLOY_PATH"/{var/cache,var/log,var/sessions,public/uploads/users,public/uploads/chat/voice,public/uploads/chat/files,public/uploads/config}
mkdir -p "$PUBLIC_HTML"

chmod -R ug+rwx "$DEPLOY_PATH/var" "$DEPLOY_PATH/public/uploads" 2>/dev/null || true

INDEX_PHP="$PUBLIC_HTML/index.php"
if [[ ! -f "$INDEX_PHP" ]]; then
  cat > "$INDEX_PHP" <<PHP
<?php

use App\\Kernel;

require_once __DIR__.'/../${APP_DIR_NAME}/vendor/autoload_runtime.php';

return static function (array \$context) {
    return new Kernel(\$context['APP_ENV'], (bool) \$context['APP_DEBUG']);
};
PHP
  echo "Criado: $INDEX_PHP"
else
  echo "Mantido: $INDEX_PHP (jÃ¡ existia)"
fi

ENV_FILE="$DEPLOY_PATH/.env.local"
if [[ ! -f "$ENV_FILE" ]]; then
  if [[ -z "${APP_SECRET:-}" || -z "${DATABASE_URL:-}" ]]; then
    echo "AVISO: .env.local nÃ£o criado â€” defina APP_SECRET e DATABASE_URL e rode de novo."
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
  echo "Mantido: $ENV_FILE (jÃ¡ existia)"
fi

echo ""
echo "PrÃ³ximos passos:"
echo "  1. cPanel: subdomÃ­nio apontando para $PUBLIC_HTML (se ainda nÃ£o existir)"
echo "  2. Conferir .env.local em $ENV_FILE"
echo "  3. Deploy da branch product/rh (GitHub Actions â†’ Deploy Product RH)"
echo "  4. ApÃ³s primeiro deploy: php bin/console app:ensure-platform-owner --allow-prod (se necessÃ¡rio)"
