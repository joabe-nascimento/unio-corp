#!/bin/bash
# Religa supervisor + uvicorn se o health cair. Seguro para cron a cada 1–5 min.
# CageFS: a verdade é o HTTP /health, não o PID file.
set +e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-hostgator.sh
source "$SCRIPT_DIR/lib-hostgator.sh"

cd "$APP_DIR" || exit 1
chmod +x "$SCRIPT_DIR/"*.sh 2>/dev/null || true

# 1) Se já responde, não toca em nada (evita fork bomb no LVE).
if jurisflow_health_ok; then
  jurisflow_touch_heartbeat
  exit 0
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - JurisFlow OFFLINE (porta $PORT)" >> "$WATCHDOG_LOG"

ensure_supervisor() {
  if jurisflow_supervisor_alive; then
    return 0
  fi
  echo "$(date '+%Y-%m-%d %H:%M:%S') - Supervisor offline, subindo..." >> "$WATCHDOG_LOG"
  nohup setsid "$SCRIPT_DIR/jurisflow-supervisor-hostgator.sh" \
    < /dev/null >> "$SUPERVISOR_LOG" 2>&1 &
  disown $! 2>/dev/null || true
  sleep 3
}

ensure_supervisor

i=0
while [ "$i" -lt 8 ]; do
  if jurisflow_health_ok; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') - JurisFlow OK via supervisor." >> "$WATCHDOG_LOG"
    jurisflow_touch_heartbeat
    exit 0
  fi
  sleep 3
  i=$((i + 1))
done

echo "$(date '+%Y-%m-%d %H:%M:%S') - Supervisor nao recuperou; restart duro." >> "$WATCHDOG_LOG"
pkill -f "jurisflow-supervisor-hostgator.sh" 2>/dev/null || true
rm -f "$SUPERVISOR_PID_FILE"
jurisflow_stop
sleep 1
ensure_supervisor
sleep 10

if jurisflow_health_ok; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') - JurisFlow OK apos restart duro." >> "$WATCHDOG_LOG"
  jurisflow_touch_heartbeat
  exit 0
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - FALHA ao reiniciar JurisFlow." >> "$WATCHDOG_LOG"
tail -n 15 "$LOG_FILE" >> "$WATCHDOG_LOG" 2>/dev/null || true
exit 1
