#!/bin/bash
# Migra JurisFlow para a porta 8098 (zumbis CageFS na 8097) e atualiza o Symfony.
set -eu

APP=/home2/joabef36/jurisflow-ai
SYM=/home2/joabef36/unio-uniojuridico
NEW_PORT=8098

cd "$APP"

# 1) Atualiza PORT no lib-hostgator.sh
python3 - <<PY
from pathlib import Path
p = Path("$APP/scripts/lib-hostgator.sh")
text = p.read_text()
import re
text2, n = re.subn(r"^PORT=\d+", "PORT=$NEW_PORT", text, count=1, flags=re.M)
if n != 1:
    raise SystemExit(f"PORT nao atualizado (n={n})")
p.write_text(text2)
print("lib PORT -> $NEW_PORT")
PY

# 2) Atualiza .env.hostgator / .env se houver PORT
for f in .env.hostgator .env; do
  if [ -f "$f" ]; then
    sed -i "s/8097/$NEW_PORT/g" "$f" || true
  fi
done

# 3) Atualiza LEGAL_AI_URL no Symfony
if [ -f "$SYM/.env.local" ]; then
  if grep -q '^LEGAL_AI_URL=' "$SYM/.env.local"; then
    sed -i "s|^LEGAL_AI_URL=.*|LEGAL_AI_URL=http://127.0.0.1:$NEW_PORT|" "$SYM/.env.local"
  else
    echo "LEGAL_AI_URL=http://127.0.0.1:$NEW_PORT" >> "$SYM/.env.local"
  fi
  echo "Symfony .env.local atualizado"
  grep '^LEGAL_AI_URL=' "$SYM/.env.local"
fi

# 4) Sobe na nova porta
source scripts/lib-hostgator.sh
echo "PORT efetivo=$PORT"
jurisflow_stop || true
if [ -f jurisflow-supervisor.pid ]; then
  kill "$(cat jurisflow-supervisor.pid)" 2>/dev/null || true
fi
pkill -f jurisflow-supervisor-hostgator.sh 2>/dev/null || true
sleep 2

# garante que 8098 esta livre
if curl -sf -m 2 "http://127.0.0.1:$PORT/health" >/dev/null 2>&1; then
  echo "AVISO: $PORT ja responde health (outro processo). Abortando."
  exit 1
fi

jurisflow_start
sleep 8
echo -n "HEALTH="
curl -s -m 5 "http://127.0.0.1:$PORT/health" || echo FAIL
echo

nohup setsid bash scripts/jurisflow-supervisor-hostgator.sh </dev/null >> supervisor.log 2>&1 &
sleep 2

# 5) Reinstala cron (usa PORT do lib)
jurisflow_install_cron
crontab -l | grep watchdog

# 6) Limpa cache Symfony para pegar novo LEGAL_AI_URL
cd "$SYM"
php bin/console cache:clear --env=prod --no-debug >/tmp/unio-cache-clear.log 2>&1 || true
echo "cache clear done"
grep LEGAL_AI_URL .env.local || true
