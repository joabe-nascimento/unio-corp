#!/usr/bin/env bash
# Validação local/CI antes de push ou deploy — espelha o pipeline de qualidade.
#
# Uso:
#   bash scripts/validate-before-push.sh
#   VALIDATE_FRESH_DB=1 bash scripts/validate-before-push.sh   # recria banco (igual CI)
#   QUICK=1 bash scripts/validate-before-push.sh               # só lints estáticos
#   SKIP_PHPSTAN=1 bash scripts/validate-before-push.sh
#   SKIP_VALIDATE=1 git push                                     # pula o hook pre-push
#
# Relatório de falha: var/log/ci-failure-report.txt (+ GitHub Step Summary em CI)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# shellcheck source=scripts/lib/ci-report.sh
source "$ROOT/scripts/lib/ci-report.sh"

PHP_BIN="${PHP_BIN:-php}"
APP_ENV="${APP_ENV:-dev}"
QUICK="${QUICK:-0}"
SKIP_PHPSTAN="${SKIP_PHPSTAN:-0}"
SKIP_TESTS="${SKIP_TESTS:-0}"
SKIP_ASSETS="${SKIP_ASSETS:-0}"
SKIP_DB="${SKIP_DB:-0}"
VALIDATE_FRESH_DB="${VALIDATE_FRESH_DB:-0}"

GIT_BRANCH="${GIT_BRANCH:-$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")}"

CI_REPORT_KIND=validate
ci_report_init

RED='\033[0;31m'
GRN='\033[0;32m'
BLD='\033[1m'
RST='\033[0m'

step=0
run_step() {
  step=$((step + 1))
  CI_REPORT_STEP="[$step] $1"
  ci_report_step "$CI_REPORT_STEP"
}

on_err() {
  local code=$?
  ci_report_fail "Comando interrompido na etapa «${CI_REPORT_STEP:-?}» (exit $code)." "$code"
}
trap on_err ERR

db_ping() {
  "$PHP_BIN" bin/console dbal:run-sql "SELECT 1" --no-interaction >/dev/null 2>&1
}

run_step "Composer validate"
composer validate --no-check-publish --no-interaction

run_step "Symfony — container DI"
"$PHP_BIN" bin/console lint:container --no-interaction

run_step "Symfony — YAML (config)"
"$PHP_BIN" bin/console lint:yaml config --no-interaction

run_step "Symfony — Twig (templates)"
"$PHP_BIN" bin/console lint:twig templates --no-interaction

if [[ "$SKIP_DB" != "1" ]]; then
  if db_ping; then
    if [[ "$VALIDATE_FRESH_DB" == "1" ]]; then
      run_step "Banco — recriar schema e seeds (modo CI)"
      "$PHP_BIN" bin/console doctrine:database:drop --force --if-exists --no-interaction
      "$PHP_BIN" bin/console doctrine:database:create --no-interaction
      "$PHP_BIN" bin/console doctrine:schema:create --no-interaction
      "$PHP_BIN" bin/console app:seed-users --no-interaction
      "$PHP_BIN" bin/console app:seed-product-grants --force --no-interaction
    else
      run_step "Banco — schema vs entidades"
      "$PHP_BIN" bin/console doctrine:schema:validate --no-interaction
    fi

    run_step "Regras de sistema (permissões, rotas, seeds)"
    "$PHP_BIN" bin/console app:validate-system --no-interaction
  else
    echo "  ⚠ Banco indisponível — pulando validação de schema/sistema/testes."
    echo "    Configure DATABASE_URL ou use VALIDATE_FRESH_DB=1 com MariaDB/MySQL ativo."
    SKIP_TESTS=1
  fi
else
  echo "  ⚠ SKIP_DB=1 — pulando validações de banco."
  SKIP_TESTS=1
fi

if [[ "$QUICK" != "1" && "$SKIP_PHPSTAN" != "1" ]]; then
  run_step "PHPStan"
  composer phpstan
elif [[ "$SKIP_PHPSTAN" == "1" ]]; then
  echo ""
  echo "  ⚠ PHPStan ignorado (SKIP_PHPSTAN=1)."
fi

if [[ "$QUICK" != "1" && "$SKIP_TESTS" != "1" ]]; then
  run_step "PHPUnit"
  APP_ENV=test "$PHP_BIN" bin/phpunit
fi

if [[ "$QUICK" != "1" && "$SKIP_ASSETS" != "1" && -f package.json ]]; then
  run_step "CSS minificado (prod usa unio-app.min.css)"
  "$PHP_BIN" bin/minify-css.php

  run_step "Assets — npm ci + vendor:sync"
  if command -v npm >/dev/null 2>&1; then
    npm ci --no-audit --no-fund
    npm run vendor:sync
  else
    echo "  ⚠ npm não encontrado — pulando sync de assets."
  fi
fi

trap - ERR
ci_report_success "Validação concluída — OK para push/deploy."
echo ""
echo -e "${GRN}${BLD}Validação concluída — OK para push/deploy.${RST}"
