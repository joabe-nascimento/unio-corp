#!/usr/bin/env bash
# Reparo emergencial — HTTP 500 por .env.local com valores sem aspas (Symfony Dotenv).
# Rodar no Terminal do cPanel (não depende de SSH externo):
#   cd /home2/joabef36/unio-uniosaude && bash scripts/lib/repair-env-local-500.sh
set -euo pipefail

ENV_FILE="${1:-/home2/joabef36/unio-uniosaude/.env.local}"
PROJECT_DIR="$(cd "$(dirname "$ENV_FILE")/.." && pwd)"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERRO: $ENV_FILE não encontrado" >&2
  exit 1
fi

echo "==> Corrigindo $ENV_FILE"

# Valores com espaço precisam de aspas no Symfony Dotenv
python3 - <<'PY' "$ENV_FILE"
import re, sys
path = sys.argv[1]
lines = open(path, encoding="utf-8").read().splitlines()
out = []
fixes = {
    "UNIO_ORGANISMO_BRAND_NAME": '"Unio Saúde"',
    "UNIO_ORGANISMO_UNIT_LABEL": '"Clínica"',
}
for line in lines:
    if "=" not in line or line.strip().startswith("#"):
        out.append(line)
        continue
    key, _, val = line.partition("=")
    key = key.strip()
    val = val.strip()
    if key in fixes:
        out.append(f"{key}={fixes[key]}")
        print(f"  corrigido: {key}")
    elif " " in val and not (val.startswith('"') or val.startswith("'")):
        out.append(f'{key}="{val}"')
        print(f"  aspas em: {key}")
    else:
        out.append(line)
open(path, "w", encoding="utf-8").write("\n".join(out) + "\n")
PY

cd "$PROJECT_DIR"
php bin/console cache:clear --env=prod --no-debug
echo "==> Smoke local"
code=$(curl -s -o /dev/null -w "%{http_code}" -H "Host: uniosaude.uniowork.com.br" "http://127.0.0.1/login" || echo "000")
echo "HTTP /login (vhost local): $code"
echo "OK — se 200/302, teste https://uniosaude.uniowork.com.br/login"
