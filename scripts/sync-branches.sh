#!/usr/bin/env bash
# Sincroniza branches espelho com production (fast-forward ou force quando divergir).
# CI não dispara em product/** e feature/** — só production/main/staging.
set -euo pipefail

SOURCE="${1:-production}"
REMOTE="${2:-origin}"

mapfile -t BRANCHES < <(git branch -r | sed -n "s|^  ${REMOTE}/||p" | grep -vE '^(HEAD|${SOURCE})$' | sort -u)

git fetch "$REMOTE" "$SOURCE"

SOURCE_SHA="$(git rev-parse "${REMOTE}/${SOURCE}")"
echo "Fonte: ${REMOTE}/${SOURCE} (${SOURCE_SHA})"

for branch in "${BRANCHES[@]}"; do
  [[ "$branch" == "$SOURCE" ]] && continue
  ahead="$(git rev-list --count "${REMOTE}/${SOURCE}..${REMOTE}/${branch}" 2>/dev/null || echo 0)"
  behind="$(git rev-list --count "${REMOTE}/${branch}..${REMOTE}/${SOURCE}" 2>/dev/null || echo 0)"

  if [[ "$ahead" == "0" && "$behind" == "0" ]]; then
    echo "  = $branch (já igual)"
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

echo "Concluído. CI roda apenas em push para ${SOURCE}, main e staging."
