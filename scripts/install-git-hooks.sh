#!/usr/bin/env bash
# Instala hooks git do projeto (pre-push → validate-before-push.sh).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
HOOKS_DIR="${ROOT}/.git/hooks"

if [[ ! -d "${ROOT}/.git" ]]; then
  echo "Erro: .git não encontrado. Rode dentro do repositório." >&2
  exit 1
fi

mkdir -p "$HOOKS_DIR"
cp "${ROOT}/scripts/hooks/pre-push" "${HOOKS_DIR}/pre-push"
chmod +x "${HOOKS_DIR}/pre-push" "${ROOT}/scripts/validate-before-push.sh"

echo "Hook pre-push instalado em .git/hooks/pre-push"
echo "Cada git push rodará: bash scripts/validate-before-push.sh"
echo "Pular: SKIP_VALIDATE=1 git push  ou  git push --no-verify"
