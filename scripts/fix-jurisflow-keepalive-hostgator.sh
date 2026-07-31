#!/bin/bash
# Aplica keepalive definitivo do JurisFlow no HostGator (sem fallback de chat).
# - endurece start (nohup+setsid)
# - instala cron watchdog a cada 1 minuto
# - sobe supervisor em loop (reinicia em segundos quando o LVE mata o processo)
set -eu

APP_DIR="/home2/joabef36/jurisflow-ai"
LIB="$APP_DIR/scripts/lib-hostgator.sh"
SUP="$APP_DIR/scripts/jurisflow-supervisor-hostgator.sh"

cd "$APP_DIR"

chmod +x "$SUP" "$APP_DIR/scripts/"*.sh 2>/dev/null || true

# 1) Endurecer jurisflow_start no lib
python3 - "$LIB" <<'PY'
import sys
from pathlib import Path

p = Path(sys.argv[1])
text = p.read_text()
if "nohup setsid .venv/bin/uvicorn" in text:
    print("lib: start ja endurecido")
    raise SystemExit(0)

old = '  setsid .venv/bin/uvicorn app.main:app --host 0.0.0.0 --port "$PORT" \\\n    < /dev/null >> "$LOG_FILE" 2>&1 &'
new = '  # nohup+setsid: sobrevive ao fim da sessao SSH/cron\n  nohup setsid .venv/bin/uvicorn app.main:app --host 0.0.0.0 --port "$PORT" \\\n    < /dev/null >> "$LOG_FILE" 2>&1 &'
if old not in text:
    raise SystemExit("bloco setsid nao encontrado")
p.write_text(text.replace(old, new, 1))
print("lib: start endurecido")
PY

# 2) Atualizar watchdog para tambem garantir o supervisor
python3 - "$APP_DIR/scripts/watchdog-hostgator.sh" <<'PY'
import sys
from pathlib import Path
p = Path(sys.argv[1])
text = p.read_text()
marker = "ensure_supervisor"
if marker in text:
    print("watchdog: ja garante supervisor")
    raise SystemExit(0)

insert = r'''
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
'''

# Inserir ensure_supervisor logo apos o cd
if 'cd "$APP_DIR" || exit 1' not in text:
    raise SystemExit("anchor do watchdog nao encontrado")
text = text.replace(
    'cd "$APP_DIR" || exit 1\n',
    'cd "$APP_DIR" || exit 1\n' + insert + '\n',
    1,
)
p.write_text(text)
print("watchdog: agora garante supervisor")
PY

# 3) Cron a cada 1 minuto
# shellcheck disable=SC1090
source "$LIB"
jurisflow_install_cron
echo "--- crontab ---"
crontab -l | grep watchdog || true

# 4) Parar uvicorn solto e subir via supervisor
jurisflow_stop || true
# mata supervisor antigo se houver
if [ -f "$APP_DIR/jurisflow-supervisor.pid" ]; then
  old=$(cat "$APP_DIR/jurisflow-supervisor.pid" || true)
  if [ -n "${old:-}" ]; then
    kill "$old" 2>/dev/null || true
    sleep 1
    kill -9 "$old" 2>/dev/null || true
  fi
  rm -f "$APP_DIR/jurisflow-supervisor.pid"
fi
pkill -f "jurisflow-supervisor-hostgator.sh" 2>/dev/null || true
sleep 1

nohup setsid "$SUP" < /dev/null >> "$APP_DIR/supervisor.log" 2>&1 &
disown $! 2>/dev/null || true
sleep 8

echo "--- health ---"
curl -s -m 5 "http://127.0.0.1:${PORT}/health" || echo FAIL
echo
echo "--- processos ---"
ps aux | grep -E 'uvicorn.*8097|jurisflow-supervisor' | grep -v grep || true
echo "--- supervisor log ---"
tail -n 8 "$APP_DIR/supervisor.log" 2>/dev/null || true
echo "OK keepalive aplicado"
