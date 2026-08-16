#!/bin/bash
# Aplica keepalive do JurisFlow na HostGator (cron + supervisor + uvicorn leve).
set +e

APP_DIR="/home2/joabef36/jurisflow-ai"
cd "$APP_DIR" || exit 1

chmod +x "$APP_DIR/scripts/"*.sh 2>/dev/null || true
# Remove CRLF se o SCP veio do Windows
sed -i 's/\r$//' "$APP_DIR/scripts/"*.sh 2>/dev/null || true

# shellcheck source=lib-hostgator.sh
source "$APP_DIR/scripts/lib-hostgator.sh"

jurisflow_install_cron
echo "--- crontab ---"
crontab -l 2>/dev/null | grep -E 'watchdog|jurisflow' || true

if [ -f "$SUPERVISOR_PID_FILE" ]; then
  old=$(tr -d '[:space:]' < "$SUPERVISOR_PID_FILE" || true)
  if [ -n "${old:-}" ]; then
    kill "$old" 2>/dev/null || true
    sleep 1
    kill -9 "$old" 2>/dev/null || true
  fi
  rm -f "$SUPERVISOR_PID_FILE"
fi
pkill -f "jurisflow-supervisor-hostgator.sh" 2>/dev/null || true
jurisflow_stop || true
sleep 1

nohup setsid "$APP_DIR/scripts/jurisflow-supervisor-hostgator.sh" \
  < /dev/null >> "$SUPERVISOR_LOG" 2>&1 &
disown $! 2>/dev/null || true
sleep 8

echo "--- health ---"
curl -s -m 5 "http://127.0.0.1:${PORT}/health" || echo FAIL
echo
echo "--- processos ---"
ps aux | grep -E "uvicorn.*${PORT}|jurisflow-supervisor" | grep -v grep || true
echo "--- supervisor log ---"
tail -n 8 "$SUPERVISOR_LOG" 2>/dev/null || true
echo "OK keepalive aplicado"
