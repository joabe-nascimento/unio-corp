#!/bin/bash
# Mantém o uvicorn vivo na HostGator. Nunca sai do loop (LVE mata o filho, não o pai).
set +e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib-hostgator.sh
source "$SCRIPT_DIR/lib-hostgator.sh"

cd "$APP_DIR" || exit 1

echo $$ > "$SUPERVISOR_PID_FILE"
jurisflow_touch_heartbeat

if [ -f .env.hostgator ]; then
  cp -f .env.hostgator .env
fi

if [ ! -x "$UVICORN_BIN" ]; then
  echo "$(date '+%Y-%m-%d %H:%M:%S') - ERRO: uvicorn nao encontrado" >> "$SUPERVISOR_LOG"
  exit 1
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - supervisor iniciado (PID $$), porta $PORT" >> "$SUPERVISOR_LOG"

# Não mata um uvicorn saudável (evita bind conflict / zumbi CageFS).
while true; do
  jurisflow_touch_heartbeat

  if jurisflow_health_ok; then
    sleep 20
    continue
  fi

  echo "$(date '+%Y-%m-%d %H:%M:%S') - offline, subindo uvicorn na porta $PORT..." >> "$SUPERVISOR_LOG"

  "$UVICORN_BIN" "$UVICORN_APP" "${UVICORN_OPTS[@]}" >> "$LOG_FILE" 2>&1 &
  child=$!
  echo "$child" > "$PID_FILE"
  echo "$(date '+%Y-%m-%d %H:%M:%S') - uvicorn PID $child" >> "$SUPERVISOR_LOG"

  waited=0
  while kill -0 "$child" 2>/dev/null; do
    jurisflow_touch_heartbeat
    sleep 8
    waited=$((waited + 8))
    # Se o PID vive mas a porta não responde por 40s, mata e sobe de novo.
    if [ "$waited" -ge 40 ] && ! jurisflow_health_ok; then
      echo "$(date '+%Y-%m-%d %H:%M:%S') - PID $child sem /health; encerrando" >> "$SUPERVISOR_LOG"
      kill "$child" 2>/dev/null || true
      sleep 1
      kill -9 "$child" 2>/dev/null || true
      break
    fi
  done

  echo "$(date '+%Y-%m-%d %H:%M:%S') - uvicorn PID $child morreu; reiniciando em 2s" >> "$SUPERVISOR_LOG"
  rm -f "$PID_FILE"
  sleep 2
done
