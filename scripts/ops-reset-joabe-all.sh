#!/usr/bin/env bash
# Limpa sessões e redefine senha do Joabe em todas as instâncias Unio.
set -euo pipefail

BASE="${BASE:-/home2/joabef36}"
EMAIL_DEV="joabe.nascimento@unio.dev"
EMAIL_OWNER="joabe@uniowork.com.br"

bash "$(dirname "$0")/ops-clear-all-sessions.sh"

NEWPASS="$(php -r 'echo bin2hex(random_bytes(6))."Un!o".random_int(10,99);')"
echo "GENERATED_PASSWORD=${NEWPASS}"

set_password() {
  local app="$1"
  local email="$2"
  local path="${BASE}/${app}"
  if [[ ! -f "${path}/bin/console" ]]; then
    echo "SKIP ${app} (sem bin/console)"
    return 0
  fi
  echo "==== ${app} / ${email} ===="
  if (cd "${path}" && php bin/console app:user:set-password \
    --email="${email}" \
    --password="${NEWPASS}" \
    --allow-prod \
    --no-interaction 2>&1); then
    echo "OK ${app} ${email}"
  else
    echo "WARN: usuário ausente em ${app}: ${email}"
  fi
}

set_password "unio-uniojuridico" "${EMAIL_DEV}"
set_password "unio-uniojuridico" "${EMAIL_OWNER}"
set_password "unio-uniosaude" "${EMAIL_DEV}"
set_password "unio" "${EMAIL_OWNER}"

echo "DONE"
