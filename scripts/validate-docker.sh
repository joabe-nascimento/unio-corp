#!/usr/bin/env bash
# Validação completa via Docker (MariaDB + PHP + Node) — igual ao CI.
#
# Uso:
#   bash scripts/validate-docker.sh
#   QUICK=1 bash scripts/validate-docker.sh
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMPOSE=(docker compose --profile validate)

echo "==> Subindo MariaDB e rodando validação (modo CI)..."
VALIDATE_FRESH_DB=1 GIT_BRANCH="${GIT_BRANCH:-local}" \
  "${COMPOSE[@]}" run --rm --build validate
