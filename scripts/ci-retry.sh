#!/usr/bin/env bash
# Retry wrapper for flaky CI network steps (SSH/SCP to HostGator).
set -u

attempts="${CI_RETRY_ATTEMPTS:-5}"
delay="${CI_RETRY_DELAY:-20}"
n=1

while true; do
  if "$@"; then
    exit 0
  fi
  exit_code=$?
  if (( n >= attempts )); then
    echo "ci-retry: falhou apos ${attempts} tentativas (exit ${exit_code}): $*" >&2
    exit "$exit_code"
  fi
  echo "ci-retry: tentativa ${n}/${attempts} falhou (exit ${exit_code}); aguardando ${delay}s..." >&2
  sleep "$delay"
  n=$((n + 1))
done
