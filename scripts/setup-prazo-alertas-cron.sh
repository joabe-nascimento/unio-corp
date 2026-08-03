#!/usr/bin/env bash
# Cron de alertas de prazo — diário às 7h (horário de Brasília).
set -e

APP_DIR="/home2/joabef36/unio-uniojuridico"
CRON_MARK="app:juridico:prazo-alertas"
CRON_LINE="0 7 * * * cd ${APP_DIR} && /usr/local/bin/php bin/console app:juridico:prazo-alertas >> ${APP_DIR}/var/log/prazo_alertas.log 2>&1"

mkdir -p "${APP_DIR}/var/log"

( crontab -l 2>/dev/null | grep -vF "${CRON_MARK}"; echo "${CRON_LINE}" ) | crontab -

echo "== Cron alertas de prazo (7h) instalado =="
crontab -l | grep -F "${CRON_MARK}" || true
