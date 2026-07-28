#!/usr/bin/env bash
# Instala o cron do Agente Autônomo Jurídico em produção (HostGator).
# Roda a cada 30 min, varrendo prazos/tarefas/carteira de todos os escritórios.
set -e

APP_DIR="/home2/joabef36/unio-uniojuridico"
CRON_MARK="app:juridico:agente-autonomo"
CRON_LINE="*/30 * * * * cd ${APP_DIR} && /usr/local/bin/php bin/console app:juridico:agente-autonomo >> ${APP_DIR}/var/log/agente_autonomo.log 2>&1"

mkdir -p "${APP_DIR}/var/log"

( crontab -l 2>/dev/null | grep -vF "${CRON_MARK}"; echo "${CRON_LINE}" ) | crontab -

echo "== Crontab atualizado =="
crontab -l
