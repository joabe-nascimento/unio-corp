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

# public_html é o document root; unio/public tem os estáticos do deploy.
# Symlink evita cópia manual: ao atualizar unio/public, o site serve os mesmos arquivos.
link_public_dir() {
  local rel="$1"
  local src="$DEPLOY_PATH/public/$rel"
  local dest="$PUBLIC_HTML/$rel"

  if [[ ! -d "$src" ]]; then
    return 0
  fi

  if [[ -e "$dest" || -L "$dest" ]]; then
    rm -rf "$dest"
  fi

  ln -sfn "$src" "$dest"
  echo "link OK: $dest → $src"
}

for dir in css js images vendor; do
  link_public_dir "$dir"
done

if [[ -d public/pos-operatorio ]]; then
  link_public_dir pos-operatorio
fi

# Falha o deploy se o pacote extraído não contém o CSS esperado (evita "deploy verde, site velho").
if ! grep -q "$CSS_MARKER" "$DEPLOY_PATH/public/css/unio-app.css"; then
  echo "ERRO: public/css/unio-app.css em $DEPLOY_PATH não contém '$CSS_MARKER' — pacote desatualizado?"
  exit 1
fi

# Falha se public_html (document root) ficou com CSS antigo ou tamanho divergente.
if [[ -f "$PUBLIC_HTML/css/unio-app.css" ]]; then
  if ! grep -q "$CSS_MARKER" "$PUBLIC_HTML/css/unio-app.css"; then
    echo "ERRO: $PUBLIC_HTML/css/unio-app.css desatualizado após sync — verifique document root no cPanel."
    ls -la "$DEPLOY_PATH/public/css/unio-app.css" "$PUBLIC_HTML/css/unio-app.css" 2>/dev/null || true
    exit 1
  fi
  src_bytes=$(wc -c < "$DEPLOY_PATH/public/css/unio-app.css" | tr -d ' ')
  dest_bytes=$(wc -c < "$PUBLIC_HTML/css/unio-app.css" | tr -d ' ')
  if [[ "$src_bytes" != "$dest_bytes" ]]; then
    echo "ERRO: tamanho CSS diverge após sync (unio=$src_bytes public_html=$dest_bytes)"
    ls -la "$DEPLOY_PATH/public/css/unio-app.css" "$PUBLIC_HTML/css/unio-app.css" 2>/dev/null || true
    exit 1
  fi
  echo "CSS OK: unio-app.css $dest_bytes bytes em unio e public_html"
fi

if [[ -f "$PUBLIC_HTML/css/unio-app.min.css" && -f "$DEPLOY_PATH/public/css/unio-app.min.css" ]]; then
  src_min=$(wc -c < "$DEPLOY_PATH/public/css/unio-app.min.css" | tr -d ' ')
  dest_min=$(wc -c < "$PUBLIC_HTML/css/unio-app.min.css" | tr -d ' ')
  if [[ "$src_min" != "$dest_min" ]]; then
    echo "ERRO: tamanho unio-app.min.css diverge (unio=$src_min public_html=$dest_min)"
    exit 1
  fi
  echo "CSS OK: unio-app.min.css $dest_min bytes em unio e public_html"
fi

echo "Deploy server OK — $DEPLOY_PATH"
