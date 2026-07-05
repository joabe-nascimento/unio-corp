#!/usr/bin/env bash
# Configura SSH para deploy HostGator a partir do GitHub Actions.
set -euo pipefail

script_dir="$(cd "$(dirname "$0")" && pwd)"
defaults_file="${script_dir}/../config/deploy-hostgator.defaults.env"
if [[ -f "$defaults_file" ]]; then
  set -a
  # shellcheck source=/dev/null
  source "$defaults_file"
  set +a
fi

original_host="${DEPLOY_HOST:-}"
canonical_host="${DEPLOY_CANONICAL_HOST:-${DEPLOY_SSH_CANONICAL_HOST:-br1136.hostgator.com.br}}"
user="${DEPLOY_USER:-${DEPLOY_SSH_DEFAULT_USER:-joabef36}}"
port="${DEPLOY_PORT:-${DEPLOY_SSH_DEFAULT_PORT:-2222}}"
key_file="${DEPLOY_KEY_FILE:-$HOME/.ssh/deploy_key}"
alias_name="${DEPLOY_SSH_ALIAS:-hg-deploy}"
preflight="${DEPLOY_SSH_PREFLIGHT:-1}"

is_cloudflare_ip() {
  case "$1" in
    104.*|172.6[0-9].*|172.67.*|2606:*) return 0 ;;
    *) return 1 ;;
  esac
}

is_public_site_host() {
  case "$1" in
    uniowork.com.br|www.uniowork.com.br) return 0 ;;
  esac
  [[ "$1" == *".uniowork.com.br" ]]
}

resolve_ipv4() {
  local name="$1"
  local ipv4=""
  if command -v getent >/dev/null 2>&1; then
    ipv4="$(getent ahostsv4 "$name" | awk '/STREAM/ {print $1; exit}')"
  elif command -v dig >/dev/null 2>&1; then
    ipv4="$(dig +short A "$name" | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | head -1)"
  fi
  printf '%s' "$ipv4"
}

mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"

if [[ ! -f "$key_file" ]]; then
  echo "Chave SSH ausente em ${key_file}" >&2
  exit 1
fi

chmod 600 "$key_file"
ssh-keygen -y -f "$key_file" > /dev/null || {
  echo "Chave SSH invalida — verifique DEPLOY_SSH_KEY no repositorio" >&2
  exit 1
}

host="$canonical_host"
if [[ -n "$original_host" ]]; then
  original_ip="$(resolve_ipv4 "$original_host")"
  if is_public_site_host "$original_host" || { [[ -n "$original_ip" ]] && is_cloudflare_ip "$original_ip"; }; then
    echo "AVISO: DEPLOY_SSH_HOST (${original_host}) e dominio publico/CDN — SSH usa ${host}."
  elif [[ "$original_host" != "$host" ]]; then
    echo "INFO: DEPLOY_SSH_HOST=${original_host}; conexao SSH via canonico ${host}."
  fi
fi

connect_host="$(resolve_ipv4 "$host")"
if [[ -z "$connect_host" ]]; then
  connect_host="$host"
fi

if is_cloudflare_ip "$connect_host"; then
  echo "ERRO: host SSH canonico (${host} -> ${connect_host}) resolve para CDN/Cloudflare." >&2
  echo "Atualize config/deploy-hostgator.defaults.env ou a variable DEPLOY_SSH_CANONICAL_HOST." >&2
  exit 1
fi

echo "SSH target: ${host} @ ${connect_host}:${port} (IPv4)"

touch "$HOME/.ssh/known_hosts"
chmod 600 "$HOME/.ssh/known_hosts"
if command -v ssh-keyscan >/dev/null 2>&1; then
  ssh-keyscan -4 -T 10 -p "$port" "$connect_host" >> "$HOME/.ssh/known_hosts" 2>/dev/null \
    || ssh-keyscan -T 10 -p "$port" "$connect_host" >> "$HOME/.ssh/known_hosts" 2>/dev/null \
    || true
fi

{
  echo "Host ${alias_name}"
  echo "  HostName ${connect_host}"
  echo "  User ${user}"
  echo "  Port ${port}"
  echo "  IdentityFile ${key_file}"
  echo "  AddressFamily inet"
  echo "  StrictHostKeyChecking accept-new"
  echo "  BatchMode yes"
  echo "  ConnectTimeout 20"
  echo "  ServerAliveInterval 15"
  echo "  ServerAliveCountMax 3"
} >> "$HOME/.ssh/config"
chmod 600 "$HOME/.ssh/config"

if [[ "$preflight" == "1" ]]; then
  echo "Testando conectividade SSH..."
  bash "${script_dir}/ci-retry.sh" ssh "${alias_name}" "echo deploy-ssh-ok"
fi
