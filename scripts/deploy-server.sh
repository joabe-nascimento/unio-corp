#!/usr/bin/env bash
# Pós-deploy na HostGator — migrations, cache e sync de estáticos para public_html.
# Uso manual: bash scripts/deploy-server.sh
# Chamado pelo GitHub Actions após extração do tar.
# Relatório: var/log/deploy-report.txt

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio}"
PUBLIC_HTML="${PUBLIC_HTML:-/home2/joabef36/public_html}"
PHP_BIN="${PHP_BIN:-php}"
CSS_MARKER="${CSS_MARKER:-core-projetos-toolbar}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck source=scripts/lib/ci-report.sh
source "$ROOT/scripts/lib/ci-report.sh"
# shellcheck source=scripts/lib/backup-database.sh
source "$ROOT/scripts/lib/backup-database.sh"

CI_REPORT_KIND=deploy
CI_REPORT_FILE="${DEPLOY_PATH}/var/log/deploy-report.txt"
ci_report_init

on_err() {
  local code=$?
  ci_report_fail "Deploy interrompido na etapa «${CI_REPORT_STEP:-?}» (exit $code)." "$code"
}
trap on_err ERR

cd "$DEPLOY_PATH"

DEFAULT_URI="$(grep -E '^DEFAULT_URI=' .env.local 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"
if [[ -f .env.local && -n "$DEFAULT_URI" ]]; then
  CI_REPORT_STEP="Organismo env por ambiente"
  ci_report_step "$CI_REPORT_STEP"
  # shellcheck source=scripts/lib/organismo-env-sync.sh
  source "$ROOT/scripts/lib/organismo-env-sync.sh"
  organismo_env_sync_for_uri "$DEPLOY_PATH/.env.local" "$DEFAULT_URI"
fi

if [[ ! -f .env ]]; then
  CI_REPORT_STEP="Criar .env base (stub)"
  ci_report_step "$CI_REPORT_STEP"
  cat > .env <<'ENV'
APP_ENV=prod
APP_SECRET=
APP_SHARE_DIR=var/share
ENV
  echo "Criado .env stub — secrets em .env.local"
fi

CI_REPORT_STEP="Preparar diretórios"
ci_report_step "$CI_REPORT_STEP"
mkdir -p var/cache var/log var/sessions var/backups/db \
  public/uploads/users \
  public/uploads/chat/voice \
  public/uploads/chat/files \
  public/uploads/config \
  public/uploads/clinic/pacientes

chmod -R ug+rwx var public/uploads 2>/dev/null || true

