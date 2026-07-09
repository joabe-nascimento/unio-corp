#!/usr/bin/env bash
# Scaffold de homolog por produto (subdominio + pastas + workflow GitHub).
#
# Uso:
#   bash scripts/scaffold-product-homolog.sh pessoas
#   bash scripts/scaffold-product-homolog.sh pessoas --apply-server   # cria subdominio/DB no servidor (SSH)
#   bash scripts/scaffold-product-homolog.sh pessoas --apply-github  # cria environment + variables (gh)
#
# Pre-requisitos opcionais:
#   SSH: ~/.ssh/unio_deploy + host joabef36@uniowork.com.br:2222
#   GitHub CLI: gh auth login

set -euo pipefail

SLUG=""
APPLY_SERVER=0
APPLY_GITHUB=0
CPANEL_USER="${CPANEL_USER:-joabef36}"
ROOT_DOMAIN="${ROOT_DOMAIN:-uniowork.com.br}"
SSH_HOST="${DEPLOY_SSH_HOST:-uniowork.com.br}"
SSH_PORT="${DEPLOY_SSH_PORT:-2222}"
SSH_USER="${DEPLOY_SSH_USER:-joabef36}"
SSH_KEY="${DEPLOY_SSH_KEY:-$HOME/.ssh/unio_deploy}"
GH_REPO="${GH_REPO:-joabe-nascimento/unio-corp}"

usage() {
  sed -n '2,12p' "$0"
  exit 1
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --apply-server) APPLY_SERVER=1 ;;
    --apply-github) APPLY_GITHUB=1 ;;
    -h|--help) usage ;;
    *)
      if [[ -z "$SLUG" ]]; then
        SLUG="$1"
      else
        echo "Argumento desconhecido: $1" >&2
        usage
      fi
      ;;
  esac
  shift
done

[[ -n "$SLUG" ]] || usage
[[ "$SLUG" =~ ^[a-z0-9-]+$ ]] || { echo "Slug invalido: use apenas a-z, 0-9, hifen"; exit 1; }

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BRANCH="product/${SLUG}"
SUBDOMAIN="${SLUG}.${ROOT_DOMAIN}"
GITHUB_ENV="product-${SLUG}"
DEPLOY_PATH="/home2/${CPANEL_USER}/unio-${SLUG}"
PUBLIC_HTML="/home2/${CPANEL_USER}/${SUBDOMAIN}"
DEFAULT_URI="https://${SUBDOMAIN}"
DB_NAME="${CPANEL_USER}_unio_${SLUG//-/_}"
DB_USER="${DB_NAME}"
WORKFLOW_FILE="${ROOT}/.github/workflows/deploy-product-${SLUG}.yml"
DEPLOY_BRANCHES_FILE="${ROOT}/config/deploy-branches.txt"
TEMPLATE="${ROOT}/.github/workflows/templates/deploy-product.yml"

slug_title() {
  echo "$SLUG" | sed 's/-/ /g' | awk '{for(i=1;i<=NF;i++) $i=toupper(substr($i,1,1)) substr($i,2)}1'
}

SLUG_TITLE="$(slug_title)"

echo "== Scaffold homolog: ${SLUG}"
echo "   branch:     ${BRANCH}"
echo "   URL:        ${DEFAULT_URI}"
echo "   app:        ${DEPLOY_PATH}"
echo "   web:        ${PUBLIC_HTML}"
echo "   GH env:     ${GITHUB_ENV}"
echo ""

# 1. deploy-branches.txt
if [[ -f "$DEPLOY_BRANCHES_FILE" ]] && grep -qxF "$BRANCH" "$DEPLOY_BRANCHES_FILE" 2>/dev/null; then
  echo "[ok] ${BRANCH} ja listado em config/deploy-branches.txt"
else
  echo "$BRANCH" >> "$DEPLOY_BRANCHES_FILE"
  echo "[+] Adicionado ${BRANCH} em config/deploy-branches.txt"
fi

# 2. workflow
if [[ -f "$WORKFLOW_FILE" ]]; then
  echo "[ok] Workflow ja existe: ${WORKFLOW_FILE}"
elif [[ ! -f "$TEMPLATE" ]]; then
  echo "[!] Template ausente: ${TEMPLATE}" >&2
  exit 1
