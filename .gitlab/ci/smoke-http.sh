#!/bin/sh
# Smoke HTTP pos-deploy (mesmas URLs do GitHub Actions).
set -eu

if [ -z "${SMOKE_URLS:-}" ]; then
  echo "SMOKE_URLS required"
  exit 1
fi

failed=0
while IFS= read -r url || [ -n "$url" ]; do
  url=$(printf '%s' "$url" | tr -d '\r')
  # trim spaces
  url=$(echo "$url" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
  [ -z "$url" ] && continue
  code=$(curl -fsS -o /dev/null -w '%{http_code}' --connect-timeout 15 --max-time 30 "$url" 2>/dev/null || echo '000')
  if [ "$code" != "200" ]; then
    echo "FAIL $url → HTTP $code"
    failed=1
  else
    echo "OK   $url → HTTP $code"
  fi
done <<EOF
${SMOKE_URLS}
EOF

exit "$failed"
