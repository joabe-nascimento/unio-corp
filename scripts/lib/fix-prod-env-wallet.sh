#!/usr/bin/env bash
# Corrige .env.local em produção — valores com espaço entre aspas + bloco WALLET.
set -euo pipefail

ENV_FILE="${1:-/home2/joabef36/unio-uniosaude/.env.local}"
APPEND_FILE="$(cd "$(dirname "$0")" && pwd)/prod-env-wallet-append.txt"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Arquivo não encontrado: $ENV_FILE" >&2
  exit 1
fi

# Corrige linhas sem aspas (quebra Symfony Dotenv)
perl -i -pe 's/^UNIO_ORGANISMO_BRAND_NAME=Unio Saúde$/UNIO_ORGANISMO_BRAND_NAME="Unio Saúde"/' "$ENV_FILE" || true
perl -i -pe 's/^UNIO_ORGANISMO_UNIT_LABEL=Clínica$/UNIO_ORGANISMO_UNIT_LABEL="Clínica"/' "$ENV_FILE" || true
perl -i -pe 's/^UNIO_ORGANISMO_BRAND_NAME=Unio Saude$/UNIO_ORGANISMO_BRAND_NAME="Unio Saúde"/' "$ENV_FILE" || true
perl -i -pe 's/^UNIO_ORGANISMO_UNIT_LABEL=Clinica$/UNIO_ORGANISMO_UNIT_LABEL="Clínica"/' "$ENV_FILE" || true

if ! grep -q 'WALLET_APPLE_PASS_TYPE_ID' "$ENV_FILE"; then
  cat "$APPEND_FILE" >> "$ENV_FILE"
  echo "Bloco WALLET adicionado."
else
  echo "WALLET já presente — apenas aspas corrigidas se necessário."
fi

cd "$(dirname "$ENV_FILE")/.."
php bin/console cache:clear --env=prod --no-debug
echo "OK: $ENV_FILE"
