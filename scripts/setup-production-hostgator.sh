#!/usr/bin/env bash
# Bootstrap producao uniowork.com.br na HostGator (DB + pastas + .env.local).
set -euo pipefail

CPANEL_USER="${CPANEL_USER:-joabef36}"
DB_NAME="${DB_NAME:-${CPANEL_USER}_unio}"
DB_USER="${DB_USER:-${CPANEL_USER}_unio}"
DEPLOY_PATH="${DEPLOY_PATH:-/home2/${CPANEL_USER}/unio}"
PUBLIC_HTML="${PUBLIC_HTML:-/home2/${CPANEL_USER}/public_html}"
DEFAULT_URI="${DEFAULT_URI:-https://uniowork.com.br}"

DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)}"
APP_SECRET="${APP_SECRET:-$(openssl rand -hex 32)}"
DATABASE_URL="${DATABASE_URL:-mysql://${DB_USER}:${DB_PASS}@localhost:3306/${DB_NAME}?serverVersion=5.7.44&charset=utf8mb4}"

echo "== Setup producao: ${DEFAULT_URI}"
echo "   app: ${DEPLOY_PATH}"
echo "   web: ${PUBLIC_HTML}"
echo "   db:  ${DB_NAME}"

if command -v uapi >/dev/null 2>&1; then
  uapi --output=json Mysql create_database name="${DB_NAME}" 2>/dev/null || true
  uapi --output=json Mysql create_user name="${DB_USER}" password="${DB_PASS}" 2>/dev/null || true
  uapi --output=json Mysql set_privileges_on_database user="${DB_USER}" database="${DB_NAME}" privileges=ALL
fi

export DEPLOY_PATH PUBLIC_HTML DEFAULT_URI APP_SECRET DATABASE_URL
export UNIO_ORGANISMO_ENABLED=true
export UNIO_ORGANISMO_PULSO_HOME=true

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
bash "${SCRIPT_DIR}/setup-product-env-server.sh"

echo ""
echo "Setup producao concluido. Rode Deploy Production no GitHub Actions (branch production)."
