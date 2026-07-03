#!/usr/bin/env bash
# Cria/atualiza caixas joabe@ e unio@ no cPanel (HostGator Titan).
# Senhas via env (GitHub Secrets): MAILBOX_JOABE_PASSWORD, MAILBOX_UNIO_PASSWORD (opcional).
# Credenciais geradas localmente ficam em var/secrets/mailbox-credentials.json (não versionado).
#
# Uso: bash scripts/setup-platform-mailboxes.sh

set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-$(cd "$(dirname "$0")/.." && pwd)}"
MAIL_DOMAIN="${MAIL_DOMAIN:-uniowork.com.br}"
SECRETS_DIR="$DEPLOY_PATH/var/secrets"
CREDENTIALS_FILE="$SECRETS_DIR/mailbox-credentials.json"
QUOTA_MB="${MAILBOX_QUOTA_MB:-1024}"

find_uapi() {
  if command -v uapi >/dev/null 2>&1; then
    command -v uapi
    return 0
  fi
  if [[ -x /usr/local/cpanel/bin/uapi ]]; then
    echo /usr/local/cpanel/bin/uapi
    return 0
  fi
  return 1
}

load_credentials() {
  if [[ ! -f "$CREDENTIALS_FILE" ]]; then
    return 0
  fi
  if ! command -v php >/dev/null 2>&1; then
    return 0
  fi
  MAILBOX_JOABE_PASSWORD="${MAILBOX_JOABE_PASSWORD:-$(php -r "
    \$j = @json_decode(file_get_contents('$CREDENTIALS_FILE'), true);
    echo isset(\$j['joabe']['password']) ? (string)\$j['joabe']['password'] : '';
  ")}"
  MAILBOX_UNIO_PASSWORD="${MAILBOX_UNIO_PASSWORD:-$(php -r "
    \$j = @json_decode(file_get_contents('$CREDENTIALS_FILE'), true);
    echo isset(\$j['unio']['password']) ? (string)\$j['unio']['password'] : '';
  ")}"
}

generate_password() {
  php -r "echo bin2hex(random_bytes(10)) . 'Un1o!' . random_int(10, 99);"
}

save_credentials() {
  local joabe_pass="${1:-}"
  local unio_pass="${2:-}"
  mkdir -p "$SECRETS_DIR"
  chmod 700 "$SECRETS_DIR" 2>/dev/null || true

  MAILBOX_SAVE_JOABE="$joabe_pass" MAILBOX_SAVE_UNIO="$unio_pass" \
  MAILBOX_SAVE_DOMAIN="$MAIL_DOMAIN" MAILBOX_SAVE_FILE="$CREDENTIALS_FILE" \
  php -r '
    $path = getenv("MAILBOX_SAVE_FILE") ?: "";
    $domain = getenv("MAILBOX_SAVE_DOMAIN") ?: "uniowork.com.br";
    $joabe = getenv("MAILBOX_SAVE_JOABE") ?: "";
    $unio = getenv("MAILBOX_SAVE_UNIO") ?: "";
    $existing = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    $out = [
      "domain" => $domain,
      "updated_at" => date("c"),
      "joabe" => [
        "email" => "joabe@" . $domain,
        "password" => $joabe !== "" ? $joabe : (string) ($existing["joabe"]["password"] ?? ""),
      ],
      "unio" => [
        "email" => "unio@" . $domain,
        "password" => $unio !== "" ? $unio : (string) ($existing["unio"]["password"] ?? ""),
      ],
      "smtp" => [
        "host" => "mail." . $domain,
        "port" => 587,
        "encryption" => "tls",
      ],
    ];
    file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  '
  chmod 600 "$CREDENTIALS_FILE" 2>/dev/null || true
}

mailbox_exists() {
  local local_part="$1"
  local uapi_bin="$2"
  "$uapi_bin" --output=json Email list_pops 2>/dev/null \
    | grep -q "\"email\":\"${local_part}@${MAIL_DOMAIN}\"" \
    || "$uapi_bin" --output=json Email list_pops 2>/dev/null \
    | grep -q "\"email\":\"${local_part}\"" \
    || return 1
}

provision_mailbox() {
  local local_part="$1"
  local password="$2"
  local uapi_bin="$3"

  if [[ -z "$password" ]]; then
    echo "mailbox: $local_part@$MAIL_DOMAIN — senha ausente, pulando"
    return 0
  fi

  if mailbox_exists "$local_part" "$uapi_bin"; then
    echo "mailbox: $local_part@$MAIL_DOMAIN — já existe, atualizando senha"
    if ! "$uapi_bin" --output=json Email passwd_pop \
      email="$local_part" domain="$MAIL_DOMAIN" password="$password" >/dev/null 2>&1; then
      echo "mailbox: aviso — não foi possível atualizar senha de $local_part@$MAIL_DOMAIN"
    fi
    return 0
  fi

  echo "mailbox: criando $local_part@$MAIL_DOMAIN"
  if ! "$uapi_bin" --output=json Email add_pop \
    email="$local_part" domain="$MAIL_DOMAIN" password="$password" quota="$QUOTA_MB" >/dev/null 2>&1; then
    echo "mailbox: falha ao criar $local_part@$MAIL_DOMAIN (verifique cPanel / quota de contas)"
    return 1
  fi
  echo "mailbox: OK $local_part@$MAIL_DOMAIN"
}

main() {
  load_credentials

  if [[ -z "${MAILBOX_JOABE_PASSWORD:-}" ]]; then
    MAILBOX_JOABE_PASSWORD="$(generate_password)"
    echo "mailbox: senha joabe gerada automaticamente (será salva em var/secrets/)"
  fi

  if ! UAPI_BIN="$(find_uapi)"; then
    echo "mailbox: uapi não encontrado — pule criação manual no cPanel (Contas de e-mail)"
    save_credentials "${MAILBOX_JOABE_PASSWORD:-}" "${MAILBOX_UNIO_PASSWORD:-}"
    return 0
  fi

  provision_mailbox "joabe" "${MAILBOX_JOABE_PASSWORD:-}" "$UAPI_BIN" || true
  if [[ -n "${MAILBOX_UNIO_PASSWORD:-}" ]]; then
    provision_mailbox "unio" "${MAILBOX_UNIO_PASSWORD:-}" "$UAPI_BIN" || true
  else
    echo "mailbox: unio@$MAIL_DOMAIN — MAILBOX_UNIO_PASSWORD não definido; mantendo caixa existente se houver"
  fi

  save_credentials "${MAILBOX_JOABE_PASSWORD:-}" "${MAILBOX_UNIO_PASSWORD:-}"
  echo "mailbox: credenciais em $CREDENTIALS_FILE (chmod 600)"
}

main "$@"
