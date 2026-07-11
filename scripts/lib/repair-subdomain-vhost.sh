#!/usr/bin/env bash
# Confirma docroot de subdominio no cPanel e recarrega Apache.
# Aplica apenas quando DEFAULT_URI/VHOST e um subdominio (ex.: uniosaude.uniowork.com.br).
#
# Requer: PUBLIC_HTML
# Opcional: DEFAULT_URI, SERVER_IP (smoke HTTPS), ROOT_DOMAIN (default uniowork.com.br)

set -euo pipefail

PUBLIC_HTML="${PUBLIC_HTML:?PUBLIC_HTML obrigatorio}"
DEFAULT_URI="${DEFAULT_URI:-}"
SERVER_IP="${SERVER_IP:-50.6.138.130}"
ROOT_DOMAIN="${ROOT_DOMAIN:-uniowork.com.br}"

VHOST="${DEFAULT_URI#https://}"
VHOST="${VHOST#http://}"
VHOST="${VHOST%%/*}"

if [[ -z "$VHOST" || "$VHOST" == "$ROOT_DOMAIN" || "$VHOST" == "www.${ROOT_DOMAIN}" ]]; then
  echo "repair-subdomain-vhost: skip (dominio principal ou sem DEFAULT_URI)"
  exit 0
fi

if [[ "$VHOST" != *".${ROOT_DOMAIN}" ]]; then
  echo "repair-subdomain-vhost: skip (vhost fora de ${ROOT_DOMAIN})"
  exit 0
fi

SUB="${VHOST%%.${ROOT_DOMAIN}}"
DOCROOT="$(basename "$PUBLIC_HTML")"

if command -v uapi >/dev/null 2>&1; then
  uapi --output=json SubDomain changedocroot \
    domain="${SUB}" rootdomain="${ROOT_DOMAIN}" docroot="${DOCROOT}" >/dev/null 2>&1 \
    && echo "docroot confirmado: ${PUBLIC_HTML}" \
    || echo "AVISO: changedocroot nao aplicou (subdominio pode ja estar OK)"
fi

if [[ -x /scripts/rebuildhttpdconf ]]; then
  /scripts/rebuildhttpdconf 2>/dev/null && echo "rebuildhttpdconf OK" || true
fi
if [[ -x /scripts/restartsrv_httpd ]]; then
  /scripts/restartsrv_httpd 2>/dev/null && echo "restartsrv_httpd OK" || true
fi

HTTP_LOCAL="$(curl -sS -o /dev/null -w '%{http_code}' \
  -H "Host: ${VHOST}" --connect-timeout 10 --max-time 20 \
  "http://127.0.0.1/login" 2>/dev/null || echo '000')"
HTTPS_EXT="$(curl -sS -o /dev/null -w '%{http_code}' \
  --resolve "${VHOST}:443:${SERVER_IP}" -k --connect-timeout 10 --max-time 20 \
  "https://${VHOST}/login" 2>/dev/null || echo '000')"
echo "smoke HTTP  vhost local /login => ${HTTP_LOCAL}"
echo "smoke HTTPS externo /login => ${HTTPS_EXT}"

if [[ "$HTTPS_EXT" != "200" && "$HTTPS_EXT" != "302" ]]; then
  echo "AVISO: HTTPS retorna ${HTTPS_EXT} — confira AutoSSL ou docroot no cPanel"
fi
