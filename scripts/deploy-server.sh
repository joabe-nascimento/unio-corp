#!/usr/bin/env bash
# Pós-deploy na HostGator — migrations, cache e sync de estáticos para public_html.
# Uso manual: bash scripts/deploy-server.sh
# Chamado pelo GitHub Actions após rsync.

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio}"
PUBLIC_HTML="${PUBLIC_HTML:-/home2/joabef36/public_html}"
PHP_BIN="${PHP_BIN:-php}"

cd "$DEPLOY_PATH"

mkdir -p var/cache var/log var/sessions \
  public/uploads/users \
  public/uploads/chat/voice \
  public/uploads/chat/files \
  public/uploads/config

chmod -R ug+rwx var public/uploads 2>/dev/null || true

# Remove físico do cache antes de qualquer comando PHP — evita ParameterNotFoundException
# por container corrompido que impede até o cache:clear de rodar.
rm -rf var/cache/prod/* 2>/dev/null || true

$PHP_BIN bin/console doctrine:migrations:migrate --no-interaction
$PHP_BIN bin/console cache:clear --env=prod --no-warmup
$PHP_BIN bin/console cache:warmup --env=prod

for dir in css js images vendor; do
  if [[ -d "public/$dir" ]]; then
    mkdir -p "$PUBLIC_HTML/$dir"
    rsync -a "public/$dir/" "$PUBLIC_HTML/$dir/"
  fi
done

if [[ -d public/pos-operatorio ]]; then
  mkdir -p "$PUBLIC_HTML/pos-operatorio"
  rsync -a public/pos-operatorio/ "$PUBLIC_HTML/pos-operatorio/"
fi

echo "Deploy server OK — $DEPLOY_PATH"
