#!/usr/bin/env bash
# Sincroniza branches espelho com production (fast-forward ou force quando divergir).
# Branches de homolog/deploy (config/deploy-branches.txt) sao ignoradas.
set -euo pipefail

SOURCE="${1:-production}"
REMOTE="${2:-origin}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEPLOY_BRANCHES_FILE="${ROOT}/config/deploy-branches.txt"
DEPLOY_BRANCHES=()

if [[ -f "$DEPLOY_BRANCHES_FILE" ]]; then
  while IFS= read -r line || [[ -n "$line" ]]; do
    line="${line%%#*}"
    line="${line#"${line%%[![:space:]]*}"}"
    line="${line%"${line##*[![:space:]]}"}"
    [[ -z "$line" ]] && continue
    DEPLOY_BRANCHES+=("$line")
  done < "$DEPLOY_BRANCHES_FILE"
fi

is_deploy_branch() {
  local b="$1"
  for d in "${DEPLOY_BRANCHES[@]}"; do
    [[ "$b" == "$d" ]] && return 0
  done
  return 1
}

mapfile -t BRANCHES < <(git branch -r | sed -n "s|^  ${REMOTE}/||p" | grep -vE '^(HEAD|${SOURCE})$' | sort -u)

git fetch "$REMOTE" "$SOURCE"

SOURCE_SHA="$(git rev-parse "${REMOTE}/${SOURCE}")"
echo "Fonte: ${REMOTE}/${SOURCE} (${SOURCE_SHA})"
if [[ ${#DEPLOY_BRANCHES[@]} -gt 0 ]]; then
  echo "Ignorando homolog/deploy: ${DEPLOY_BRANCHES[*]}"
fi

for branch in "${BRANCHES[@]}"; do
  [[ "$branch" == "$SOURCE" ]] && continue

  if is_deploy_branch "$branch"; then
    echo "  ⊘ $branch (homolog deploy — ignorado)"
    continue
  fi

  ahead="$(git rev-list --count "${REMOTE}/${SOURCE}..${REMOTE}/${branch}" 2>/dev/null || echo 0)"
  behind="$(git rev-list --count "${REMOTE}/${branch}..${REMOTE}/${SOURCE}" 2>/dev/null || echo 0)"

  if [[ "$ahead" == "0" && "$behind" == "0" ]]; then
    echo "  = $branch (ja igual)"
    continue
  fi

  if [[ "$ahead" == "0" ]]; then
    echo "  → $branch (fast-forward, -${behind} commits)"
    git push "$REMOTE" "${SOURCE_SHA}:refs/heads/${branch}"
  else
    echo "  ! $branch (divergiu: +${ahead}/-${behind}) — reset para ${SOURCE}"
    git push "$REMOTE" "${SOURCE_SHA}:refs/heads/${branch}" --force
  fi
done

echo "Concluido."
