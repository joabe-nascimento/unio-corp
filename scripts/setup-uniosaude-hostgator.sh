#!/usr/bin/env bash
# Setup/verificacao uniosaude na HostGator (rodar via SSH no servidor).
#
#   ssh -p 2222 joabef36@br1136.hostgator.com.br
#   bash /tmp/setup-uniosaude-hostgator.sh
#
set -euo pipefail

ROOT_DOMAIN="uniowork.com.br"
SUB="uniosaude"
FQDN="${SUB}.${ROOT_DOMAIN}"
DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio-uniosaude}"
PUBLIC_HTML="${PUBLIC_HTML:-/home2/joabef36/uniosaude.uniowork.com.br}"
SERVER_IP="${SERVER_IP:-50.6.138.130}"

echo "== Unio Saúde — setup HostGator"
echo "   URL:  https://${FQDN}"
echo "   app:  ${DEPLOY_PATH}"
echo "   web:  ${PUBLIC_HTML}"
echo ""

echo "[1/6] Subdominio cPanel..."
uapi --output=json SubDomain addsubdomain \
  domain="${SUB}" rootdomain="${ROOT_DOMAIN}" dir="${FQDN}" 2>&1 \
  | grep -q '"status":1' && echo "  criado" || echo "  ja existia (OK)"

LEGACY_APP="/home2/joabef36/unio-clinicaunio"
LEGACY_ENV="${LEGACY_APP}/.env.local"
if [[ -f "${LEGACY_ENV}" && ! -f "${DEPLOY_PATH}/.env.local" ]]; then
  echo "[1b/6] Migrando .env.local de clinicaunio..."
  mkdir -p "${DEPLOY_PATH}"
  cp "${LEGACY_ENV}" "${DEPLOY_PATH}/.env.local"
  sed -i 's|clinicaunio.uniowork.com.br|uniosaude.uniowork.com.br|g' "${DEPLOY_PATH}/.env.local"
  sed -i 's|unio-clinicaunio|unio-uniosaude|g' "${DEPLOY_PATH}/.env.local" || true
  chmod 600 "${DEPLOY_PATH}/.env.local"
  echo "  OK"
fi

if [[ -d "${LEGACY_APP}/vendor" && ! -d "${DEPLOY_PATH}/vendor" ]]; then
  echo "[1c/6] Copiando app de clinicaunio (primeira migracao)..."
  mkdir -p "${DEPLOY_PATH}"
  rsync -a \
    --exclude='var/cache' \
    --exclude='var/log' \
    --exclude='var/sessions' \
    "${LEGACY_APP}/" "${DEPLOY_PATH}/" 2>/dev/null || true
  sed -i 's|clinicaunio.uniowork.com.br|uniosaude.uniowork.com.br|g' "${DEPLOY_PATH}/.env.local" 2>/dev/null || true
  echo "  OK"
fi

echo "[2/6] PHP 8.2 no vhost..."
uapi --output=json LangPHP php_set_vhost_versions version=ea-php82 vhost="${FQDN}" >/dev/null 2>&1 \
  || uapi --output=json LangPHP php_set_vhost_versions version=ea-php81 vhost="${FQDN}" >/dev/null 2>&1 \
  || echo "  AVISO: nao foi possivel definir PHP via uapi"
echo "  OK"

echo "[3/6] AutoSSL..."
uapi --output=json SSL start_autossl_check >/dev/null 2>&1 || true
echo "  solicitado (pode levar alguns minutos)"

echo "[4/6] Symfony cache..."
if [[ -f "${DEPLOY_PATH}/bin/console" ]]; then
  cd "${DEPLOY_PATH}"
  php bin/console cache:clear --env=prod --no-warmup 2>/dev/null || true
  php bin/console cache:warmup --env=prod 2>/dev/null || true
  echo "  OK"
else
  echo "  AVISO: app ainda nao deployada em ${DEPLOY_PATH}"
fi

echo "[5/6] Reparar document root e Apache..."
if command -v uapi >/dev/null 2>&1; then
  uapi --output=json SubDomain changedocroot \
    domain="${SUB}" rootdomain="${ROOT_DOMAIN}" docroot="${FQDN}" >/dev/null 2>&1 \
    && echo "  docroot confirmado: ${PUBLIC_HTML}" \
    || echo "  AVISO: changedocroot nao aplicou (subdominio pode ja estar OK)"
fi
if [[ -x /scripts/rebuildhttpdconf ]]; then
  /scripts/rebuildhttpdconf 2>/dev/null && echo "  rebuildhttpdconf OK" || true
fi
if [[ -x /scripts/restartsrv_httpd ]]; then
  /scripts/restartsrv_httpd 2>/dev/null && echo "  restartsrv_httpd OK" || true
fi

echo "[6/6] Smoke (vhost local + HTTPS publico via IP)..."
HTTP_LOCAL="$(curl -sS -o /dev/null -w '%{http_code}' \
  -H "Host: ${FQDN}" --connect-timeout 10 --max-time 20 \
  "http://127.0.0.1/login" 2>/dev/null || echo '000')"
echo "  HTTP  vhost local /login => ${HTTP_LOCAL}"

HTTPS_EXT="$(curl -sS -o /dev/null -w '%{http_code}' \
  --resolve "${FQDN}:443:${SERVER_IP}" -k --connect-timeout 10 --max-time 20 \
  "https://${FQDN}/login" 2>/dev/null || echo '000')"
echo "  HTTPS externo /login => ${HTTPS_EXT}"

if [[ "$HTTPS_EXT" != "200" && "$HTTPS_EXT" != "302" ]]; then
  echo "  AVISO: HTTPS ainda nao responde — aguarde AutoSSL (5-30 min) ou rode setup de novo."
fi

echo ""
echo "=== DNS HostGator (ns1136) ==="
if command -v dig >/dev/null 2>&1; then
  dig +short "@ns1136.hostgator.com.br" A "${FQDN}" || true
else
  nslookup "${FQDN}" ns1136.hostgator.com.br 2>/dev/null | grep -i address | tail -1 || true
fi

echo ""
echo "=== DNS publico (internet) ==="
if command -v dig >/dev/null 2>&1; then
  PUB="$(dig +short A "${FQDN}" | head -1 || true)"
else
  PUB=""
fi
if [[ -z "${PUB}" ]]; then
  echo "  FALTA: ${FQDN} nao resolve na internet."
  echo "  O cPanel ja tem o registro A; falta alinhar os nameservers do"
  echo "  dominio ${ROOT_DOMAIN} no Registro.br para:"
  echo "    ns1136.hostgator.com.br"
  echo "    ns1137.hostgator.com.br"
  echo "  Isso NAO da para fazer pelo terminal da HostGator — so no Registro.br."
else
  echo "  OK: ${FQDN} => ${PUB}"
fi

echo ""
echo "Concluido."
