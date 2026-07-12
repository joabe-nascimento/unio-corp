#!/usr/bin/env bash
# Backup MySQL antes de migrations — usa DATABASE_URL do .env.local.
# Mantém os últimos N arquivos em var/backups/db/.

backup_database_before_migrate() {
  local project_dir="${1:?project dir}"
  local php_bin="${2:-php}"
  local keep="${3:-7}"

  if ! command -v mysqldump >/dev/null 2>&1; then
    echo "AVISO: mysqldump indisponível — backup DB pulado"
    return 0
  fi

  local backup_dir="${project_dir}/var/backups/db"
  mkdir -p "$backup_dir"

  local params_script="${project_dir}/scripts/lib/print-db-mysql-params.php"
  if [[ ! -f "$params_script" ]]; then
    echo "AVISO: print-db-mysql-params.php ausente — backup DB pulado"
    return 0
  fi

  set +u
  # shellcheck disable=SC1090
  eval "$("$php_bin" "$params_script" "$project_dir")"
  set -u

  if [[ -z "${DB_MYSQL_NAME:-}" ]]; then
    echo "AVISO: não foi possível ler DATABASE_URL — backup DB pulado"
    return 0
  fi

  local stamp outfile cnf
  stamp="$(date +%Y%m%d-%H%M%S)"
  outfile="${backup_dir}/pre-migrate-${stamp}.sql.gz"
  cnf="$(mktemp "${TMPDIR:-/tmp}/unio-mysql.XXXXXX")"

  cat > "$cnf" <<EOF
[client]
user=${DB_MYSQL_USER}
password=${DB_MYSQL_PASS}
host=${DB_MYSQL_HOST}
port=${DB_MYSQL_PORT}
EOF
  chmod 600 "$cnf"

  local dump_args=(--single-transaction --quick)
  if mysqldump --help 2>/dev/null | grep -q -- '--no-tablespaces'; then
    dump_args+=(--no-tablespaces)
  fi

  if mysqldump --defaults-extra-file="$cnf" "${dump_args[@]}" "$DB_MYSQL_NAME" | gzip -c > "$outfile"; then
    local size
    size="$(wc -c < "$outfile" | tr -d ' ')"
    echo "Backup DB OK: $outfile ($size bytes)"
  else
    rm -f "$outfile"
    rm -f "$cnf"
    echo "AVISO: mysqldump falhou — migration continuará sem backup"
    return 0
  fi

  rm -f "$cnf"

  # Rotação: manter os N backups mais recentes
  local files_to_delete
  files_to_delete="$(ls -1t "$backup_dir"/pre-migrate-*.sql.gz 2>/dev/null | tail -n +"$(( keep + 1 ))" || true)"
  if [[ -n "$files_to_delete" ]]; then
    echo "$files_to_delete" | while IFS= read -r old; do
      [[ -n "$old" ]] && rm -f "$old"
    done
  fi
}