else
  sed -e "s/__SLUG__/${SLUG}/g" \
      -e "s/__SLUG_TITLE__/${SLUG_TITLE}/g" \
      -e "s/__BRANCH__/${BRANCH//\//\\/}/g" \
      -e "s/__GITHUB_ENV__/${GITHUB_ENV}/g" \
      -e "s|__DEPLOY_PATH__|${DEPLOY_PATH}|g" \
      -e "s|__PUBLIC_HTML__|${PUBLIC_HTML}|g" \
      "$TEMPLATE" > "$WORKFLOW_FILE"
  echo "[+] Criado ${WORKFLOW_FILE}"
fi

# 3. Git branch local hint
if git show-ref --verify --quiet "refs/heads/${BRANCH}" 2>/dev/null; then
  echo "[ok] Branch local ${BRANCH} existe"
else
  echo "[i] Crie a branch: git checkout -b ${BRANCH} production"
fi

# 4. Server (optional)
if [[ "$APPLY_SERVER" == "1" ]]; then
  [[ -f "$SSH_KEY" ]] || { echo "Chave SSH nao encontrada: ${SSH_KEY}" >&2; exit 1; }
  SSH_OPTS=(-i "$SSH_KEY" -p "$SSH_PORT" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)

  echo "[*] Criando subdominio ${SUBDOMAIN}..."
  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" \
    "uapi --output=json SubDomain addsubdomain domain=${SLUG} rootdomain=${ROOT_DOMAIN} dir=${SUBDOMAIN}" \
    || echo "[i] Subdominio pode ja existir"

  DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
  APP_SECRET="$(openssl rand -hex 32)"
  DATABASE_URL="mysql://${DB_USER}:${DB_PASS}@localhost:3306/${DB_NAME}?serverVersion=5.7.44&charset=utf8mb4"

  echo "[*] Criando banco ${DB_NAME}..."
  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" bash -s <<REMOTE
set -euo pipefail
uapi --output=json Mysql create_database name=${DB_NAME} 2>/dev/null || true
uapi --output=json Mysql create_user name=${DB_USER} password='${DB_PASS}' 2>/dev/null || true
uapi --output=json Mysql set_privileges_on_database user=${DB_USER} database=${DB_NAME} privileges=ALL
REMOTE

  scp "${SSH_OPTS[@]}" "${ROOT}/scripts/setup-product-env-server.sh" \
    "${SSH_USER}@${SSH_HOST}:/tmp/setup-product-env-server.sh"
  ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" \
    "sed -i 's/\\r\$//' /tmp/setup-product-env-server.sh && chmod +x /tmp/setup-product-env-server.sh && \
     DEPLOY_PATH='${DEPLOY_PATH}' PUBLIC_HTML='${PUBLIC_HTML}' DEFAULT_URI='${DEFAULT_URI}' \
     APP_SECRET='${APP_SECRET}' DATABASE_URL='${DATABASE_URL}' \
     bash /tmp/setup-product-env-server.sh && rm -f /tmp/setup-product-env-server.sh"

  echo "[+] Servidor preparado. Credenciais gravadas em ${DEPLOY_PATH}/.env.local (nao commitar)."
fi

# 5. GitHub environment (optional)
if [[ "$APPLY_GITHUB" == "1" ]]; then
  command -v gh >/dev/null || { echo "gh CLI nao encontrado" >&2; exit 1; }
  gh api --method PUT "repos/${GH_REPO}/environments/${GITHUB_ENV}"
  gh variable set DEPLOY_PATH -e "$GITHUB_ENV" -b "$DEPLOY_PATH" -R "$GH_REPO"
  gh variable set DEPLOY_PUBLIC_HTML -e "$GITHUB_ENV" -b "$PUBLIC_HTML" -R "$GH_REPO"
  gh variable set DEFAULT_URI -e "$GITHUB_ENV" -b "$DEFAULT_URI" -R "$GH_REPO"
  echo "[+] Environment ${GITHUB_ENV} criado (vars). SSH: use secrets do repositorio."
fi

echo ""
echo "Proximos passos:"
echo "  1. git add config/deploy-branches.txt .github/workflows/deploy-product-${SLUG}.yml"
echo "  2. git commit && git push origin production"
echo "  3. git push origin production:${BRANCH}"
if [[ "$APPLY_GITHUB" != "1" ]]; then
  echo "  4. gh: environment ${GITHUB_ENV} + vars DEPLOY_PATH, DEPLOY_PUBLIC_HTML, DEFAULT_URI"
fi
if [[ "$APPLY_SERVER" != "1" ]]; then
  echo "  5. bash scripts/scaffold-product-homolog.sh ${SLUG} --apply-server"
fi
echo "  6. Push em ${BRANCH} dispara Deploy Product ${SLUG_TITLE}"
