#!/usr/bin/env bash
# Auditoria rápida do banco — rodar no servidor: bash scripts/audit-database.sh
set -euo pipefail

cd "$(dirname "$0")/.."
PHP="${PHP_BIN:-php}"

run_sql() {
  $PHP bin/console dbal:run-sql "$1" --no-ansi 2>/dev/null | tail -n +3
}

echo "========== BANCO =========="
run_sql "SELECT VERSION() AS mysql_version"

echo ""
echo "========== MIGRATIONS =========="
$PHP bin/console doctrine:migrations:status --no-ansi 2>/dev/null | grep -E "Current|Latest|Executed|New|Database|Name" || true

echo ""
echo "========== SCHEMA =========="
$PHP bin/console doctrine:schema:validate --no-ansi 2>/dev/null | grep -E "mapping|Database|OK|ERROR" || true

echo ""
echo "========== CONTAGENS =========="
run_sql "SELECT (SELECT COUNT(*) FROM \`user\`) AS users, (SELECT COUNT(*) FROM \`user\` WHERE ativo=1) AS ativos, (SELECT COUNT(*) FROM \`user\` WHERE perfil='PLATFORM_OWNER') AS owners, (SELECT COUNT(*) FROM empresa) AS empresas"

echo ""
echo "========== TABELAS =========="
run_sql "SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema=DATABASE()"

echo ""
echo "========== platform_audit_log =========="
run_sql "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='platform_audit_log'"

echo ""
echo "========== E-MAILS DUPLICADOS =========="
run_sql "SELECT email, COUNT(*) AS n FROM \`user\` GROUP BY email HAVING n > 1"

echo ""
echo "========== CNPJ DUPLICADO =========="
run_sql "SELECT cnpj, COUNT(*) AS n FROM empresa GROUP BY cnpj HAVING n > 1"

echo ""
echo "========== TOP TABELAS (MB) =========="
run_sql "SELECT table_name, ROUND((data_length+index_length)/1024/1024,2) AS mb FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY (data_length+index_length) DESC LIMIT 8"

echo ""
echo "========== FIM =========="
