#!/usr/bin/env bash
# Helpers para relatório de falha em CI/deploy (source, não executar direto).
# Uso: source scripts/lib/ci-report.sh

ci_report_init() {
  CI_REPORT_KIND="${CI_REPORT_KIND:-pipeline}"
  CI_REPORT_FILE="${CI_REPORT_FILE:-var/log/ci-failure-report.txt}"
  CI_REPORT_STEP=""
  mkdir -p "$(dirname "$CI_REPORT_FILE")"
  : > "$CI_REPORT_FILE"
}

ci_report_step() {
  CI_REPORT_STEP="$1"
  echo ""
  echo "==> $1"
}

ci_report_fail() {
  local message="${1:-Falha desconhecida}"
  local exit_code="${2:-1}"
  local ts
  ts="$(date -Iseconds 2>/dev/null || date)"

  {
    echo "════════════════════════════════════════════════════════"
    echo " RELATÓRIO DE FALHA — ${CI_REPORT_KIND}"
    echo "════════════════════════════════════════════════════════"
    echo "Quando:    $ts"
    echo "Etapa:     ${CI_REPORT_STEP:-(não iniciada)}"
    echo "Branch:    ${GIT_BRANCH:-${GITHUB_REF_NAME:-desconhecida}}"
    echo "Commit:    ${GITHUB_SHA:-$(git rev-parse --short HEAD 2>/dev/null || echo '—')}"
    echo "Runner:    ${RUNNER_OS:-local} / ${PHP_BIN:-php}"
    echo "Exit code: $exit_code"
    echo ""
    echo "Causa:"
    echo "  $message"
    echo ""
    echo "Como corrigir (checklist):"
    case "$CI_REPORT_KIND" in
      validate)
        echo "  1. Rode local: composer validate:ci"
        echo "  2. Se PHPUnit: php bin/phpunit --filter NomeDoTeste"
        echo "  3. Se PHPStan: composer phpstan"
        echo "  4. Se assets: php bin/minify-css.php && npm ci && npm run vendor:sync"
        ;;
      deploy)
        echo "  1. Veja logs do job Deploy Production no GitHub Actions"
        echo "  2. SSH: tail -50 ~/unio/var/log/prod.log"
        echo "  3. SSH: bash ~/unio/scripts/deploy-server.sh (manual, com cuidado)"
        echo "  4. Confirme symlinks: ls -la ~/public_html/css"
        ;;
      *)
        echo "  1. Reproduza localmente o comando da etapa que falhou"
        ;;
    esac
    echo ""
    echo "Arquivo: $CI_REPORT_FILE"
    echo "════════════════════════════════════════════════════════"
  } | tee -a "$CI_REPORT_FILE" >&2

  if [[ -n "${GITHUB_STEP_SUMMARY:-}" && -f "$CI_REPORT_FILE" ]]; then
    {
      echo "## Falha: ${CI_REPORT_KIND}"
      echo ""
      echo '```text'
      cat "$CI_REPORT_FILE"
      echo '```'
    } >> "$GITHUB_STEP_SUMMARY"
  fi

  exit "$exit_code"
}

ci_report_success() {
  local message="${1:-OK}"
  local ts
  ts="$(date -Iseconds 2>/dev/null || date)"

  {
    echo "════════════════════════════════════════════════════════"
    echo " RELATÓRIO DE SUCESSO — ${CI_REPORT_KIND}"
    echo "════════════════════════════════════════════════════════"
    echo "Quando:  $ts"
    echo "Branch:  ${GIT_BRANCH:-${GITHUB_REF_NAME:-desconhecida}}"
    echo "Commit:  ${GITHUB_SHA:-$(git rev-parse --short HEAD 2>/dev/null || echo '—')}"
    echo "Status:  $message"
    echo "════════════════════════════════════════════════════════"
  } | tee "$CI_REPORT_FILE"

  if [[ -n "${GITHUB_STEP_SUMMARY:-}" ]]; then
    echo "✅ **${CI_REPORT_KIND}** — $message" >> "$GITHUB_STEP_SUMMARY"
  fi
}
