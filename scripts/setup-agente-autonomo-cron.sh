#!/usr/bin/env bash
# Instala os crons jurídicos em produção (HostGator):
#   1. Agente Autônomo — a cada 30 min, varrendo prazos/tarefas/carteira de todos os escritórios.
#   2. Resync do RAG — a cada 6h, reindexando a biblioteca de documentos no JurisFlow
#      (necessário pois o RAG do JurisFlow é em memória e some a cada restart do processo Python).
set -e

APP_DIR="/home2/joabef36/unio-uniojuridico"
CRON_MARK="app:juridico:agente-autonomo"
CRON_LINE="*/30 * * * * cd ${APP_DIR} && /usr/local/bin/php bin/console app:juridico:agente-autonomo >> ${APP_DIR}/var/log/agente_autonomo.log 2>&1"

RAG_MARK="app:juridico:rag:sync"
RAG_LINE="0 */6 * * * cd ${APP_DIR} && /usr/local/bin/php bin/console app:juridico:rag:sync >> ${APP_DIR}/var/log/rag_sync.log 2>&1"

PRAZO_MARK="app:juridico:prazo-alertas"
PRAZO_LINE="0 7 * * * cd ${APP_DIR} && /usr/local/bin/php bin/console app:juridico:prazo-alertas >> ${APP_DIR}/var/log/prazo_alertas.log 2>&1"

mkdir -p "${APP_DIR}/var/log"

( crontab -l 2>/dev/null | grep -vF "${CRON_MARK}" | grep -vF "${RAG_MARK}" | grep -vF "${PRAZO_MARK}"; echo "${CRON_LINE}"; echo "${RAG_LINE}"; echo "${PRAZO_LINE}" ) | crontab -

echo "== Crontab atualizado =="
crontab -l
