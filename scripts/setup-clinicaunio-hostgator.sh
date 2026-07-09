#!/usr/bin/env bash
# Setup/verificacao clinicaunio na HostGator (rodar via SSH no servidor).
#
#   ssh -p 2222 joabef36@br1136.hostgator.com.br
#   bash /tmp/setup-clinicaunio-hostgator.sh
#
set -euo pipefail

ROOT_DOMAIN="uniowork.com.br"
SUB="clinicaunio"
FQDN="${SUB}.${ROOT_DOMAIN}"
DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio-clinicaunio}"
PUBLIC_HTML="${PUBLIC_HTML:-/home2/joabef36/clinicaunio.uniowork.com.br}"
SERVER_IP="${SERVER_IP:-50.6.138.130}"

echo "== Clinica Unio — setup HostGator"
echo "   URL:  https://${FQDN}"
echo "   app:  ${DEPLOY_PATH}"
echo "   web:  ${PUBLIC_HTML}"
echo ""

echo "[1/5] Subdominio cPanel..."
uapi --output=json SubDomain addsubdomain \
  domain="${SUB}" rootdomain="${ROOT_DOMAIN}" dir="${FQDN}" 2>&1 \
  | grep -q '"status":1' && echo "  criado" || echo "  ja existia (OK)"

echo "[2/5] PHP 8.2 no vhost..."
uapi --output=json LangPHP php_set_vhost_versions version=ea-php82 vhost="${FQDN}" >/dev/null 2>&1 \
  || uapi --output=json LangPHP php_set_vhost_versions version=ea-php81 vhost="${FQDN}" >/dev/null 2>&1 \
  || echo "  AVISO: nao foi possivel definir PHP via uapi"
echo "  OK"

echo "[3/5] AutoSSL..."
uapi --output=json SSL start_autossl_check >/dev/null 2>&1 || true
echo "  solicitado (pode levar alguns minutos)"

echo "[4/5] Symfony cache..."
if [[ -f "${DEPLOY_PATH}/bin/console" ]]; then
  cd "${DEPLOY_PATH}"
  php bin/console cache:clear --env=prod --no-warmup 2>/dev/null || true
  php bin/console cache:warmup --env=prod 2>/dev/null || true
  echo "  OK"
else
  echo "  AVISO: app ainda nao deployada em ${DEPLOY_PATH}"
fi

echo "[5/5] Smoke (forca DNS local para o IP do servidor)..."
HTTP_CODE="$(curl -sS -o /dev/null -w '%{http_code}' \
  --resolve "${FQDN}:443:${SERVER_IP}" -k "https://${FQDN}/login" 2>/dev/null || echo '000')"
echo "  HTTPS /login => HTTP ${HTTP_CODE}"

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
