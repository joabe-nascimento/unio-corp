#!/usr/bin/env bash
# Setup/verificacao uniojuridico na HostGator (rodar via SSH no servidor).
#
#   ssh -p 2222 joabef36@br1136.hostgator.com.br
#   bash /tmp/setup-uniojuridico-hostgator.sh
#
set -euo pipefail

ROOT_DOMAIN="uniowork.com.br"
SUB="uniojuridico"
FQDN="${SUB}.${ROOT_DOMAIN}"
DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio-uniojuridico}"
PUBLIC_HTML="${PUBLIC_HTML:-/home2/joabef36/uniojuridico.uniowork.com.br}"
SERVER_IP="${SERVER_IP:-50.6.138.130}"
CPANEL_USER="${CPANEL_USER:-joabef36}"
DB_NAME="${DB_NAME:-${CPANEL_USER}_unio_uniojuridico}"
DB_USER="${DB_USER:-${CPANEL_USER}_uniojuridico_rw}"

DB_PASS="${DB_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)}"
APP_SECRET="${APP_SECRET:-$(openssl rand -hex 32)}"
DATABASE_URL="${DATABASE_URL:-mysql://${DB_USER}:${DB_PASS}@localhost:3306/${DB_NAME}?serverVersion=5.7.44&charset=utf8mb4}"

echo "== Unio Jurídico — setup HostGator"
echo "   URL:  https://${FQDN}"
echo "   app:  ${DEPLOY_PATH}"
echo "   web:  ${PUBLIC_HTML}"
echo "   db:   ${DB_NAME}"
echo ""

echo "[1/7] Subdominio cPanel..."
uapi --output=json SubDomain addsubdomain \
  domain="${SUB}" rootdomain="${ROOT_DOMAIN}" dir="${FQDN}" 2>&1 \
  | grep -q '"status":1' && echo "  criado" || echo "  ja existia (OK)"

echo "[2/7] Banco MySQL..."
uapi --output=json Mysql create_database name="${DB_NAME}" 2>/dev/null || true
uapi --output=json Mysql create_user name="${DB_USER}" password="${DB_PASS}" 2>/dev/null || true
uapi --output=json Mysql set_privileges_on_database user="${DB_USER}" database="${DB_NAME}" privileges=ALL
echo "  OK"

echo "[3/7] Pastas e .env.local..."
mkdir -p "${DEPLOY_PATH}"/{var/cache,var/log,var/sessions,public/uploads/users,public/uploads/chat/voice,public/uploads/chat/files,public/uploads/config}
mkdir -p "${PUBLIC_HTML}"
chmod -R ug+rwx "${DEPLOY_PATH}/var" "${DEPLOY_PATH}/public/uploads" 2>/dev/null || true

ENV_FILE="${DEPLOY_PATH}/.env.local"
if [[ ! -f "${ENV_FILE}" ]]; then
  cat > "${ENV_FILE}" <<ENV
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=${APP_SECRET}

DEFAULT_URI=https://${FQDN}

DATABASE_URL="${DATABASE_URL}"

MAILER_DSN=null://null
MAILER_FROM_ADDRESS=noreply@uniowork.com.br

MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

REDIS_URL=

