#!/usr/bin/env bash
# Espelha contas da producao em staging e RH (mesmas senhas de login).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP="${PHP_BIN:-php}"
SCRIPT="$ROOT/scripts/sync-homolog-identity-from-prod.php"
DUMP="${1:-/tmp/unio-identity.json}"

"$PHP" "$SCRIPT" export "$DUMP"
"$PHP" "$SCRIPT" import staging "$DUMP"
"$PHP" "$SCRIPT" import rh "$DUMP"

echo "Sync concluido (staging + rh)."
