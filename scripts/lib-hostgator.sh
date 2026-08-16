#!/bin/bash
# Constantes e funções do JurisFlow na HostGator (Unio Jurídico).
# Porta canônica: 8098. Não reusar 8090–8097 (zumbis CageFS).

APP_DIR="/home2/joabef36/jurisflow-ai"
PORT=8098
LOG_FILE="$APP_DIR/jurisflow.log"
PID_FILE="$APP_DIR/jurisflow.pid"
WATCHDOG_LOG="$APP_DIR/watchdog.log"
LOCK_FILE="$APP_DIR/.watchdog.lock"
HEARTBEAT_FILE="$APP_DIR/supervisor.heartbeat"
SUPERVISOR_PID_FILE="$APP_DIR/jurisflow-supervisor.pid"
SUPERVISOR_LOG="$APP_DIR/supervisor.log"

# Flags leves: 1 worker, sem access log (health a cada poucos s comia o quota de CPU do LVE).
UVICORN_BIN=".venv/bin/uvicorn"
UVICORN_APP="app.main:app"
UVICORN_OPTS=(--host 0.0.0.0 --port "$PORT" --workers 1 --limit-concurrency 16 --timeout-keep-alive 5 --no-access-log --log-level warning)

jurisflow_health_ok() {
  curl -sf -m 4 "http://127.0.0.1:${PORT}/health" >/dev/null 2>&1
}

jurisflow_touch_heartbeat() {
  date '+%Y-%m-%d %H:%M:%S' > "$HEARTBEAT_FILE" 2>/dev/null || true
}

jurisflow_supervisor_alive() {
  # CageFS: PID file de outra sessão SSH/cron não é confiável.
  if pgrep -f "jurisflow-supervisor-hostgator.sh" >/dev/null 2>&1; then
    return 0
  fi
  return 1
}

jurisflow_stop() {
  pkill -f "uvicorn ${UVICORN_APP} --host 0.0.0.0 --port ${PORT}" 2>/dev/null || true
  if [ -f "$PID_FILE" ]; then
    rm -f "$PID_FILE"
  fi
  sleep 1
}

jurisflow_start() {
  cd "$APP_DIR" || return 1

  if [ -f .env.hostgator ]; then
    cp -f .env.hostgator .env
  fi

  if [ ! -x "$UVICORN_BIN" ]; then
    echo "ERRO: $UVICORN_BIN nao encontrado em $APP_DIR" >&2
    return 1
  fi

  if jurisflow_health_ok; then
    return 0
  fi

  nohup setsid "$UVICORN_BIN" "$UVICORN_APP" "${UVICORN_OPTS[@]}" \
    < /dev/null >> "$LOG_FILE" 2>&1 &
  local pid=$!
  echo "$pid" > "$PID_FILE"
  disown "$pid" 2>/dev/null || true
}

jurisflow_install_cron() {
  # HostGator às vezes recusa "* * * * *" ou converte para */21.
  # Tentamos 1 min; se falhar, 5 min. flock evita overlap.
  local current job_min job_5
  current=$(crontab -l 2>/dev/null | sed '/watchdog-hostgator\.sh/d' | sed '/watchdog\.sh/d' | sed '/^$/d' || true)
  job_min="* * * * * flock -n $LOCK_FILE $APP_DIR/scripts/watchdog-hostgator.sh >/dev/null 2>&1"
  job_5="*/5 * * * * flock -n $LOCK_FILE $APP_DIR/scripts/watchdog-hostgator.sh >/dev/null 2>&1"

  if { echo "$current"; echo "$job_min"; echo "$job_5"; } | crontab - 2>/dev/null; then
    return 0
  fi
  if { echo "$current"; echo "$job_min"; } | crontab - 2>/dev/null; then
    return 0
  fi
  { echo "$current"; echo "$job_5"; } | crontab - 2>/dev/null || true
}