CI_REPORT_STEP="Limpar cache prod corrompido"
ci_report_step "$CI_REPORT_STEP"
rm -rf var/cache/prod/* 2>/dev/null || true

CI_REPORT_STEP="Doctrine schema / migrations"
ci_report_step "$CI_REPORT_STEP"

db_has_core_schema() {
  $PHP_BIN bin/console dbal:run-sql "SELECT 1 FROM \`user\` LIMIT 1" --quiet >/dev/null 2>&1
}

if db_has_core_schema; then
  CI_REPORT_STEP="Backup DB (pré-migration)"
  ci_report_step "$CI_REPORT_STEP"
  backup_database_before_migrate "$DEPLOY_PATH" "$PHP_BIN" 7 || true

  $PHP_BIN bin/console doctrine:migrations:migrate --no-interaction
else
  echo "Banco sem schema base — doctrine:schema:create + marcar migrations"
  $PHP_BIN bin/console doctrine:schema:create --no-interaction
  $PHP_BIN bin/console doctrine:migrations:sync-metadata-storage --no-interaction 2>/dev/null || true
  $PHP_BIN bin/console doctrine:migrations:version --add --all --no-interaction

  if [[ "${SKIP_UNIO_PLATFORM_STEPS:-0}" == "1" ]]; then
    echo "Seeds iniciais (ambiente produto/homolog)"
    $PHP_BIN bin/console app:seed-users --allow-prod --no-interaction 2>/dev/null || true
    $PHP_BIN bin/console app:seed-product-grants --force --allow-prod --no-interaction 2>/dev/null || true
  fi
fi

CI_REPORT_STEP="Cache Symfony (prod)"
ci_report_step "$CI_REPORT_STEP"
$PHP_BIN bin/console cache:clear --env=prod --no-warmup
$PHP_BIN bin/console cache:warmup --env=prod

CI_REPORT_STEP="Migrar branding legado (admin_config)"
ci_report_step "$CI_REPORT_STEP"
bash "$ROOT/scripts/lib/migrate-legacy-branding.sh" "$DEPLOY_PATH" || true

link_public_dir() {
  local rel="$1"
  local src="$DEPLOY_PATH/public/$rel"
  local dest="$PUBLIC_HTML/$rel"

  if [[ ! -d "$src" ]]; then
    return 0
  fi

  if [[ -e "$dest" || -L "$dest" ]]; then
    rm -rf "$dest"
  fi

  ln -sfn "$src" "$dest"
  echo "link OK: $dest → $src"
}

CI_REPORT_STEP="Symlinks public_html → unio/public"
ci_report_step "$CI_REPORT_STEP"
for dir in css js images vendor uploads; do
  link_public_dir "$dir"
done

if [[ -d public/pos-operatorio ]]; then
  link_public_dir pos-operatorio
fi

CI_REPORT_STEP="Validar CSS no pacote e no document root"
ci_report_step "$CI_REPORT_STEP"
if ! grep -q "$CSS_MARKER" "$DEPLOY_PATH/public/css/unio-app.css"; then
  ci_report_fail "public/css/unio-app.css em $DEPLOY_PATH não contém '$CSS_MARKER' — pacote desatualizado?" 1
fi

if [[ -f "$PUBLIC_HTML/css/unio-app.css" ]]; then
  if ! grep -q "$CSS_MARKER" "$PUBLIC_HTML/css/unio-app.css"; then
    ls -la "$DEPLOY_PATH/public/css/unio-app.css" "$PUBLIC_HTML/css/unio-app.css" 2>/dev/null || true
    ci_report_fail "$PUBLIC_HTML/css/unio-app.css desatualizado após sync — verifique document root no cPanel." 1
  fi
  src_bytes=$(wc -c < "$DEPLOY_PATH/public/css/unio-app.css" | tr -d ' ')
  dest_bytes=$(wc -c < "$PUBLIC_HTML/css/unio-app.css" | tr -d ' ')
  if [[ "$src_bytes" != "$dest_bytes" ]]; then
    ls -la "$DEPLOY_PATH/public/css/unio-app.css" "$PUBLIC_HTML/css/unio-app.css" 2>/dev/null || true
    ci_report_fail "Tamanho CSS diverge após sync (unio=$src_bytes public_html=$dest_bytes)" 1
  fi
  echo "CSS OK: unio-app.css $dest_bytes bytes em unio e public_html"
fi

if [[ -f "$PUBLIC_HTML/css/unio-app.min.css" && -f "$DEPLOY_PATH/public/css/unio-app.min.css" ]]; then
  src_min=$(wc -c < "$DEPLOY_PATH/public/css/unio-app.min.css" | tr -d ' ')
  dest_min=$(wc -c < "$PUBLIC_HTML/css/unio-app.min.css" | tr -d ' ')
  if [[ "$src_min" != "$dest_min" ]]; then
    ci_report_fail "Tamanho unio-app.min.css diverge (unio=$src_min public_html=$dest_min)" 1
  fi
  echo "CSS OK: unio-app.min.css $dest_min bytes em unio e public_html"
fi

if [[ "${SKIP_UNIO_PLATFORM_STEPS:-0}" != "1" ]]; then
  CI_REPORT_STEP="Sincronizar identidade de e-mail"
  ci_report_step "$CI_REPORT_STEP"
  $PHP_BIN bin/console app:platform:sync-email-identity --no-interaction 2>/dev/null || true

  if [[ -n "${PLATFORM_OWNER_PASSWORD:-}" ]]; then
    CI_REPORT_STEP="Atualizar senha PLATFORM_OWNER"
    ci_report_step "$CI_REPORT_STEP"
    $PHP_BIN bin/console app:ensure-platform-owner \
      --allow-prod \
      --email=joabe@uniowork.com.br \
      --password="$PLATFORM_OWNER_PASSWORD" \
      --no-interaction 2>/dev/null || true
  fi

  CI_REPORT_STEP="Caixas de e-mail (cPanel)"
  ci_report_step "$CI_REPORT_STEP"
  bash "$DEPLOY_PATH/scripts/setup-platform-mailboxes.sh" 2>/dev/null || true
else
  echo "SKIP: steps Unio (e-mail / PLATFORM_OWNER / mailboxes) — ambiente produto"
fi

DEFAULT_URI="$(grep -E '^DEFAULT_URI=' .env.local 2>/dev/null | head -1 | cut -d= -f2- | tr -d '\r' || true)"

VHOST="${DEFAULT_URI#https://}"
VHOST="${VHOST#http://}"
VHOST="${VHOST%%/*}"
if command -v uapi >/dev/null 2>&1 && [[ -n "$VHOST" ]]; then
  CI_REPORT_STEP="PHP vhost (cPanel)"
  ci_report_step "$CI_REPORT_STEP"
  uapi --output=json LangPHP php_set_vhost_versions version=ea-php82 vhost="${VHOST}" 2>/dev/null \
    || uapi --output=json LangPHP php_set_vhost_versions version=ea-php81 vhost="${VHOST}" 2>/dev/null \
    || true
  uapi --output=json SSL start_autossl_check 2>/dev/null || true
fi

CI_REPORT_STEP="Symfony entrypoint em public_html"
ci_report_step "$CI_REPORT_STEP"
# shellcheck source=lib/sync-public-html-entrypoint.sh
source "$ROOT/scripts/lib/sync-public-html-entrypoint.sh"

if [[ -n "${DEFAULT_URI:-}" ]]; then
  CI_REPORT_STEP="Reparo vhost subdominio (Apache)"
  ci_report_step "$CI_REPORT_STEP"
  export DEFAULT_URI
  bash "$ROOT/scripts/lib/repair-subdomain-vhost.sh" || echo "AVISO: repair-subdomain-vhost (nao bloqueia deploy)"
fi

CI_REPORT_STEP="Registrar revisão de deploy"
ci_report_step "$CI_REPORT_STEP"
mkdir -p var/deploy
cat > var/deploy/revision.json <<EOF
{
  "commit": "${GITHUB_SHA:-unknown}",
  "branch": "${GITHUB_REF_NAME:-unknown}",
  "deployed_at": "$(date -Iseconds 2>/dev/null || date)",
  "deploy_path": "$DEPLOY_PATH"
}
EOF

trap - ERR
ci_report_success "Deploy server OK — $DEPLOY_PATH (public_html symlinked)"

$PHP_BIN bin/console app:platform-audit:record-deploy --no-interaction 2>/dev/null || true
