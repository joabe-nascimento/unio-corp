#!/usr/bin/env bash
# Encerra todas as sessões PHP (var/sessions) em todas as instâncias Unio no servidor.
set -euo pipefail

BASE="${BASE:-/home2/joabef36}"
APPS=(
  unio
  unio-staging
  unio-uniosaude
  unio-uniojuridico
  unio-rh
)

total=0
for app in "${APPS[@]}"; do
  dir="${BASE}/${app}/var/sessions"
  if [[ ! -d "$dir" ]]; then
    echo "SKIP ${app} (sem var/sessions)"
    continue
  fi
  count=0
  shopt -s nullglob
  for f in "$dir"/*; do
    if [[ -f "$f" ]]; then
      rm -f "$f"
      count=$((count + 1))
    fi
  done
  shopt -u nullglob
  total=$((total + count))
  echo "CLEARED ${app}: ${count} sessão(ões)"
done

echo "TOTAL_CLEARED=${total}"
