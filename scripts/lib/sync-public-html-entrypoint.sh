#!/usr/bin/env bash
# Regrava index.php e .htaccess do Symfony no document root (public_html do subdominio).
# Usado em todo deploy (deploy-server.sh) e no setup inicial de produto.
#
# Requer: DEPLOY_PATH, PUBLIC_HTML

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:?DEPLOY_PATH obrigatorio}"
PUBLIC_HTML="${PUBLIC_HTML:?PUBLIC_HTML obrigatorio}"
APP_DIR_NAME="$(basename "$DEPLOY_PATH")"

mkdir -p "$PUBLIC_HTML"

INDEX_PHP="$PUBLIC_HTML/index.php"
cat > "$INDEX_PHP" <<PHP
<?php

use App\\Kernel;

require_once __DIR__.'/../${APP_DIR_NAME}/vendor/autoload_runtime.php';

return static function (array \$context) {
    return new Kernel(\$context['APP_ENV'], (bool) \$context['APP_DEBUG']);
};
PHP
echo "Atualizado: $INDEX_PHP"

HTACCESS="$PUBLIC_HTML/.htaccess"
if [[ ! -f "$HTACCESS" ]] || ! grep -q 'RewriteRule \^ index.php' "$HTACCESS" 2>/dev/null; then
  cat > "$HTACCESS" <<'HTA'
DirectoryIndex index.php

<IfModule mod_negotiation.c>
    Options -MultiViews
</IfModule>

<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

<IfModule !mod_rewrite.c>
    DirectoryIndex index.php
</IfModule>
HTA
  echo "Atualizado: $HTACCESS"
else
  echo "Mantido: $HTACCESS (rewrite OK)"
fi
