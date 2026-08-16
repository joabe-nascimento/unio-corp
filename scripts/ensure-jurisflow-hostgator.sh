#!/usr/bin/env bash
# Garante JurisFlow + LEGAL_AI_URL corretos após deploy do Unio Jurídico.
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio-uniojuridico}"
JURISFLOW_APP="${JURISFLOW_APP:-/home2/joabef36/jurisflow-ai}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# shellcheck source=scripts/lib/jurisflow-hostgator.env
source "$ROOT/scripts/lib/jurisflow-hostgator.env"
PORT="${JURISFLOW_HOSTGATOR_PORT:-8098}"
PORT="${PORT//$'\r'/}"
LEGAL_URL="http://127.0.0.1:${PORT}"

ENV_FILE="${DEPLOY_PATH}/.env.local"
if [[ -f "$ENV_FILE" ]]; then
  if grep -q '^LEGAL_AI_URL=' "$ENV_FILE" 2>/dev/null; then
    sed -i "s|^LEGAL_AI_URL=.*|LEGAL_AI_URL=${LEGAL_URL}|" "$ENV_FILE"
  else
    printf 'LEGAL_AI_URL=%s\n' "$LEGAL_URL" >> "$ENV_FILE"
  fi
  if grep -q '^LEGAL_AI_ENABLED=' "$ENV_FILE" 2>/dev/null; then
    sed -i 's|^LEGAL_AI_ENABLED=.*|LEGAL_AI_ENABLED=true|' "$ENV_FILE"
  else
    echo 'LEGAL_AI_ENABLED=true' >> "$ENV_FILE"
  fi
  echo "LEGAL_AI_URL=${LEGAL_URL} (sincronizado em .env.local)"
fi

if [[ -d "$JURISFLOW_APP" ]]; then
  mkdir -p "${JURISFLOW_APP}/scripts"
  for f in lib-hostgator.sh watchdog-hostgator.sh jurisflow-supervisor-hostgator.sh; do
    if [[ -f "$ROOT/scripts/$f" ]]; then
      cp -f "$ROOT/scripts/$f" "${JURISFLOW_APP}/scripts/$f"
    fi
  done
  chmod +x "${JURISFLOW_APP}/scripts/"*.sh 2>/dev/null || true

  if [[ -x "${JURISFLOW_APP}/scripts/watchdog-hostgator.sh" ]]; then
    bash "${JURISFLOW_APP}/scripts/watchdog-hostgator.sh" || true
  elif [[ -f "$ROOT/scripts/fix-jurisflow-keepalive-hostgator.sh" ]]; then
    bash "$ROOT/scripts/fix-jurisflow-keepalive-hostgator.sh" || true
  fi
  sleep 3
fi

if curl -sf -m 5 "${LEGAL_URL}/health" >/dev/null 2>&1; then
  echo "JurisFlow OK: ${LEGAL_URL}/health"
  exit 0
fi

echo "AVISO: JurisFlow indisponível em ${LEGAL_URL}/health após ensure" >&2
exit 1
