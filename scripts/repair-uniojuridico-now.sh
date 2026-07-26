#!/usr/bin/env bash
# Reparo manual Unio Jurídico — Terminal do cPanel ou SSH.
#
#   export DEPLOY_PATH=/home2/joabef36/unio-uniojuridico
#   export PUBLIC_HTML=/home2/joabef36/uniojuridico.uniowork.com.br
#   export DEFAULT_URI=https://uniojuridico.uniowork.com.br
#   bash scripts/repair-uniojuridico-now.sh

set -euo pipefail

export DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio-uniojuridico}"
export PUBLIC_HTML="${PUBLIC_HTML:-/home2/joabef36/uniojuridico.uniowork.com.br}"
export DEFAULT_URI="${DEFAULT_URI:-https://uniojuridico.uniowork.com.br}"

echo "== Reparo Unio Jurídico"
echo "   URL:  ${DEFAULT_URI}"
echo "   app:  ${DEPLOY_PATH}"
echo "   web:  ${PUBLIC_HTML}"
echo ""

for script in \
  "${DEPLOY_PATH}/scripts/setup-uniojuridico-hostgator.sh" \
  "${DEPLOY_PATH}/scripts/lib/sync-public-html-entrypoint.sh" \
  "${DEPLOY_PATH}/scripts/lib/repair-subdomain-vhost.sh"; do
  if [[ ! -f "$script" ]]; then
    echo "ERRO: nao encontrado: $script"
    echo "Rode um deploy antes ou veja docs/UNIOJURIDICO_DEPLOY_REPAIR.md"
    exit 1
  fi
done

echo "[1/3] setup-uniojuridico-hostgator.sh"
bash "${DEPLOY_PATH}/scripts/setup-uniojuridico-hostgator.sh"

echo ""
echo "[2/3] sync-public-html-entrypoint.sh"
# shellcheck source=lib/sync-public-html-entrypoint.sh
source "${DEPLOY_PATH}/scripts/lib/sync-public-html-entrypoint.sh"

echo ""
echo "[3/3] repair-subdomain-vhost.sh"
bash "${DEPLOY_PATH}/scripts/lib/repair-subdomain-vhost.sh"

echo ""
echo "Concluido. Ver docs/UNIOJURIDICO_DEPLOY_REPAIR.md se HTTPS ainda falhar."