UNIO_ORGANISMO_ENABLED=true
UNIO_ORGANISMO_PULSO_HOME=true
UNIO_ORGANISMO_BRAND_NAME="Unio Jurídico"
UNIO_ORGANISMO_BRAND_SLOGAN="Justiça que acompanha."
UNIO_ORGANISMO_HERO_TITLE="Gestão jurídica"
UNIO_ORGANISMO_HERO_TITLE_ACCENT="inteligente e conectada."
UNIO_ORGANISMO_HERO_DESC="Unio Jurídico reúne clientes, processos, prazos, documentos e uma IA jurídica dedicada em um único lugar."
UNIO_ORGANISMO_LUMEN_SUBTITLE="Assistente jurídica"
UNIO_ORGANISMO_UNIT_LABEL="Escritório"
UNIO_ORGANISMO_UNIT_LABEL_ARTIGO="do escritório"
UNIO_ORGANISMO_UNIT_LABEL_PLURAL="Escritórios"
UNIO_ORGANISMO_NAV_MATURIDADE="Painel de Processos"
UNIO_ORGANISMO_NAV_SECTION_CLIENTS="Clientes"
UNIO_ORGANISMO_NAV_PACIENTES="Clientes"
UNIO_ORGANISMO_NAV_SALA_CRITICA="Prazos Críticos"
UNIO_ORGANISMO_NAV_ALERTAS="Prazos & Alertas"
UNIO_ORGANISMO_NAV_SECTION_DELIVERABLES="Acompanhamento"
UNIO_ORGANISMO_NAV_PROTOCOLOS="Modelos de Petição"
UNIO_ORGANISMO_NAV_QUESTIONARIOS="Formulários"
UNIO_ORGANISMO_NAV_PORTAL="Portal do Cliente"
UNIO_ORGANISMO_PULSO_PROJECTS_ACTIVE="Casos ativos"
UNIO_ORGANISMO_PULSO_IN_PROGRESS="Em andamento"
UNIO_ORGANISMO_PULSO_CASES_HEADING="Processos em andamento"
UNIO_ORGANISMO_PULSO_KPIS_HEADING="Indicadores do escritório"
UNIO_ORGANISMO_PULSO_EMPTY="Nenhum processo ativo — tudo tranquilo no escritório."
UNIO_ORGANISMO_HUB_HERO_TITLE="Painel jurídico"
UNIO_ORGANISMO_MARKETING_EYEBROW="Plataforma de gestão jurídica"
UNIO_ORGANISMO_MARKETING_TAGLINE="Justiça que acompanha."

VITORIA_AI_ENABLED=false
VITORIA_AI_URL=
VITORIA_AI_KEY=

LEGAL_AI_ENABLED=true
LEGAL_AI_URL=http://127.0.0.1:8098
LEGAL_AI_ESCRITORIO_ID=default

MERCURE_URL=
MERCURE_PUBLIC_URL=
MERCURE_JWT_SECRET=
ENV
  chmod 600 "${ENV_FILE}"
  echo "  criado ${ENV_FILE}"
else
  echo "  mantido ${ENV_FILE} (ja existia)"
fi

echo "[4/7] PHP 8.2 no vhost..."
uapi --output=json LangPHP php_set_vhost_versions version=ea-php82 vhost="${FQDN}" >/dev/null 2>&1 \
  || uapi --output=json LangPHP php_set_vhost_versions version=ea-php81 vhost="${FQDN}" >/dev/null 2>&1 \
  || echo "  AVISO: nao foi possivel definir PHP via uapi"
echo "  OK"

echo "[5/7] AutoSSL..."
uapi --output=json SSL start_autossl_check >/dev/null 2>&1 || true
echo "  solicitado (pode levar alguns minutos)"

echo "[6/7] Reparar document root e Apache..."
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

echo "[7/7] Smoke (vhost local + HTTPS publico via IP)..."
HTTP_LOCAL="$(curl -sS -o /dev/null -w '%{http_code}' \
  -H "Host: ${FQDN}" --connect-timeout 10 --max-time 20 \
  "http://127.0.0.1/login" 2>/dev/null || echo '000')"
echo "  HTTP  vhost local /login => ${HTTP_LOCAL}"

HTTPS_EXT="$(curl -sS -o /dev/null -w '%{http_code}' \
  --resolve "${FQDN}:443:${SERVER_IP}" -k --connect-timeout 10 --max-time 20 \
  "https://${FQDN}/login" 2>/dev/null || echo '000')"
echo "  HTTPS externo /login => ${HTTPS_EXT}"

if [[ "$HTTPS_EXT" != "200" && "$HTTPS_EXT" != "302" && "$HTTPS_EXT" != "403" ]]; then
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
echo "Concluido."
