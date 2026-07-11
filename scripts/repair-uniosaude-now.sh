#!/usr/bin/env bash
# Reparo manual Unio Saúde — Terminal do cPanel (sem SSH).
#
#   export DEPLOY_PATH=/home2/joabef36/unio-uniosaude
#   export PUBLIC_HTML=/home2/joabef36/uniosaude.uniowork.com.br
#   export DEFAULT_URI=https://uniosaude.uniowork.com.br
#   bash scripts/repair-uniosaude-now.sh
#
# Documentacao: docs/UNIOSAUDE_DEPLOY_REPAIR.md

set -euo pipefail

export DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio-uniosaude}"
export PUBLIC_HTML="${PUBLIC_HTML:-/home2/joabef36/uniosaude.uniowork.com.br}"
export DEFAULT_URI="${DEFAULT_URI:-https://uniosaude.uniowork.com.br}"
export UNIO_ORGANISMO_ENABLED="${UNIO_ORGANISMO_ENABLED:-true}"
export UNIO_ORGANISMO_PULSO_HOME="${UNIO_ORGANISMO_PULSO_HOME:-true}"

echo "== Reparo Unio Saúde"
echo "   URL:  ${DEFAULT_URI}"
echo "   app:  ${DEPLOY_PATH}"
echo "   web:  ${PUBLIC_HTML}"
echo ""

for script in \
  "${DEPLOY_PATH}/scripts/setup-uniosaude-hostgator.sh" \
  "${DEPLOY_PATH}/scripts/setup-product-env-server.sh" \
  "${DEPLOY_PATH}/scripts/lib/repair-subdomain-vhost.sh"; do
  if [[ ! -f "$script" ]]; then
    echo "ERRO: nao encontrado: $script"
    echo "Rode um deploy antes ou veja docs/UNIOSAUDE_DEPLOY_REPAIR.md"
    exit 1
  fi
done

echo "[1/3] setup-uniosaude-hostgator.sh"
bash "${DEPLOY_PATH}/scripts/setup-uniosaude-hostgator.sh"

echo ""
echo "[2/3] setup-product-env-server.sh"
bash "${DEPLOY_PATH}/scripts/setup-product-env-server.sh"

echo ""
echo "[3/3] repair-subdomain-vhost.sh"
bash "${DEPLOY_PATH}/scripts/lib/repair-subdomain-vhost.sh"

echo ""
echo "Concluido. Ver docs/UNIOSAUDE_DEPLOY_REPAIR.md se HTTPS ainda falhar."
