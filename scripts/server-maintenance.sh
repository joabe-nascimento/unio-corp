#!/usr/bin/env bash
# Ativa ou desativa modo manutenção da plataforma no servidor (var/admin_config.json).
# Uso remoto:
#   DEPLOY_PATH=/home2/joabef36/unio bash scripts/server-maintenance.sh on
#   DEPLOY_PATH=/home2/joabef36/unio bash scripts/server-maintenance.sh off

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio}"
PHP_BIN="${PHP_BIN:-php}"
MODE="${1:-}"

MSG="${MAINTENANCE_MSG:-Plataforma temporariamente indisponível para manutenção. Retornamos em breve.}"

if [[ "$MODE" != "on" && "$MODE" != "off" ]]; then
  echo "Uso: DEPLOY_PATH=... bash $0 on|off"
  exit 1
fi

cd "$DEPLOY_PATH"

CFG="${DEPLOY_PATH}/var/admin_config.json"
mkdir -p "${DEPLOY_PATH}/var"

if [[ -f "$CFG" ]]; then
  payload="$("$PHP_BIN" -r '
    $f = $argv[1];
    $mode = $argv[2];
    $msg = $argv[3];
    $cfg = json_decode(file_get_contents($f), true);
    if (!is_array($cfg)) { $cfg = []; }
    $cfg["manutencao"] = ($mode === "on");
    if ($mode === "on") { $cfg["msg_manutencao"] = $msg; }
    echo json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  ' "$CFG" "$MODE" "$MSG")"
else
  if [[ "$MODE" == "off" ]]; then
    echo "Nada a fazer — config ausente e modo off."
    exit 0
  fi
  payload="$("$PHP_BIN" -r 'echo json_encode(["manutencao"=>true,"msg_manutencao"=>$argv[1]], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);' "$MSG")"
fi

printf '%s\n' "$payload" > "$CFG"
chmod 660 "$CFG" 2>/dev/null || true

$PHP_BIN bin/console cache:clear --env=prod --no-warmup
$PHP_BIN bin/console cache:warmup --env=prod

echo "Manutenção ${MODE} em ${DEPLOY_PATH} ($(date -Iseconds 2>/dev/null || date))"
