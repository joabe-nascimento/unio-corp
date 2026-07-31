#!/bin/bash
# Watchdog: verifica /health e reinicia se necessario.
# Cron a cada 1 min (HostGator LVE mata o uvicorn apos ~120s de CPU).
# Tambem garante que o supervisor em loop esteja rodando.
set -eu

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-hostgator.sh
source "$SCRIPT_DIR/lib-hostgator.sh"

cd "$APP_DIR" || exit 1

ensure_supervisor() {
  local sup_pid_file="$APP_DIR/jurisflow-supervisor.pid"
  local sup_script="$APP_DIR/scripts/jurisflow-supervisor-hostgator.sh"
  local alive=0
  if [ -f "$sup_pid_file" ]; then
    local spid
    spid=$(cat "$sup_pid_file" 2>/dev/null || true)
    if [ -n "${spid:-}" ] && kill -0 "$spid" 2>/dev/null; then
      alive=1
    fi
  fi
  if [ "$alive" -eq 0 ] && [ -x "$sup_script" ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Supervisor offline, subindo..." >> "$WATCHDOG_LOG"
    nohup setsid "$sup_script" < /dev/null >> "$APP_DIR/supervisor.log" 2>&1 &
    disown $! 2>/dev/null || true
    sleep 2
  fi
}

ensure_supervisor

if jurisflow_health_ok; then
  exit 0
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - JurisFlow OFFLINE (porta $PORT), reiniciando..." >> "$WATCHDOG_LOG"

jurisflow_stop
jurisflow_start
sleep 8

if jurisflow_health_ok; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') - JurisFlow reiniciado com sucesso (PID $(cat "$PID_FILE" 2>/dev/null || echo '?'))." >> "$WATCHDOG_LOG"
else
  echo "$(date '+%Y-%m-%d %H:%M:%S') - FALHA ao reiniciar JurisFlow." >> "$WATCHDOG_LOG"
  # Se o start pontual falhou, tenta pelo supervisor
  ensure_supervisor
  sleep 5
  if jurisflow_health_ok; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Recuperado via supervisor." >> "$WATCHDOG_LOG"
    exit 0
  fi
  tail -n 20 "$LOG_FILE" >> "$WATCHDOG_LOG" 2>/dev/null || true
  exit 1
fi
