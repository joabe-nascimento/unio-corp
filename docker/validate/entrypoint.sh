#!/usr/bin/env bash
set -euo pipefail

cd /app

if [[ ! -f vendor/autoload.php ]]; then
  echo "==> Instalando dependências Composer..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [[ "${QUICK:-0}" != "1" && "${SKIP_ASSETS:-0}" != "1" && -f package.json ]]; then
  if [[ ! -d node_modules ]]; then
    echo "==> Instalando dependências npm..."
    npm ci --no-audit --no-fund
  fi
fi

exec bash scripts/validate-before-push.sh
