#!/bin/bash
# Supervisor local do JurisFlow no HostGator.
# Mantem o uvicorn vivo: quando o LVE mata o processo (~120s CPU),
# este loop reinicia em segundos — sem depender do cron.
set -eu

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-hostgator.sh
source "$SCRIPT_DIR/lib-hostgator.sh"

cd "$APP_DIR" || exit 1

SUPERVISOR_PID_FILE="$APP_DIR/jurisflow-supervisor.pid"
SUPERVISOR_LOG="$APP_DIR/supervisor.log"

echo $$ > "$SUPERVISOR_PID_FILE"

if [ -f .env.hostgator ]; then
  cp -f .env.hostgator .env
fi

if [ ! -x .venv/bin/uvicorn ]; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') - ERRO: .venv/bin/uvicorn nao encontrado" >> "$SUPERVISOR_LOG"
  exit 1
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - supervisor iniciado (PID $$), porta $PORT" >> "$SUPERVISOR_LOG"

# Mata qualquer uvicorn solto da mesma porta antes do loop.
pkill -f "uvicorn app.main:app --host 0.0.0.0 --port $PORT" 2>/dev/null || true
sleep 1

while true; do
  # Se a porta ja responde, nao sobe outro processo (evita bind conflict).
  if jurisflow_health_ok; then
    sleep 15
    continue
  fi

  echo "$(date '+%Y-%m-%d %H:%M:%S') - offline, subindo uvicorn na porta $PORT..." >> "$SUPERVISOR_LOG"

  # Roda em foreground neste loop: quando o LVE matar, o wait termina e reiniciamos.
  .venv/bin/uvicorn app.main:app --host 0.0.0.0 --port "$PORT" \
    >> "$LOG_FILE" 2>&1 &
  child=$!
  echo "$child" > "$PID_FILE"
  echo "$(date '+%Y-%m-%d %H:%M:%S') - uvicorn PID $child" >> "$SUPERVISOR_LOG"

  # Espera o processo morrer (ou sleep curto se health falhar).
  while kill -0 "$child" 2>/dev/null; do
    sleep 5
  done

  echo "$(date '+%Y-%m-%d %H:%M:%S') - uvicorn PID $child morreu; reiniciando em 2s" >> "$SUPERVISOR_LOG"
  rm -f "$PID_FILE"
  sleep 2
done
