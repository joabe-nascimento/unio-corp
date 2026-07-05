#!/usr/bin/env bash
# Remove seeds demo do homolog RH e copia PLATFORM_OWNER da producao (mesmo servidor).
set -euo pipefail

PHP="${PHP_BIN:-php}"
DEPLOY_PATH="${DEPLOY_PATH:-/home2/joabef36/unio-rh}"
PROD_DB="${PROD_DB:-joabef36_unio}"

cd "$DEPLOY_PATH"

echo "== Removendo usuarios demo do RH =="
$PHP bin/console dbal:run-sql \
  "DELETE FROM user_product_grant WHERE user_id IN (SELECT id FROM user WHERE email LIKE '%@unio.dev' OR email LIKE '%@nexus.dev' OR email LIKE '%@edu360.dev')" \
  --env=prod --quiet 2>/dev/null || true

$PHP bin/console dbal:run-sql \
  "DELETE FROM user WHERE email LIKE '%@unio.dev' OR email LIKE '%@nexus.dev' OR email LIKE '%@edu360.dev'" \
  --env=prod --quiet

$PHP bin/console dbal:run-sql \
  "DELETE FROM empresa WHERE cnpj IN ('11.111.111/0001-11', '22.222.222/0001-22', '33.333.333/0001-33')" \
  --env=prod --quiet 2>/dev/null || true

echo "== Copiando PLATFORM_OWNER da producao (se ausente) =="
$PHP bin/console dbal:run-sql \
  "INSERT INTO user (nome, email, password, perfil, roles, ativo, empresa_id, criado_em, avatar, onboarding_completed_steps, termos_aceitos_em, termos_versao)
SELECT u.nome, u.email, u.password, u.perfil, u.roles, u.ativo, NULL, u.criado_em, u.avatar, u.onboarding_completed_steps, u.termos_aceitos_em, u.termos_versao
FROM ${PROD_DB}.user u
WHERE u.email = 'joabe@uniowork.com.br'
AND NOT EXISTS (SELECT 1 FROM user x WHERE x.email = 'joabe@uniowork.com.br')" \
  --env=prod 2>&1 || echo "AVISO: insert owner falhou"

echo "== Usuarios RH =="
$PHP bin/console dbal:run-sql "SELECT email, perfil FROM user" --env=prod 2>/dev/null
