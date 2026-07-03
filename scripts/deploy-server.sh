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

CI_REPORT_KIND=deploy
CI_REPORT_FILE="${DEPLOY_PATH}/var/log/deploy-report.txt"
ci_report_init

on_err() {
  local code=$?
  ci_report_fail "Deploy interrompido na etapa «${CI_REPORT_STEP:-?}» (exit $code)." "$code"
}
trap on_err ERR

cd "$DEPLOY_PATH"

CI_REPORT_STEP="Preparar diretórios"
ci_report_step "$CI_REPORT_STEP"
mkdir -p var/cache var/log var/sessions \
  public/uploads/users \
  public/uploads/chat/voice \
  public/uploads/chat/files \
  public/uploads/config

chmod -R ug+rwx var public/uploads 2>/dev/null || true

CI_REPORT_STEP="Limpar cache prod corrompido"
ci_report_step "$CI_REPORT_STEP"
rm -rf var/cache/prod/* 2>/dev/null || true

CI_REPORT_STEP="Doctrine migrations"
ci_report_step "$CI_REPORT_STEP"
$PHP_BIN bin/console doctrine:migrations:migrate --no-interaction

CI_REPORT_STEP="Cache Symfony (prod)"
ci_report_step "$CI_REPORT_STEP"
$PHP_BIN bin/console cache:clear --env=prod --no-warmup
$PHP_BIN bin/console cache:warmup --env=prod

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
for dir in css js images vendor; do
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

trap - ERR
ci_report_success "Deploy server OK — $DEPLOY_PATH (public_html symlinked)"
