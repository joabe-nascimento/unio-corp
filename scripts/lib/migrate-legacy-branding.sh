#!/usr/bin/env bash
# Atualiza taglines legadas em var/admin_config.json.
set -euo pipefail

DEPLOY_PATH="${1:-.}"
PHP_BIN="${PHP_BIN:-php}"
SCRIPT="$(cd "$(dirname "$0")" && pwd)/migrate-legacy-branding.php"

exec "$PHP_BIN" "$SCRIPT" "$DEPLOY_PATH"
