#!/usr/bin/env bash
# Pós-deploy na HostGator — migrations, cache e sync de estáticos para public_html.
# Uso manual: bash scripts/deploy-server.sh
# Chamado pelo GitHub Actions após extração do tar.

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio}"
PUBLIC_HTML="${PUBLIC_HTML:-/home2/joabef36/public_html}"
PHP_BIN="${PHP_BIN:-php}"
CSS_MARKER="${CSS_MARKER:-core-projetos-toolbar}"

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

# HostGator shared: rsync pode não existir ou falhar silenciosamente — cp -a é confiável.
sync_public_dir() {
  local rel="$1"
  local src="$DEPLOY_PATH/public/$rel"
  local dest="$PUBLIC_HTML/$rel"

  if [[ ! -d "$src" ]]; then
    return 0
  fi

  mkdir -p "$dest"
  cp -a "$src/." "$dest/"
  echo "sync OK: public/$rel → $dest ($(find "$src" -type f | wc -l | tr -d ' ') arquivos)"
}

for dir in css js images vendor; do
  sync_public_dir "$dir"
done

if [[ -d public/pos-operatorio ]]; then
  sync_public_dir pos-operatorio
fi

# Falha o deploy se o pacote extraído não contém o CSS esperado (evita "deploy verde, site velho").
if ! grep -q "$CSS_MARKER" "$DEPLOY_PATH/public/css/unio-app.css"; then
  echo "ERRO: public/css/unio-app.css em $DEPLOY_PATH não contém '$CSS_MARKER' — pacote desatualizado?"
  exit 1
fi

# Falha se public_html (document root em alguns setups) ficou com CSS antigo.
if [[ -f "$PUBLIC_HTML/css/unio-app.css" ]] && ! grep -q "$CSS_MARKER" "$PUBLIC_HTML/css/unio-app.css"; then
  echo "ERRO: $PUBLIC_HTML/css/unio-app.css desatualizado após sync — verifique document root no cPanel."
  ls -la "$DEPLOY_PATH/public/css/unio-app.css" "$PUBLIC_HTML/css/unio-app.css" 2>/dev/null || true
  exit 1
fi

echo "Deploy server OK — $DEPLOY_PATH (CSS app: $(wc -c < "$DEPLOY_PATH/public/css/unio-app.css" | tr -d ' ') bytes)"
