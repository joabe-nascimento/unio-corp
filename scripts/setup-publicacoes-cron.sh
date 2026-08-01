#!/usr/bin/env bash
# Instala cron de captura DJEN (Publicações & Intimações) — diário às 6h (horário de Brasília).
set -e

APP_DIR="/home2/joabef36/unio-uniojuridico"
CRON_MARK="app:juridico:capturar-publicacoes"
CRON_LINE="0 6 * * * cd ${APP_DIR} && /usr/local/bin/php bin/console app:juridico:capturar-publicacoes >> ${APP_DIR}/var/log/publicacoes_captura.log 2>&1"

mkdir -p "${APP_DIR}/var/log"

( crontab -l 2>/dev/null | grep -vF "${CRON_MARK}"; echo "${CRON_LINE}" ) | crontab -

echo "== Cron captura DJEN (6h) instalado =="
crontab -l | grep -F "${CRON_MARK}" || true
