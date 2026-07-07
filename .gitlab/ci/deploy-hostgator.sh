#!/usr/bin/env bash
# Deploy HostGator — equivalente ao deploy-reusable.yml (GitHub Actions).
set -euo pipefail

: "${DEPLOY_PATH:?DEPLOY_PATH required}"
: "${DEPLOY_PUBLIC_HTML:?DEPLOY_PUBLIC_HTML required}"
: "${ARCHIVE_REMOTE:?ARCHIVE_REMOTE required}"
: "${DEPLOY_SSH_KEY:?DEPLOY_SSH_KEY required}"
: "${DEPLOY_SSH_USER:?DEPLOY_SSH_USER required}"
: "${DEPLOY_SSH_PORT:?DEPLOY_SSH_PORT required}"

SKIP_UNIO_PLATFORM_STEPS="${SKIP_UNIO_PLATFORM_STEPS:-0}"
DEPLOY_SSH_ALIAS="${DEPLOY_SSH_ALIAS:-hg-deploy}"

chmod +x scripts/ci-ssh-setup.sh scripts/ci-retry.sh scripts/ci-remote-extract.sh

mkdir -p ~/.ssh
printf '%s\n' "$DEPLOY_SSH_KEY" | tr -d '\r' > ~/.ssh/deploy_key
chmod 600 ~/.ssh/deploy_key

export DEPLOY_HOST="${DEPLOY_SSH_HOST:-}"
export DEPLOY_CANONICAL_HOST="${DEPLOY_SSH_CANONICAL_HOST:-}"
export DEPLOY_USER="$DEPLOY_SSH_USER"
export DEPLOY_PORT="$DEPLOY_SSH_PORT"
export DEPLOY_KEY_FILE="$HOME/.ssh/deploy_key"
export DEPLOY_SSH_ALIAS
bash scripts/ci-ssh-setup.sh

composer install --no-dev --no-progress --prefer-dist --optimize-autoloader --no-scripts
npm ci
npm run vendor:sync
php bin/minify-css.php

ARCHIVE="${CI_PROJECT_DIR}/.ci-deploy.tar.gz"
tar --exclude='.git' \
    --exclude='.gitlab-ci.yml' \
    --exclude='.gitlab' \
    --exclude='node_modules' \
    --exclude='.env' \
    --exclude='.env.*' \
    --exclude='var/cache' \
    --exclude='var/log' \
    --exclude='public/uploads' \
    --exclude='tests' \
    --exclude='docs' \
    --exclude='services' \
    --exclude='.cursor' \
    --exclude='deploy.tar.gz' \
    --exclude='phpunit.xml.dist' \
    --exclude='.phpunit.cache' \
    -czf "$ARCHIVE" .

bash scripts/ci-retry.sh scp "$ARCHIVE" "${DEPLOY_SSH_ALIAS}:/tmp/${ARCHIVE_REMOTE}"

ENV_FILE="${CI_PROJECT_DIR}/.ci-deploy-remote.env"
{
  printf 'DEPLOY_PATH=%s\n' "$DEPLOY_PATH"
  printf 'PUBLIC_HTML=%s\n' "$DEPLOY_PUBLIC_HTML"
  printf 'GITHUB_SHA=%s\n' "${CI_COMMIT_SHA:-unknown}"
  printf 'GITHUB_REF_NAME=%s\n' "${CI_COMMIT_REF_NAME:-unknown}"
  if [[ "$SKIP_UNIO_PLATFORM_STEPS" == "1" ]]; then
    printf 'SKIP_UNIO_PLATFORM_STEPS=1\n'
  else
    printf 'MAILBOX_JOABE_PASSWORD=%s\n' "${MAILBOX_JOABE_PASSWORD:-}"
    printf 'MAILBOX_UNIO_PASSWORD=%s\n' "${MAILBOX_UNIO_PASSWORD:-}"
    printf 'PLATFORM_OWNER_PASSWORD=%s\n' "${PLATFORM_OWNER_PASSWORD:-}"
  fi
} > "$ENV_FILE"

bash scripts/ci-retry.sh scp "$ENV_FILE" "${DEPLOY_SSH_ALIAS}:/tmp/deploy-remote.env"
bash scripts/ci-retry.sh scp scripts/ci-remote-extract.sh "${DEPLOY_SSH_ALIAS}:/tmp/ci-remote-extract.sh"
bash scripts/ci-retry.sh ssh "${DEPLOY_SSH_ALIAS}" "ARCHIVE=${ARCHIVE_REMOTE} bash /tmp/ci-remote-extract.sh"

mkdir -p deploy-reports
scp "${DEPLOY_SSH_ALIAS}:${DEPLOY_PATH}/var/log/deploy-report.txt" \
    deploy-reports/deploy-report.txt 2>/dev/null \
  || echo "Relatorio remoto indisponivel" > deploy-reports/deploy-report.txt

cat deploy-reports/deploy-report.txt
