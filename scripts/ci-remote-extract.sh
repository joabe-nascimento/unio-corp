#!/usr/bin/env bash
# Executado no servidor HostGator via GitHub Actions (extract + deploy-server).
set -eo pipefail

ARCHIVE="${ARCHIVE:?ARCHIVE required}"

set -a
# shellcheck source=/dev/null
source /tmp/deploy-remote.env
set +a
rm -f /tmp/deploy-remote.env

mkdir -p "$DEPLOY_PATH"
tar -xzf "/tmp/${ARCHIVE}" -C "$DEPLOY_PATH"
rm -f "/tmp/${ARCHIVE}"

export PHP_BIN="${PHP_BIN:-php}"
bash "${DEPLOY_PATH}/scripts/deploy-server.sh" || {
  echo "========== DEPLOY REPORT =========="
  cat "${DEPLOY_PATH}/var/log/deploy-report.txt" 2>/dev/null || echo "(sem relatorio)"
  exit 1
}

echo "========== DEPLOY REPORT =========="
cat "${DEPLOY_PATH}/var/log/deploy-report.txt" 2>/dev/null || true
